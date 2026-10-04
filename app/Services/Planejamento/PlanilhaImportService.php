<?php

namespace App\Services\Planejamento;

use App\Models\Categoria;
use App\Models\PlanCartao;
use App\Models\PlanCompraParcelada;
use App\Models\PlanContaFixa;
use App\Models\PlanDivida;
use App\Models\PlanilhaImportacao;
use App\Models\PlanLancamento;
use App\Models\PlanMeta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deixa as tabelas plan_* do tenant iguais à planilha enviada: inclui o que é
 * novo, atualiza o que mudou, remove o que saiu e não toca no que está igual.
 * Ou o arquivo inteiro entra, ou nada muda.
 */
class PlanilhaImportService
{
    /** Ordem importa: cartões antes das parceladas, que apontam para eles. */
    private const MODELOS = [
        'cartoes'      => PlanCartao::class,
        'lancamentos'  => PlanLancamento::class,
        'contas_fixas' => PlanContaFixa::class,
        'parceladas'   => PlanCompraParcelada::class,
        'dividas'      => PlanDivida::class,
        'metas'        => PlanMeta::class,
    ];

    public function __construct(
        private XlsxReader $reader,
        private PlanilhaParser $parser,
    ) {
    }

    public function importar(int $tenantId, ?int $userId, string $caminho, string $nomeArquivo): PlanilhaImportacao
    {
        $registro = [
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'arquivo_nome' => Str::limit($nomeArquivo, 250, ''),
            'arquivo_hash' => is_file($caminho) ? hash_file('sha256', $caminho) : str_repeat('0', 64),
        ];

        try {
            $resultado = $this->parser->interpretar($this->reader->ler($caminho));
        } catch (PlanilhaInvalidaException $e) {
            $resultado = ['dados' => [], 'avisos' => [], 'erros' => [
                ['aba' => null, 'linha' => null, 'mensagem' => $e->getMessage()],
            ]];
        }

        if ($resultado['erros']) {
            return PlanilhaImportacao::create($registro + [
                'status' => PlanilhaImportacao::REJEITADA,
                'erros'  => $resultado['erros'],
            ]);
        }

        return DB::transaction(function () use ($tenantId, $userId, $registro, $resultado) {
            $importacao = PlanilhaImportacao::create($registro + ['status' => PlanilhaImportacao::SUCESSO]);
            $avisos     = $resultado['avisos'];
            $resumo     = [];

            $cartoes = [];
            foreach (self::MODELOS as $aba => $modelo) {
                $linhas = $resultado['dados'][$aba];

                if ($aba === 'lancamentos') {
                    $linhas = $this->vincularCategorias($tenantId, $userId, $linhas, $avisos);
                }
                if ($aba === 'parceladas') {
                    $linhas = $this->vincularCartoes($linhas, $cartoes, $avisos);
                }

                $resumo[$aba] = $this->sincronizar($modelo, $tenantId, $importacao->id, $linhas);

                if ($aba === 'cartoes') {
                    $cartoes = PlanCartao::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
                        ->mapWithKeys(fn ($c) => [$this->normalizar($c->nome) => $c->id])->all();
                }
            }

            // Relatório na ordem das abas da planilha.
            $ordenado = [];
            foreach (array_keys(PlanilhaParser::abas()) as $aba) {
                $ordenado[$aba] = $resumo[$aba];
            }

            $mudou = collect($ordenado)->sum(fn ($r) => $r['incluidas'] + $r['atualizadas'] + $r['removidas']) > 0;

            $importacao->update([
                'status' => $mudou ? PlanilhaImportacao::SUCESSO : PlanilhaImportacao::SEM_ALTERACOES,
                'resumo' => $ordenado,
                'avisos' => $avisos,
            ]);

            return $importacao;
        });
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @return array{incluidas: int, atualizadas: int, removidas: int, mantidas: int}
     */
    private function sincronizar(string $modelo, int $tenantId, int $importacaoId, array $linhas): array
    {
        $contagem   = ['incluidas' => 0, 'atualizadas' => 0, 'removidas' => 0, 'mantidas' => 0];
        $existentes = $modelo::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()->keyBy('chave');

        foreach ($linhas as $linha) {
            $atual = $existentes->pull($linha['chave']);

            if (! $atual) {
                $modelo::create($linha + ['tenant_id' => $tenantId, 'importacao_id' => $importacaoId]);
                $contagem['incluidas']++;
                continue;
            }

            $atual->fill($linha);
            // Mudar só de linha na planilha não é alteração de conteúdo.
            if ($atual->isDirty(array_diff(array_keys($linha), ['linha']))) {
                $atual->importacao_id = $importacaoId;
                $atual->save();
                $contagem['atualizadas']++;
            } else {
                if ($atual->isDirty('linha')) {
                    $atual->saveQuietly();
                }
                $contagem['mantidas']++;
            }
        }

        // O que sobrou não está mais na planilha.
        foreach ($existentes as $sobra) {
            $sobra->delete();
            $contagem['removidas']++;
        }

        return $contagem;
    }

    /** Liga cada lançamento à categoria do sistema de mesmo nome e tipo, criando as que faltam. */
    private function vincularCategorias(int $tenantId, ?int $userId, array $linhas, array &$avisos): array
    {
        $existentes = Categoria::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
            ->mapWithKeys(fn ($c) => [$c->tipo . '|' . $this->normalizar($c->nome) => $c->id])->all();

        $dono = $userId ?? User::where('tenant_id', $tenantId)->where('role', 'master')->orderBy('id')->value('id');

        foreach ($linhas as &$linha) {
            $linha['categoria_id'] = null;
            if ($linha['categoria'] === null) {
                continue;
            }

            $tipo  = strtoupper($linha['tipo']);
            $chave = $tipo . '|' . $this->normalizar($linha['categoria']);

            if (! isset($existentes[$chave]) && $dono) {
                $existentes[$chave] = Categoria::create([
                    'tenant_id' => $tenantId,
                    'user_id'   => $dono,
                    'nome'      => $linha['categoria'],
                    'tipo'      => $tipo,
                ])->id;

                $avisos[] = [
                    'aba'      => 'Lançamentos',
                    'linha'    => $linha['linha'],
                    'mensagem' => "Categoria \"{$linha['categoria']}\" (" . Str::lower($tipo) . ') não existia e foi criada.',
                ];
            }

            $linha['categoria_id'] = $existentes[$chave] ?? null;
        }

        return $linhas;
    }

    private function vincularCartoes(array $linhas, array $cartoes, array &$avisos): array
    {
        foreach ($linhas as &$linha) {
            $linha['plan_cartao_id'] = null;
            if ($linha['cartao'] === null) {
                continue;
            }

            $linha['plan_cartao_id'] = $cartoes[$this->normalizar($linha['cartao'])] ?? null;

            if ($linha['plan_cartao_id'] === null) {
                $avisos[] = [
                    'aba'      => 'Compras Parceladas',
                    'linha'    => $linha['linha'],
                    'mensagem' => "O cartão \"{$linha['cartao']}\" não está na aba Cartões.",
                ];
            }
        }

        return $linhas;
    }

    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($texto))));
    }
}
