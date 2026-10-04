<?php

namespace App\Services\Planejamento;

use App\Models\PlanilhaFonte;
use App\Models\PlanilhaImportacao;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Busca a planilha pelo link de compartilhamento do OneDrive e a entrega ao
 * PlanilhaImportService. Salvar o link, o botão Analisar e a verificação
 * diária passam todos por aqui.
 */
class PlanilhaFonteService
{
    private const HOSTS = ['1drv.ms', 'onedrive.live.com'];
    private const SUFIXO_SHAREPOINT = '.sharepoint.com';
    private const TAMANHO_MAXIMO = 5 * 1024 * 1024;

    // Acesso de visitante do OneDrive pessoal (o mesmo do navegador ao abrir
    // um link compartilhado). Conferido com o link real em 04/10/2026; a API
    // antiga (api.onedrive.com/v1.0/shares) passou a responder 401.
    private const URL_TOKEN = 'https://api-badgerp.svc.ms/v1.0/token';
    private const APP_ID_VISITANTE = '5cbed6ac-a083-4e14-b191-b4ba07653de2';
    private const URL_ITEM = 'https://my.microsoftpersonalcontent.com/_api/v2.0/shares/';
    private const HOSTS_DE_DOWNLOAD = ['.microsoftpersonalcontent.com', '.sharepoint.com', '.1drv.com', '.onedrive.live.com'];

    public function __construct(private PlanilhaImportService $importador)
    {
    }

    /** Mensagem de recusa, ou null quando o link pode ser usado. */
    public function motivoDaRecusa(string $url): ?string
    {
        $partes = parse_url(trim($url));
        $host   = strtolower($partes['host'] ?? '');

        $aceito = ($partes['scheme'] ?? '') === 'https'
            && (in_array($host, self::HOSTS, true) || str_ends_with($host, self::SUFIXO_SHAREPOINT));

        return $aceito ? null : 'Cole o link de compartilhamento do OneDrive (começa com https://1drv.ms ou https://onedrive.live.com).';
    }

