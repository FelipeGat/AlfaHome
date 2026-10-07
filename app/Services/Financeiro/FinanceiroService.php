<?php

namespace App\Services\Financeiro;

use App\Models\Banco;
use App\Models\SaldoAjuste;
use App\Models\Transferencia;
use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fonte única dos números do dinheiro da família. Web, API do app e avisos do
 * Telegram leem daqui; nenhuma tela soma por conta própria.
 *
 * Regras:
 * - Saldo da conta = último ajuste (saldo real informado numa data) + entradas
 *   realizadas − saídas realizadas com data depois do ajuste e até hoje, da
 *   planilha (casadas pelo nome da conta) e do sistema (pela conta escolhida),
 *   ± transferências entre contas próprias.
 * - Realizado = lançamento com valor realizado preenchido; o resto é pendente.
 * - Entrou/Saiu no mês = realizado do mês, o mesmo do Previsto x Realizado.
 * - Transferência entre contas próprias não é entrada nem saída.
 * - Compra no cartão é saída uma vez (na data em que a planilha a registra);
 *   limite de cartão nunca entra em saldo.
 * Somas em centavos inteiros, para não perder centavo por arredondamento.
 */
class FinanceiroService
{
    public function __construct(private PlanejamentoService $planejamento) {}

    // ─── Contas ──────────────────────────────────────────────────────────────

    /**
     * Contas com dinheiro (corrente, poupança, carteira) e o saldo calculado.
     *
     * @return array{total: float, itens: list<array>, sem_conta: list<array{conta: string, movimentos: int}>}
     */
    public function contas(int $tenantId, ?CarbonInterface $hoje = null): array
    {
        $hoje   = ($hoje ?? now())->copy()->endOfDay();
        $bancos = $this->bancosComDinheiro($tenantId);
        $ajustes = $this->ultimosAjustes($tenantId, $hoje);

        $desde = collect($ajustes)->min(fn ($a) => $a->data) ?? $hoje->copy()->startOfDay();
        $movimentos = $this->planejamento->movimentos($tenantId, Carbon::parse($desde)->startOfDay(), $hoje)
            ->filter(fn ($m) => $m['valor_realizado'] !== null && $m['realizado_em'] && $m['realizado_em']->lte($hoje));
        $transferencias = Transferencia::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->whereDate('data', '>=', $desde)->whereDate('data', '<=', $hoje)->get();

        $porNome = $bancos->keyBy(fn ($b) => $this->chave($b->nome));
        $itens = $bancos->map(function (Banco $b) use ($ajustes, $movimentos, $transferencias, $porNome) {
            $ajuste = $ajustes[$b->id] ?? null;
            // Conta depois do ajuste: data posterior à dele, ou a mesma data mas
            // registrado depois dele (o saldo informado ainda não o incluía).
            $depois = fn ($data, $registradoEm) => $ajuste === null
                || Carbon::parse($data)->startOfDay()->gt($ajuste->data)
                || (Carbon::parse($data)->isSameDay($ajuste->data) && $registradoEm && Carbon::parse($registradoEm)->gte($ajuste->created_at));

            $centavos = $this->centavos($ajuste?->saldo ?? 0);
            foreach ($movimentos as $m) {
                $daConta = $m['banco_id'] !== null ? $m['banco_id'] === $b->id : ($m['conta'] && $porNome->get($this->chave($m['conta']))?->id === $b->id);
                if ($daConta && $depois($m['realizado_em'], $m['registrado_em'] ?? null)) {
                    $centavos += ($m['tipo'] === 'receita' ? 1 : -1) * $this->centavos($m['valor_realizado']);
                }
            }
            foreach ($transferencias as $t) {
                if ($depois($t->data, $t->created_at)) {
                    if ($t->origem_id === $b->id) {
                        $centavos -= $this->centavos($t->valor);
                    }
                    if ($t->destino_id === $b->id) {
                        $centavos += $this->centavos($t->valor);
                    }
                }
            }

            return [
                'id'             => $b->id,
                'nome'           => $b->nome,
                'cor'            => $b->cor,
                'logo'           => $b->logo,
                'saldo'          => $this->reais($centavos + $this->centavos($b->saldo_poupanca)),
                'saldo_corrente' => $this->reais($centavos),
                'tem_cartao'     => (bool) $b->tem_cartao_credito,
                'ajuste'         => $ajuste ? ['data' => $ajuste->data->format('Y-m-d'), 'saldo' => (float) $ajuste->saldo] : null,
            ];
        })->values();

        // Conta da planilha sem cadastro (ex.: "Ticket"). Cartão cadastrado sem
        // conta corrente (ex.: Celebre) não entra: é compra, não dinheiro em conta.
        $cadastradas = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('nome')->map(fn ($n) => $this->chave($n))->all();
        $semConta = $movimentos
            ->filter(fn ($m) => $m['banco_id'] === null && $m['conta'] && ! in_array($this->chave($m['conta']), $cadastradas, true))
            ->groupBy('conta')
            ->map(fn ($g, $conta) => ['conta' => $conta, 'movimentos' => $g->count()])
            ->values()->all();

        return [
            'total'     => $this->reais($itens->sum(fn ($i) => $this->centavos($i['saldo']))),
            'itens'     => $itens->all(),
            'sem_conta' => $semConta,
        ];
    }

