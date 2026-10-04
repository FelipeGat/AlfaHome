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
     * @return array{caminho: string}|array{erro: string}
     */
    private function baixar(string $url): array
    {
        // Nunca se acessa o link colado: só a API do OneDrive, com o link codificado.
        $codificado = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        $endpoint   = "https://api.onedrive.com/v1.0/shares/u!{$codificado}/root/content";

        try {
            $resposta = Http::timeout(30)->withOptions(['allow_redirects' => ['max' => 5]])->get($endpoint);
        } catch (ConnectionException) {
            return ['erro' => 'Não foi possível falar com o OneDrive agora. Tente de novo em alguns minutos.'];
        }

        if (! $resposta->successful()) {
            return ['erro' => 'O OneDrive recusou o link (código ' . $resposta->status() . '). Confira se o compartilhamento continua ativo para "qualquer pessoa com o link".'];
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