    /**
     * Salva o link da família, mas só se a planilha puder ser lida por ele.
     *
     * @return array{fonte: ?PlanilhaFonte, importacao: ?PlanilhaImportacao, erro: ?string}
     */
    public function configurar(int $tenantId, ?int $userId, string $url): array
    {
        $url = trim($url);
        if ($erro = $this->motivoDaRecusa($url)) {
            return ['fonte' => null, 'importacao' => null, 'erro' => $erro];
        }

        $arquivo = $this->baixar($url);
        if (isset($arquivo['erro'])) {
            return ['fonte' => null, 'importacao' => null, 'erro' => $arquivo['erro']];
        }

        $fonte = PlanilhaFonte::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId],
            ['user_id' => $userId, 'url' => $url],
        );

        return ['fonte' => $fonte, 'importacao' => $this->importar($fonte, $userId, $arquivo['caminho'], false), 'erro' => null];
    }

    /**
     * Baixa a versão atual e importa. No modo automático, planilha igual à
     * última importada só atualiza a data da verificação.
     *
     * @return array{importacao: ?PlanilhaImportacao, erro: ?string}
     */
    public function analisar(PlanilhaFonte $fonte, ?int $userId = null, bool $automatica = false): array
    {
        $arquivo = $this->baixar($fonte->url);
        if (isset($arquivo['erro'])) {
            $fonte->update(['verificada_em' => now(), 'status' => PlanilhaFonte::FALHA, 'erro' => $arquivo['erro']]);

            return ['importacao' => null, 'erro' => $arquivo['erro']];
        }

        return ['importacao' => $this->importar($fonte, $userId, $arquivo['caminho'], $automatica), 'erro' => null];
    }

    private function importar(PlanilhaFonte $fonte, ?int $userId, string $caminho, bool $automatica): ?PlanilhaImportacao
    {
        try {
            $hash = hash_file('sha256', $caminho);

            if ($automatica && $hash === $fonte->arquivo_hash) {
                $fonte->update(['verificada_em' => now(), 'status' => PlanilhaImportacao::SEM_ALTERACOES, 'erro' => null]);

                return null;
            }

            $importacao = $this->importador->importar($fonte->tenant_id, $userId, $caminho, 'Planilha do OneDrive');
            $rejeitada  = $importacao->status === PlanilhaImportacao::REJEITADA;

            $fonte->update([
                'verificada_em' => now(),
                'status'        => $importacao->status,
                'erro'          => $rejeitada ? $this->resumoDosErros($importacao) : null,
                'arquivo_hash'  => $rejeitada ? $fonte->arquivo_hash : $hash,
            ]);

            return $importacao;
        } finally {
            @unlink($caminho);
        }
    }

    /**
     * Lê a planilha como um visitante anônimo do link, do mesmo jeito que o
     * navegador faz ao abrir um link compartilhado do OneDrive pessoal:
     *   1. pede um token de visitante;
     *   2. consulta o item do link, que devolve o endereço de download;
     *   3. baixa o arquivo.
     * O link colado nunca é acessado diretamente — só vai codificado na consulta.
     *
     * @return array{caminho: string}|array{erro: string}
     */
    private function baixar(string $url): array
    {
        $codificado = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        try {
            $token = Http::timeout(20)->asJson()->post(self::URL_TOKEN, ['appId' => self::APP_ID_VISITANTE])->json('token');
            if (! $token) {
                return ['erro' => 'O OneDrive não liberou o acesso de visitante agora. Tente de novo em alguns minutos.'];
            }

            $item = Http::timeout(30)
                ->withHeaders(['Authorization' => 'Badger ' . $token, 'Prefer' => 'autoredeem'])
                ->get(self::URL_ITEM . "u!{$codificado}/driveitem");

            if (! $item->successful()) {
                return ['erro' => 'O OneDrive recusou o link (código ' . $item->status() . '). Confira se o compartilhamento continua ativo para "qualquer pessoa com o link".'];
            }

            // A chave tem ponto: não dá para ler com json('a.b').
            $download = (string) (($item->json() ?? [])['@content.downloadUrl'] ?? '');
            if (! $this->enderecoDeDownloadConfiavel($download)) {
                return ['erro' => 'O link não devolveu uma planilha Excel. Compartilhe o arquivo da planilha, não a pasta.'];
            }
            if ((int) $item->json('size') > self::TAMANHO_MAXIMO) {
                return ['erro' => 'O arquivo do link tem mais de 5 MB.'];
            }

            $resposta = Http::timeout(60)->get($download);
        } catch (ConnectionException) {
            return ['erro' => 'Não foi possível falar com o OneDrive agora. Tente de novo em alguns minutos.'];
        }

        if (! $resposta->successful()) {
            return ['erro' => 'O OneDrive não entregou o arquivo (código ' . $resposta->status() . '). Tente de novo em alguns minutos.'];
        }

        $conteudo = $resposta->body();
        if (strlen($conteudo) > self::TAMANHO_MAXIMO) {
            return ['erro' => 'O arquivo do link tem mais de 5 MB.'];
        }
        // Todo .xlsx é um zip: começa com "PK".
        if (! str_starts_with($conteudo, 'PK')) {
            return ['erro' => 'O link não devolveu uma planilha Excel. Compartilhe o arquivo da planilha, não a pasta.'];
        }

        $caminho = tempnam(sys_get_temp_dir(), 'planilha-onedrive');
        file_put_contents($caminho, $conteudo);

        return ['caminho' => $caminho];
    }

    /** O endereço de download vem do OneDrive, mas só é seguido se for dele mesmo. */
    private function enderecoDeDownloadConfiavel(string $url): bool
    {
        $partes = parse_url($url);
        $host   = strtolower($partes['host'] ?? '');

        if (($partes['scheme'] ?? '') !== 'https') {
            return false;
        }

        foreach (self::HOSTS_DE_DOWNLOAD as $sufixo) {
            if ($host === ltrim($sufixo, '.') || str_ends_with($host, $sufixo)) {
                return true;
            }
        }

        return false;
    }

    private function resumoDosErros(PlanilhaImportacao $importacao): string
    {
        $primeiro = $importacao->erros[0] ?? null;
        if (! $primeiro) {
            return 'A planilha foi rejeitada.';
        }

        $onde  = $primeiro['aba'] ? $primeiro['aba'] . ($primeiro['linha'] ? ', linha ' . $primeiro['linha'] : '') . ': ' : '';
        $mais  = count($importacao->erros) - 1;

        return $onde . $primeiro['mensagem'] . ($mais > 0 ? " (e mais {$mais})" : '');
    }
}