    /** Regrava `bancos.saldo` com o saldo calculado: as telas antigas que leem a coluna mostram o mesmo número. */
    public function recalcular(int $tenantId): void
    {
        foreach ($this->contas($tenantId)['itens'] as $c) {
            Banco::withoutGlobalScopes()->whereKey($c['id'])->update(['saldo' => $c['saldo_corrente']]);
        }
    }

    /** "Ajustar saldo": registra o saldo real da conta na data e recalcula. */
    public function ajustar(Banco $banco, float $saldoReal, ?CarbonInterface $data = null, ?int $userId = null, ?string $observacao = null): SaldoAjuste
    {
        $data  = ($data ?? now())->copy()->startOfDay();
        $atual = collect($this->contas($banco->tenant_id, $data)['itens'])->firstWhere('id', $banco->id);
        $calculado = (float) ($atual['saldo_corrente'] ?? 0);

        $ajuste = SaldoAjuste::withoutGlobalScopes()->create([
            'tenant_id'  => $banco->tenant_id,
            'banco_id'   => $banco->id,
            'user_id'    => $userId,
            'data'       => $data->toDateString(),
            'saldo'      => round($saldoReal, 2),
            'diferenca'  => $this->reais($this->centavos($saldoReal) - $this->centavos($calculado)),
            'observacao' => $observacao,
        ]);
        $this->recalcular($banco->tenant_id);

        return $ajuste;
    }

    // ─── Mês, últimas movimentações e próximos pagamentos ───────────────────

    /** Entrou, saiu, resultado e onde gastou — o realizado do mês. */
    public function mes(int $tenantId, CarbonInterface $mes): array
    {
        $r = $this->planejamento->resumoMes($tenantId, $mes, completo: false);
        $entrou = $this->centavos($r['receitas']['realizado']);
        $saiu   = $this->centavos($r['despesas']['realizado']);

        return [
            'mes'               => $r['mes'],
            'entrou'            => $this->reais($entrou),
            'saiu'              => $this->reais($saiu),
            'resultado'         => $this->reais($entrou - $saiu),
            'tem_movimentacao'  => $entrou !== 0 || $saiu !== 0,
            'categorias'        => array_map(fn ($c) => ['categoria' => $c['categoria'], 'valor' => $c['realizado'], 'pct' => $c['pct']], $r['por_categoria']),
        ];
    }

    /** As últimas movimentações realizadas até hoje, mais recentes primeiro. */
    public function ultimas(int $tenantId, int $quantidade = 5, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ?? now())->copy()->endOfDay();

        return $this->planejamento->movimentos($tenantId, $hoje->copy()->subDays(60)->startOfDay(), $hoje)
            ->filter(fn ($m) => $m['valor_realizado'] !== null && $m['realizado_em'] && $m['realizado_em']->lte($hoje))
            ->sortBy([['realizado_em', 'desc'], ['ordem', 'desc']])
            ->take($quantidade)
            ->map(fn ($m) => $this->movimento($m))
            ->values()->all();
    }

    /** Próximos pagamentos (atrasados primeiro), no máximo `quantidade`. */
    public function proximosPagamentos(int $tenantId, int $quantidade = 3, ?CarbonInterface $hoje = null, ?array $v = null): array
    {
        $v ??= $this->planejamento->vencimentos($tenantId, $hoje);
        $atrasados = array_values(array_filter($v['atrasado'], fn ($i) => $i['tipo'] !== 'receita'));

        return array_map(
            fn ($i) => ['descricao' => $i['descricao'], 'valor' => $i['valor'], 'data' => $i['data'], 'atrasado' => in_array($i, $atrasados, true), 'pago' => $i['pago'] ?? false, 'detalhe' => $i['detalhe']],
            array_slice(array_merge($atrasados, $v['a_pagar']), 0, $quantidade)
        );
    }

    /** Tudo o que o Início mostra, numa chamada. */
    public function inicio(int $tenantId, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ?? now())->copy();
        // Vencimentos e cartões calculados uma vez só para a tela inteira.
        $v        = $this->planejamento->vencimentos($tenantId, $hoje);
        $vencidas = array_values(array_filter($v['atrasado'], fn ($i) => $i['tipo'] !== 'receita'));
        $cartoes  = $this->planejamento->cartoes($tenantId);

        return [
            'data'               => $hoje->format('Y-m-d'),
            'contas'             => $this->contas($tenantId, $hoje),
            'mes'                => $this->mes($tenantId, $hoje),
            'ultimas'            => $this->ultimas($tenantId, 5, $hoje),
            'proximos'           => $this->proximosPagamentos($tenantId, 3, $hoje, $v),
            'vencidas'           => ['quantidade' => count($vencidas), 'total' => $this->reais(array_sum(array_map(fn ($i) => $this->centavos($i['valor']), $vencidas)))],
            'cartoes'            => $this->cartoesDoInicio($cartoes, $v),
            'planilha_importada' => $this->planejamento->ultimaImportacao($tenantId) !== null,
            'tem_conta'          => Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists(),
        ];
    }

    /**
     * Cartões da planilha para o Início: limite, usado, livre e a fatura com o
     * próximo vencimento (e se as compras dela já estão pagas). Limite nunca é saldo.
     */
    private function cartoesDoInicio(array $cartoes, array $vencimentos): array
    {
        $faturas = collect($vencimentos['a_pagar'])->where('origem', 'fatura')->keyBy('descricao');
        $itens = $cartoes['itens']->map(function ($c) use ($faturas) {
            $fatura = $faturas->get('Fatura ' . $c->nome);

            return [
                'chave'       => $c->chave,
                'banco_id'    => $c->banco_id,
                'nome'        => $c->nome,
                'limite'      => $c->limite_total !== null ? (float) $c->limite_total : null,
                'utilizado'   => $c->limite_utilizado !== null ? (float) $c->limite_utilizado : null,
                'disponivel'  => $c->limite_disponivel,
                'pct'         => $c->utilizado_pct,
                'fatura'      => $c->fatura_atual !== null ? (float) $c->fatura_atual : null,
                'vencimento'  => $fatura['data'] ?? null,
                'fatura_paga' => $fatura['pago'] ?? false,
            ];
        })->values()->all();

        return [
            'quantidade'        => count($itens),
            'limite_total'      => $cartoes['resumo']['limite_total'],
            'limite_utilizado'  => $cartoes['resumo']['limite_utilizado'],
            'limite_disponivel' => $cartoes['resumo']['limite_disponivel'],
            'utilizado_pct'     => $cartoes['resumo']['utilizado_pct'],
            'itens'             => $itens,
        ];
    }

    /** Movimentações realizadas de uma conta (planilha e sistema), mais recentes primeiro. */
    public function movimentosDaConta(Banco $banco, int $dias = 60, ?CarbonInterface $hoje = null): array
    {
        $hoje  = ($hoje ?? now())->copy()->endOfDay();
        $chave = $this->chave($banco->nome);

        $desde = $hoje->copy()->subDays($dias)->startOfDay();
        // Transferência aparece no extrato da conta como entrada ou saída dela.
        $transferencias = Transferencia::withoutGlobalScope('tenant')->where('tenant_id', $banco->tenant_id)
            ->where(fn ($q) => $q->where('origem_id', $banco->id)->orWhere('destino_id', $banco->id))
            ->whereBetween('data', [$desde->toDateString(), $hoje->toDateString()])->with(['origem', 'destino'])->get()
            ->map(fn ($t) => [
                'ref'             => 'transferencia:' . $t->id,
                'ordem'           => 300000 + $t->id,
                'tipo'            => $t->destino_id === $banco->id ? 'receita' : 'despesa',
                'descricao'       => $t->destino_id === $banco->id ? 'Transferência de ' . ($t->origem?->nome ?? 'outra conta') : 'Transferência para ' . ($t->destino?->nome ?? 'outra conta'),
                'categoria'       => 'Transferência',
                'conta'           => $banco->nome,
                'realizado_em'    => $t->data,
                'valor_realizado' => (float) $t->valor,
            ]);

        return $this->planejamento->movimentos($banco->tenant_id, $desde, $hoje)
            ->filter(fn ($m) => $m['valor_realizado'] !== null && $m['realizado_em'] && $m['realizado_em']->lte($hoje)
                && ($m['banco_id'] !== null ? $m['banco_id'] === $banco->id : $m['conta'] && $this->chave($m['conta']) === $chave))
            ->concat($transferencias)
            ->sortBy([['realizado_em', 'desc'], ['ordem', 'desc']])
            ->map(fn ($m) => $this->movimento($m))
            ->values()->all();
    }

    // ─── Apoio ───────────────────────────────────────────────────────────────

    private function movimento(array $m): array
    {
        return [
            'ref'       => $m['ref'],
            'tipo'      => $m['tipo'],
            'descricao' => $m['descricao'],
            'categoria' => $m['categoria'],
            'conta'     => $m['conta'],
            'data'      => $m['realizado_em']->format('Y-m-d'),
            'valor'     => $this->reais($this->centavos($m['valor_realizado'])),
        ];
    }

    private function bancosComDinheiro(int $tenantId): Collection
    {
        return Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('tem_conta_corrente', true)->orWhere('tem_poupanca', true)->orWhere('eh_dinheiro', true))
            ->orderBy('nome')->get();
    }

    /** Último ajuste de cada conta até a data, indexado pelo id da conta. */
    private function ultimosAjustes(int $tenantId, CarbonInterface $ate): array
    {
        return SaldoAjuste::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereDate('data', '<=', $ate)
            ->orderBy('data')->orderBy('id')->get()
            ->keyBy('banco_id')->all();
    }

    private function chave(?string $nome): string
    {
        return Str::lower(Str::ascii(trim((string) $nome)));
    }

    private function centavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private function reais(int $centavos): float
    {
        return round($centavos / 100, 2);
    }
}
