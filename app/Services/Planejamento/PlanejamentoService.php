<?php

namespace App\Services\Planejamento;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\PlanCartao;
use App\Models\PlanCompraParcelada;
use App\Models\PlanContaFixa;
use App\Models\PlanDivida;
use App\Models\PlanilhaImportacao;
use App\Models\PlanLancamento;
use App\Models\PlanMeta;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Services\Financeiro\FinanceiroService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Única definição dos números do planejamento. Telas web, API e painel inicial
 * chamam este serviço — nenhuma delas recalcula por conta própria.
 *
 * Os lançamentos do mês são os da planilha (plan_lancamentos) somados às
 * despesas e receitas lançadas à mão no sistema.
 */
class PlanejamentoService
{
    // ─── Mês e ano ────────────────────────────────────────────────────────────

    /** `completo: false` dispensa cartões, dívidas e metas (o Início não usa). */
    public function resumoMes(int $tenantId, CarbonInterface $mes, bool $completo = true): array
    {
        $inicio = $mes->copy()->startOfMonth();
        $fim    = $mes->copy()->endOfMonth();

        $movimentos = $this->movimentos($tenantId, $inicio, $fim);
        $totais     = $this->totais($movimentos, $inicio, $fim);

        $porCategoria = $movimentos
            ->filter(fn ($m) => $m['tipo'] === 'despesa' && $m['realizado_em'] && $m['realizado_em']->between($inicio, $fim))
            ->groupBy(fn ($m) => $m['categoria'] ?? 'Sem categoria')
            ->map(fn ($grupo, $categoria) => [
                'categoria' => $categoria,
                'realizado' => round($grupo->sum('valor_realizado'), 2),
            ])
            ->filter(fn ($c) => $c['realizado'] != 0)
            ->sortByDesc('realizado')
            ->values()
            ->map(fn ($c) => $c + ['pct' => $this->pct($c['realizado'], $totais['despesas']['realizado'])])
            ->all();

        $base = ['mes' => $inicio->format('Y-m')] + $totais + ['por_categoria' => $porCategoria];
        if (! $completo) {
            return $base;
        }

        return $base + [
            'cartoes'           => $this->cartoes($tenantId)['resumo'],
            'dividas'           => $this->dividas($tenantId)['resumo'],
            'metas'             => $this->metas($tenantId)['resumo'],
            'ultima_importacao' => $this->ultimaImportacao($tenantId),
        ];
    }

    public function anual(int $tenantId, int $ano): array
    {
        $inicio = Carbon::create($ano, 1, 1)->startOfDay();
        $fim    = Carbon::create($ano, 12, 31)->endOfDay();

        $movimentos = $this->movimentos($tenantId, $inicio, $fim);

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $de = Carbon::create($ano, $m, 1)->startOfMonth();
            $meses[] = ['mes' => $de->format('Y-m')] + $this->totais($movimentos, $de, $de->copy()->endOfMonth());
        }

        return ['ano' => $ano, 'meses' => $meses, 'total' => $this->totais($movimentos, $inicio, $fim)];
    }

    /**
     * Lançamentos do mês (planilha + manuais), por data. Com `comTransferencias`,
     * as transferências entre contas próprias entram também (tipo
     * "transferencia", que não é entrada nem saída).
     */
    public function lancamentos(int $tenantId, CarbonInterface $mes, ?string $tipo = null, bool $comTransferencias = false): array
    {
        $inicio = $mes->copy()->startOfMonth();
        $fim    = $mes->copy()->endOfMonth();

        $movimentos = $this->movimentos($tenantId, $inicio, $fim);
        if ($comTransferencias) {
            $movimentos = $movimentos->concat($this->transferencias($tenantId, $inicio, $fim));
        }

        return $movimentos
            ->filter(fn ($m) => $m['data']->between($inicio, $fim))
            ->when($tipo, fn ($c) => $c->where('tipo', $tipo))
            ->sortBy([['data', 'asc'], ['ordem', 'asc']])
            ->values()
            ->map(function ($m) {
                $previsto  = $m['valor_previsto'];
                $realizado = $m['realizado_em'] ? $m['valor_realizado'] : null;

                return [
                    'ref'             => $m['ref'],
                    'origem'          => $m['origem'],
                    'editavel'        => $m['origem'] !== 'planilha',
                    'data'            => $m['data']->format('Y-m-d'),
                    'tipo'            => $m['tipo'],
                    'descricao'       => $m['descricao'],
                    'categoria'       => $m['categoria'],
                    'forma'           => $m['forma'],
                    'conta'           => $m['conta'],
                    'valor_previsto'  => $previsto,
                    'valor_realizado' => $realizado,
                    'diferenca'       => $previsto !== null && $realizado !== null ? round($realizado - $previsto, 2) : null,
                    'status'          => $m['status'],
                    'observacao'      => $m['observacao'],
                ] + (isset($m['origem_id']) ? ['origem_id' => $m['origem_id'], 'destino_id' => $m['destino_id']] : []);
            })
            ->all();
    }

    /** Transferências entre contas próprias no período, no formato de movimento. */
    private function transferencias(int $tenantId, CarbonInterface $inicio, CarbonInterface $fim): Collection
    {
        return Transferencia::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->whereBetween('data', [$inicio->format('Y-m-d'), $fim->format('Y-m-d')])
            ->with(['origem', 'destino'])->get()
            ->map(fn ($t) => [
                'ref'             => 'transferencia:' . $t->id,
                'origem'          => 'manual',
                'ordem'           => 300000 + $t->id,
                'tipo'            => 'transferencia',
                'data'            => $t->data,
                'realizado_em'    => $t->data,
                'descricao'       => $t->observacao ?: 'Transferência',
                'categoria'       => null,
                'forma'           => 'transferencia',
                'conta'           => ($t->origem?->nome ?? '?') . ' → ' . ($t->destino?->nome ?? '?'),
                'banco_id'        => null,
                'registrado_em'   => $t->created_at,
                'valor_previsto'  => (float) $t->valor,
                'valor_realizado' => (float) $t->valor,
                'status'          => 'concluido',
                'observacao'      => $t->observacao,
                'origem_id'       => $t->origem_id,
                'destino_id'      => $t->destino_id,
            ]);
    }

    /**
     * Mês que a tela abre quando ninguém escolheu um: o corrente, se já tem
     * lançamento da planilha; senão o mais recente que tiver.
     */
    public function mesPadrao(int $tenantId): Carbon
    {
        $atual = now()->startOfMonth();
        $datas = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ((clone $datas)->whereBetween('data', [$atual->format('Y-m-d'), $atual->copy()->endOfMonth()->format('Y-m-d')])->exists()) {
            return $atual;
        }

        $ultima = $datas->max('data');

        return $ultima ? Carbon::parse($ultima)->startOfMonth() : $atual;
    }

    // ─── Abas de apoio ────────────────────────────────────────────────────────

    /** @return array{itens: Collection, resumo: array} */
    public function cartoes(int $tenantId): array
    {
        $planilha = PlanCartao::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('linha')->get();
        $hoje     = now()->startOfDay();
        foreach ($planilha as $c) {
            // A fatura é a soma das compras da aba Lançamentos; o valor digitado
            // na aba Cartões fica só para quando não há compra lançada.
            $c->setAttribute('fatura_planilha', $c->fatura_atual);
            if ($c->dia_vencimento && ($soma = $this->faturaPelasCompras($tenantId, $c, $this->proximoDia($hoje, (int) $c->dia_vencimento))) !== null) {
                $c->fatura_atual = $soma;
            }
        }
        $itens = $planilha->concat($this->cartoesDoSistema($tenantId, $planilha))->values();

        // Como na planilha: só entra na conta o cartão com limite informado.
        $comLimite = $itens->filter(fn ($c) => $c->limite_total !== null);
        $total     = round($comLimite->sum(fn ($c) => (float) $c->limite_total), 2);
        $utilizado = round($comLimite->sum(fn ($c) => (float) $c->limite_utilizado), 2);

        return ['itens' => $itens, 'resumo' => [
            'limite_total'      => $total,
            'limite_utilizado'  => $utilizado,
            'limite_disponivel' => round($total - $utilizado, 2),
            'faturas_abertas'   => round($itens
                ->filter(fn ($c) => $this->igual($c->status_fatura, 'aberta'))
                ->sum(fn ($c) => (float) $c->fatura_atual), 2),
            'utilizado_pct'     => $this->pct($utilizado, $total),
        ]];
    }

    /** @return array{itens: Collection, resumo: array} */
    public function parceladas(int $tenantId): array
    {
        $itens = PlanCompraParcelada::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('linha')->get();

        $abertas = $itens->filter(fn ($p) => (int) $p->parcelas_restantes > 0);

        return ['itens' => $itens, 'resumo' => [
            'saldo_total'          => round($itens->sum(fn ($p) => (float) $p->saldo), 2),
            'parcela_mensal_total' => round($abertas->sum(fn ($p) => (float) $p->valor_parcela), 2),
        ]];
    }

    /** @return array{itens: Collection, resumo: array} */
    public function dividas(int $tenantId): array
    {
        $itens = PlanDivida::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('linha')->get();

        return ['itens' => $itens, 'resumo' => [
            'saldo_total'          => round($itens->sum(fn ($d) => (float) $d->saldo_atual), 2),
            'parcela_mensal_total' => round($itens->sum(fn ($d) => (float) $d->parcela_mensal), 2),
            'quantidade'           => $itens->count(),
        ]];
    }

    /** @return array{itens: Collection, resumo: array} */
    public function metas(int $tenantId): array
    {
        $itens = PlanMeta::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('linha')->get();

        return ['itens' => $itens, 'resumo' => [
            'progresso_medio_pct' => $itens->isEmpty() ? 0.0 : round($itens->avg(fn ($m) => $m->concluido_pct), 2),
            'quantidade'          => $itens->count(),
        ]];
    }

    /** @return array{itens: Collection, resumo: array} */
    public function contasFixas(int $tenantId): array
    {
        $itens = PlanContaFixa::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->orderByRaw('dia_vencimento is null')->orderBy('dia_vencimento')->orderBy('linha')->get();

        return ['itens' => $itens, 'resumo' => [
            'previsto_total'  => round($itens->sum(fn ($c) => (float) $c->valor_previsto), 2),
            'realizado_total' => round($itens->sum(fn ($c) => (float) $c->valor_realizado), 2),
        ]];
    }

    /**
     * O que a planilha diz que está atrasado, a pagar e a receber — é o que a
     * tela de Alertas mostra.
     *
     * - Lançamento e conta fixa pendentes: atrasados se a data já passou.
     * - Fatura aberta e parcela de dívida ativa: a planilha não diz se a do mês
     *   já foi paga, então entram sempre como "a pagar" no próximo dia de
     *   vencimento, nunca como atrasadas.
     * - Compra no cartão (lançamento pendente com forma "cartão" e compra
     *   parcelada) não entra: é paga dentro da fatura, que já está na lista.
     *
     * @return array{atrasado: array, a_pagar: array, a_receber: array, totais: array}
     */
    public function vencimentos(int $tenantId, ?CarbonInterface $hoje = null): array
    {
        $hoje   = ($hoje ?? now())->copy()->startOfDay();
        $grupos = ['atrasado' => [], 'a_pagar' => [], 'a_receber' => []];

        $item = fn (string $origem, string $tipo, string $descricao, $valor, ?CarbonInterface $data, ?string $detalhe = null, bool $pago = false) => [
            'origem'    => $origem,
            'tipo'      => $tipo,
            'descricao' => $descricao,
            'valor'     => $valor !== null ? round((float) $valor, 2) : null,
            'data'      => $data?->format('Y-m-d'),
            'detalhe'   => $detalhe,
            'pago'      => $pago,
        ];

        $pendentes = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('status', 'pendente')
            ->where(fn ($q) => $q->whereNull('forma')->orWhere('forma', '!=', 'cartao'))
            ->orderBy('data')->orderBy('linha')->get();
        foreach ($pendentes as $l) {
            $grupo = $l->data->lt($hoje) ? 'atrasado' : ($l->tipo === 'receita' ? 'a_receber' : 'a_pagar');
            $grupos[$grupo][] = $item('lancamento', $l->tipo, $l->descricao, $l->valor_previsto, $l->data, $l->categoria);
        }

        $fixas = PlanContaFixa::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('status', 'pendente')->whereNotNull('dia_vencimento')->orderBy('dia_vencimento')->get();
        foreach ($fixas as $c) {
            $data = $this->diaNoMes($hoje, $c->dia_vencimento);
            $grupos[$data->lt($hoje) ? 'atrasado' : 'a_pagar'][] = $item('conta_fixa', 'despesa', $c->conta, $c->valor_previsto, $data, 'Conta fixa');
        }

        $cartoes = PlanCartao::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('dia_vencimento')->orderBy('linha')->get();
        foreach ($cartoes as $c) {
            $vence = $this->proximoDia($hoje, $c->dia_vencimento);
            $valor = $this->faturaPelasCompras($tenantId, $c, $vence) ?? (float) $c->fatura_atual;
            if ($this->igual($c->status_fatura, 'aberta') && $valor > 0) {
                $grupos['a_pagar'][] = $item('fatura', 'despesa', 'Fatura ' . $c->nome, $valor, $vence, 'Fatura de cartão', $this->faturaPaga($tenantId, $c, $vence));
            }
        }

        // Lançados no sistema e ainda não pagos/recebidos. Compra no cartão fica
        // de fora: é paga dentro da fatura. Futuro só até 45 dias (recorrência
        // sem fim gera anos de lançamentos).
        $limite = $hoje->copy()->addDays(45)->toDateString();
        $despesas = Despesa::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereNull('data_pagamento')
            ->where(fn ($q) => $q->whereNull('tipo_pagamento')->orWhere('tipo_pagamento', '!=', 'credito'))
            ->where('data_compra', '<=', $limite)->with(['categoria', 'fornecedor'])->orderBy('data_compra')->get();
        foreach ($despesas as $d) {
            $grupos[$d->data_compra->lt($hoje) ? 'atrasado' : 'a_pagar'][] = $item('manual', 'despesa', $this->descricaoDespesa($d), $d->valor, $d->data_compra, $d->categoria?->nome) + ['ref' => 'despesa:' . $d->id];
        }
        $receitas = Receita::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)->whereNull('data_recebimento')
            ->where('data_prevista_recebimento', '<=', $limite)->with('categoria')->orderBy('data_prevista_recebimento')->get();
        foreach ($receitas as $r) {
            $grupos[$r->data_prevista_recebimento->lt($hoje) ? 'atrasado' : 'a_receber'][] = $item('manual', 'receita', $r->observacoes ?? $r->categoria?->nome ?? 'Receita', $r->valor, $r->data_prevista_recebimento, $r->categoria?->nome) + ['ref' => 'receita:' . $r->id];
        }

        // Cartões do sistema: fatura atrasada (vencida com compra pendente) e a próxima.
        foreach ($this->cartoesDoSistema($tenantId, PlanCartao::withoutGlobalScopes()->where('tenant_id', $tenantId)->get()) as $c) {
            $banco = Banco::withoutGlobalScopes()->find($c->banco_id);
            $atrasadas = Despesa::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
                ->where('tipo_pagamento', 'credito')->where('forma_pagamento', $c->banco_id)->whereNull('data_pagamento')
                ->where('data_compra', '<', $hoje->toDateString())->get()
                ->groupBy(fn ($d) => $d->data_compra->format('Y-m'));
            foreach ($atrasadas as $mes => $compras) {
                $vence = $c->dia_vencimento ? $this->diaNoMes(Carbon::createFromFormat('!Y-m', $mes), (int) $c->dia_vencimento) : $compras->min('data_compra');
                $grupos['atrasado'][] = $item('fatura', 'despesa', 'Fatura ' . $c->nome, $compras->sum('valor'), $vence, 'Fatura de cartão') + ['banco_id' => $c->banco_id];
            }
            $proxima = $this->proximaFaturaDoSistema($banco, $hoje);
            if ($proxima) {
                $grupos['a_pagar'][] = $item('fatura', 'despesa', 'Fatura ' . $c->nome, $proxima['paga'] ? $proxima['total'] : $proxima['pendente'], Carbon::parse($proxima['vencimento']), 'Fatura de cartão', $proxima['paga']) + ['banco_id' => $c->banco_id];
            }
        }

        $dividas = PlanDivida::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('dia_vencimento')->orderBy('linha')->get();
        foreach ($dividas as $d) {
            if ($this->igual($d->status, 'ativa') && (float) $d->parcela_mensal > 0) {
                $grupos['a_pagar'][] = $item('divida', 'despesa', 'Parcela ' . $d->nome, $d->parcela_mensal, $this->proximoDia($hoje, $d->dia_vencimento), $d->credor);
            }
        }

        foreach ($grupos as &$itens) {
            usort($itens, fn ($a, $b) => [$a['data'] ?? '9999', $a['descricao']] <=> [$b['data'] ?? '9999', $b['descricao']]);
        }
        unset($itens);

        // Fatura já paga continua na lista até vencer, mas não soma no "a pagar".
        return $grupos + ['totais' => array_map(
            fn ($itens) => round(array_sum(array_column(array_filter($itens, fn ($i) => ! $i['pago']), 'valor')), 2),
            $grupos
        )];
    }

    /**
     * A planilha marca a fatura como "Aberta" na aba Cartões até alguém mudar;
     * o sinal mais confiável de pagamento são as compras daquela fatura (forma
     * cartão, mesma conta, lançadas perto do vencimento) estarem como Pago.
     */
    private function faturaPaga(int $tenantId, PlanCartao $cartao, CarbonInterface $vence): bool
    {
        $conta = $this->chaveConta($cartao->banco ?: $cartao->nome);
        $compras = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('forma', 'cartao')->where('tipo', 'despesa')
            ->whereBetween('data', [$vence->copy()->subDays(10)->toDateString(), $vence->copy()->addDays(10)->toDateString()])
            ->get()
            ->filter(fn ($l) => $this->chaveConta($l->conta) === $conta);

        if ($compras->isEmpty()) {
            return false;
        }
        // Todas pagas, ou as pagas já cobrem o valor da fatura (um encargo
        // pendente lançado perto, como juros, vai para a fatura seguinte).
        $pagas = $compras->where('status', 'concluido')
            ->sum(fn ($l) => (int) round((float) ($l->valor_realizado ?? $l->valor_previsto) * 100));

        return $compras->every(fn ($l) => $l->status === 'concluido')
            || ((float) $cartao->fatura_atual > 0 && $pagas >= (int) round((float) $cartao->fatura_atual * 100));
    }

    /**
     * Soma das compras da planilha (forma cartão, mesma conta) lançadas até 10
     * dias antes ou depois do vencimento — a mesma janela de `faturaPaga`. Nula
     * quando não há compra: aí vale o valor digitado na aba Cartões.
     */
    private function faturaPelasCompras(int $tenantId, PlanCartao $cartao, CarbonInterface $vence): ?float
    {
        $conta = $this->chaveConta($cartao->banco ?: $cartao->nome);
        $compras = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('forma', 'cartao')->where('tipo', 'despesa')
            ->whereBetween('data', [$vence->copy()->subDays(10)->toDateString(), $vence->copy()->addDays(10)->toDateString()])
            ->get()
            ->filter(fn ($l) => $this->chaveConta($l->conta) === $conta);

        if ($compras->isEmpty()) {
            return null;
        }

        return round($compras->sum(fn ($l) => (int) round((float) ($l->status === 'concluido' ? ($l->valor_realizado ?? $l->valor_previsto) : $l->valor_previsto) * 100)) / 100, 2);
    }

    /**
     * Faturas de um cartão: as compras da planilha (forma cartão, mesma conta)
     * agrupadas pelo mês em que caem, com total, pago e pendente. Mais recentes
     * primeiro; `meses_atras`/`meses_frente` limitam a janela.
     *
     * @return list<array{mes: string, vencimento: ?string, total: float, pago: float, pendente: float, paga: bool, itens: list<array>}>
     */
    public function faturasDoCartao(int $tenantId, PlanCartao $cartao, int $mesesAtras = 4, int $mesesFrente = 2, ?CarbonInterface $hoje = null): array
    {
        $hoje  = ($hoje ?? now())->copy()->startOfMonth();
        if ($cartao->banco_id) {
            return $this->faturasDoSistema($tenantId, (int) $cartao->banco_id, $cartao->dia_vencimento, $hoje->copy()->subMonths($mesesAtras), $hoje->copy()->addMonths($mesesFrente)->endOfMonth());
        }
        $conta = $this->chaveConta($cartao->banco ?: $cartao->nome);

        return PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('forma', 'cartao')->where('tipo', 'despesa')
            ->whereBetween('data', [$hoje->copy()->subMonths($mesesAtras)->toDateString(), $hoje->copy()->addMonths($mesesFrente)->endOfMonth()->toDateString()])
            ->orderBy('data')->orderBy('linha')->get()
            ->filter(fn ($l) => $this->chaveConta($l->conta) === $conta)
            ->groupBy(fn ($l) => $l->data->format('Y-m'))
            ->sortKeysDesc()
            ->map(function ($compras, $mes) use ($cartao) {
                $valor = fn ($l) => (int) round((float) ($l->status === 'concluido' ? ($l->valor_realizado ?? $l->valor_previsto) : $l->valor_previsto) * 100);
                $pago = $compras->where('status', 'concluido')->sum($valor);
                $pendente = $compras->where('status', '!=', 'concluido')->sum($valor);
                $vence = $cartao->dia_vencimento ? $this->diaNoMes(Carbon::createFromFormat('!Y-m', $mes), $cartao->dia_vencimento)->format('Y-m-d') : null;

                return [
                    'mes'        => $mes,
                    'vencimento' => $vence,
                    'total'      => round(($pago + $pendente) / 100, 2),
                    'pago'       => round($pago / 100, 2),
                    'pendente'   => round($pendente / 100, 2),
                    'paga'       => $pendente === 0,
                    'itens'      => $compras->map(fn ($l) => [
                        'data'      => $l->data->format('Y-m-d'),
                        'descricao' => $l->descricao,
                        'categoria' => $l->categoria,
                        'valor'     => round($valor($l) / 100, 2),
                        'pago'      => $l->status === 'concluido',
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    /**
     * Cartões cadastrados em Contas que a planilha não traz (família que lança
     * pelo sistema). Saem no mesmo formato dos da planilha, sem gravar nada:
     * limite = o cadastrado; usado = compras no crédito ainda não pagas (todas
     * as parcelas); fatura = compras do próximo vencimento.
     */
    private function cartoesDoSistema(int $tenantId, Collection $planilha): Collection
    {
        $daPlanilha = $planilha->map(fn ($c) => $this->chaveConta($c->banco ?: $c->nome))->all();
        $bancos = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('tem_cartao_credito', true)->orderBy('nome')->get()
            ->reject(fn ($b) => in_array($this->chaveConta($b->nome), $daPlanilha, true));
        if ($bancos->isEmpty()) {
            return collect();
        }

        $compras = Despesa::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where('tipo_pagamento', 'credito')->whereIn('forma_pagamento', $bancos->pluck('id'))
            ->get(['id', 'forma_pagamento', 'valor', 'data_compra', 'data_pagamento', 'recorrencia_sem_fim'])
            ->groupBy('forma_pagamento');
        $hoje = now()->startOfDay();

        return $bancos->map(function (Banco $b) use ($compras, $hoje) {
            $doCartao = $compras->get($b->id, collect());
            $fatura   = $this->proximaFaturaDoSistema($b, $hoje, $doCartao);
            // Parcelada prende o valor todo no limite; "todo mês" só até a
            // fatura atual (os meses seguintes ainda não foram cobrados).
            $ate   = $fatura ? Carbon::parse($fatura['vencimento'])->endOfMonth() : $hoje->copy()->endOfMonth();
            $usado = $doCartao->whereNull('data_pagamento')
                ->reject(fn ($d) => $d->recorrencia_sem_fim && $d->data_compra->gt($ate))
                ->sum(fn ($d) => (int) round((float) $d->valor * 100));
            $limite    = (float) $b->limite_cartao > 0 ? (float) $b->limite_cartao : null;

            $c = new PlanCartao([
                'tenant_id'        => $b->tenant_id,
                'chave'            => 'banco:' . $b->id,
                'linha'            => 100000 + $b->id,
                'nome'             => $b->nome,
                'banco'            => $b->nome,
                'limite_total'     => $limite,
                'limite_utilizado' => $limite !== null || $usado > 0 ? round($usado / 100, 2) : null,
                'dia_fechamento'   => $b->dia_fechamento_cartao,
                'dia_vencimento'   => $b->dia_vencimento_cartao,
                'fatura_atual'     => $fatura ? $fatura['total'] : null,
                'status_fatura'    => $fatura ? ($fatura['paga'] ? 'Paga' : 'Aberta') : null,
            ]);
            $c->setAttribute('banco_id', $b->id);
            $c->setAttribute('fatura_vencimento', $fatura['vencimento'] ?? null);
            $c->setAttribute('fatura_pendente', $fatura['pendente'] ?? null);

            return $c;
        })->values();
    }

    /**
     * Fatura do cartão do sistema que vence a seguir: a do mês do próximo
     * vencimento, ou, sem dia cadastrado, o mês mais próximo com compras de
     * hoje em diante. Nula quando não há compra nela.
     *
     * @return ?array{mes: string, vencimento: string, total: float, pendente: float, paga: bool}
     */
    private function proximaFaturaDoSistema(Banco $b, CarbonInterface $hoje, ?Collection $compras = null): ?array
    {
        $compras ??= Despesa::withoutGlobalScope('tenant')->where('tenant_id', $b->tenant_id)
            ->where('tipo_pagamento', 'credito')->where('forma_pagamento', $b->id)
            ->get(['id', 'forma_pagamento', 'valor', 'data_compra', 'data_pagamento']);

        // A do próximo vencimento; se ela não tem compra (ex.: tudo lançado
        // caiu na seguinte), a primeira fatura com compra daí em diante.
        $vence = $b->dia_vencimento_cartao ? $this->proximoDia($hoje, (int) $b->dia_vencimento_cartao) : $hoje->copy();
        if (! $compras->contains(fn ($d) => $d->data_compra->format('Y-m') === $vence->format('Y-m'))) {
            $primeira = $compras->filter(fn ($d) => $d->data_compra->format('Y-m') > $vence->format('Y-m'))->sortBy('data_compra')->first();
            if (! $primeira) {
                return null;
            }
            $vence = $b->dia_vencimento_cartao
                ? $this->diaNoMes($primeira->data_compra, (int) $b->dia_vencimento_cartao)
                : $primeira->data_compra->copy()->startOfDay();
        }

        $doMes = $compras->filter(fn ($d) => $d->data_compra->format('Y-m') === $vence->format('Y-m'));
        if ($doMes->isEmpty()) {
            return null;
        }
        $centavos = fn ($d) => (int) round((float) $d->valor * 100);
        $pendente = $doMes->whereNull('data_pagamento')->sum($centavos);

        return [
            'mes'        => $vence->format('Y-m'),
            'vencimento' => $vence->format('Y-m-d'),
            'total'      => round($doMes->sum($centavos) / 100, 2),
            'pendente'   => round($pendente / 100, 2),
            'paga'       => $pendente === 0,
        ];
    }

    /** Faturas de um cartão do sistema: compras no crédito agrupadas pelo mês do vencimento. */
    private function faturasDoSistema(int $tenantId, int $bancoId, ?int $diaVencimento, CarbonInterface $de, CarbonInterface $ate): array
    {
        return Despesa::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where('tipo_pagamento', 'credito')->where('forma_pagamento', $bancoId)
            ->whereBetween('data_compra', [$de->toDateString(), $ate->toDateString()])
            ->with(['categoria', 'fornecedor', 'pagoCom'])
            ->orderBy('data_compra')->orderBy('id')->get()
            ->groupBy(fn ($d) => $d->data_compra->format('Y-m'))
            ->sortKeysDesc()
            ->map(function ($compras, $mes) use ($diaVencimento) {
                $centavos = fn ($d) => (int) round((float) $d->valor * 100);
                $pago     = $compras->whereNotNull('data_pagamento')->sum($centavos);
                $pendente = $compras->whereNull('data_pagamento')->sum($centavos);
                $vence    = $diaVencimento ? $this->diaNoMes(Carbon::createFromFormat('!Y-m', $mes), $diaVencimento) : $compras->first()->data_compra;
                $paga     = $compras->first(fn ($d) => $d->data_pagamento);

                return [
                    'mes'        => $mes,
                    'vencimento' => $vence->format('Y-m-d'),
                    'total'      => round(($pago + $pendente) / 100, 2),
                    'pago'       => round($pago / 100, 2),
                    'pendente'   => round($pendente / 100, 2),
                    'paga'       => $pendente === 0,
                    'paga_em'    => $pendente === 0 ? $paga?->data_pagamento?->format('Y-m-d') : null,
                    'paga_com'   => $pendente === 0 ? $paga?->pagoCom?->nome : null,
                    'itens'      => $compras->map(fn ($d) => [
                        'ref'       => 'despesa:' . $d->id,
                        'data'      => $d->data_compra->format('Y-m-d'),
                        'descricao' => $this->descricaoDespesa($d),
                        'categoria' => $d->categoria?->nome,
                        'valor'     => round($centavos($d) / 100, 2),
                        'pago'      => $d->data_pagamento !== null,
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    /** "Parcela 1/3 — Geladeira" (como a compra parcelada é gravada) vira "Geladeira (1/3)". */
    private function descricaoDespesa(Despesa $d): string
    {
        $texto = $d->fornecedor?->nome ?? $d->observacoes ?? $d->categoria?->nome ?? 'Despesa';

        return preg_match('/^Parcela (\d+\/\d+) — (.+)$/u', $texto, $m) ? "{$m[2]} ({$m[1]})" : $texto;
    }

    /** "Cartão Mercado Pago" e "Mercado Pago" são a mesma conta. */
    public function chaveConta(?string $nome): string
    {
        $n = Str::lower(Str::ascii(trim((string) $nome)));

        return trim(preg_replace('/^cartao\s+(de\s+credito\s+)?/', '', $n));
    }

    /**
     * Painel do dia: quanto há nas contas, o que está atrasado, o que vence na
     * semana e como o mês fecha. Só compõe o que já existe (vencimentos,
     * resumo do mês, cartões) com o saldo das contas.
     */
    public function hoje(int $tenantId, ?CarbonInterface $hoje = null): array
    {
        $hoje   = ($hoje ?? now())->copy()->startOfDay();
        $semana = $hoje->copy()->addDays(7)->format('Y-m-d');
        $fimMes = $hoje->copy()->endOfMonth()->format('Y-m-d');
        $soma   = fn (array $itens) => round(array_sum(array_column(array_filter($itens, fn ($i) => ! ($i['pago'] ?? false)), 'valor')), 2);

        // Saldo das contas: a definição única do FinanceiroService.
        $saldos = app(FinanceiroService::class)->contas($tenantId, $hoje);
        $contas = array_map(fn ($c) => ['id' => $c['id'], 'nome' => $c['nome'], 'saldo' => $c['saldo'], 'cor' => $c['cor'], 'logo' => $c['logo']], $saldos['itens']);
        $totalContas = $saldos['total'];

        $v      = $this->vencimentos($tenantId, $hoje);
        $janela = fn (array $itens, string $ate) => array_values(array_filter($itens, fn ($i) => $i['data'] !== null && $i['data'] <= $ate));

        $atrasadoPagar   = array_values(array_filter($v['atrasado'], fn ($i) => $i['tipo'] !== 'receita'));
        $atrasadoReceber = array_values(array_filter($v['atrasado'], fn ($i) => $i['tipo'] === 'receita'));
        $pagar7   = $janela($v['a_pagar'], $semana);
        $receber7 = $janela($v['a_receber'], $semana);
        $pagarMes   = $soma($janela($v['a_pagar'], $fimMes)) + $soma($atrasadoPagar);
        $receberMes = $soma($janela($v['a_receber'], $fimMes)) + $soma($atrasadoReceber);

        $mes     = $this->resumoMes($tenantId, $hoje);
        $cartoes = $this->cartoes($tenantId)['resumo'];
        $fatura  = collect($v['a_pagar'])->where('origem', 'fatura')->sortBy('data')->first();

        return [
            'data'   => $hoje->format('Y-m-d'),
            'contas' => ['total' => $totalContas, 'itens' => $contas],
            'atrasado' => [
                'total_pagar'   => $soma($atrasadoPagar),
                'total_receber' => $soma($atrasadoReceber),
                'itens'         => $v['atrasado'],
            ],
            'proximos_7_dias' => [
                'a_pagar'       => $pagar7,
                'a_receber'     => $receber7,
                'total_pagar'   => $soma($pagar7),
                'total_receber' => $soma($receber7),
            ],
            'ate_fim_do_mes' => [
                'a_pagar'        => round($pagarMes, 2),
                'a_receber'      => round($receberMes, 2),
                'projecao_saldo' => round($totalContas + $receberMes - $pagarMes, 2),
            ],
            'mes' => [
                'mes'      => $mes['mes'],
                'receitas' => $mes['receitas']['realizado'],
                'despesas' => $mes['despesas']['realizado'],
                'saldo'    => $mes['saldo']['realizado'],
            ],
            'cartoes' => [
                'limite_disponivel' => $cartoes['limite_disponivel'],
                'utilizado_pct'     => $cartoes['utilizado_pct'],
                'proxima_fatura'    => $fatura ? ['descricao' => $fatura['descricao'], 'valor' => $fatura['valor'], 'data' => $fatura['data']] : null,
            ],
            'planilha_importada' => $mes['ultima_importacao'] !== null,
        ];
    }

    /** O dia de vencimento dentro do mês de $ref (dia 31 em mês de 30 vira o último dia). */
    private function diaNoMes(CarbonInterface $ref, int $dia): Carbon
    {
        return Carbon::create($ref->year, $ref->month, min($dia, $ref->daysInMonth))->startOfDay();
    }

    /** Próxima ocorrência do dia de vencimento a partir de $hoje (hoje conta). */
    private function proximoDia(CarbonInterface $hoje, int $dia): Carbon
    {
        $data = $this->diaNoMes($hoje, $dia);

        return $data->lt($hoje) ? $this->diaNoMes($hoje->copy()->startOfMonth()->addMonth(), $dia) : $data;
    }

    public function ultimaImportacao(int $tenantId): ?array
    {
        $ultima = PlanilhaImportacao::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where('status', '!=', PlanilhaImportacao::REJEITADA)->latest('id')->first();

        return $ultima ? [
            'id'      => $ultima->id,
            'em'      => $ultima->created_at->toIso8601String(),
            'arquivo' => $ultima->arquivo_nome,
        ] : null;
    }

    // ─── Base comum ───────────────────────────────────────────────────────────

    /**
     * Tudo o que se move no período, num formato só. `data` é quando estava
     * previsto; `realizado_em` é quando aconteceu (nulo se ainda não).
     */
    public function movimentos(int $tenantId, CarbonInterface $inicio, CarbonInterface $fim): Collection
    {
        $de  = $inicio->format('Y-m-d');
        $ate = $fim->format('Y-m-d');

        // Na planilha o lançamento tem uma data só: vale para previsto e realizado.
        // Realizado é o que está com situação Pago/Realizado — a família às vezes
        // preenche o valor realizado de contas futuras, que continuam pendentes.
        // Pago sem valor realizado vale o previsto.
        $planilha = PlanLancamento::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereBetween('data', [$de, $ate])->get()
            ->map(function ($l) {
                $realizado = $l->status === 'concluido' ? ($l->valor_realizado ?? $l->valor_previsto) : null;

                return [
                    'ref'             => 'planilha:' . $l->id,
                    'origem'          => 'planilha',
                    'ordem'           => $l->linha,
                    'tipo'            => $l->tipo,
                    'data'            => $l->data,
                    'realizado_em'    => $realizado !== null ? $l->data : null,
                    'descricao'       => $l->descricao,
                    'categoria'       => $l->categoria,
                    'forma'           => $l->forma,
                    'conta'           => $l->conta,
                    'banco_id'        => null,
                    'registrado_em'   => $l->created_at,
                    'valor_previsto'  => $l->valor_previsto !== null ? (float) $l->valor_previsto : null,
                    'valor_realizado' => $realizado !== null ? (float) $realizado : null,
                    'status'          => $l->status,
                    'observacao'      => $l->observacao,
                ];
            });

        // Só o escopo de tenant é dispensado (o tenant vem por parâmetro); o de
        // exclusão continua valendo — lançamento excluído não entra em conta.
        $despesas = Despesa::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereBetween('data_compra', [$de, $ate])->orWhereBetween('data_pagamento', [$de, $ate]))
            ->with(['categoria', 'fornecedor', 'banco'])->get()
            ->map(fn ($d) => [
                'ref'             => 'despesa:' . $d->id,
                'origem'          => 'manual',
                'ordem'           => 100000 + $d->id,
                'tipo'            => 'despesa',
                'data'            => $d->data_compra,
                'realizado_em'    => $d->data_pagamento,
                'descricao'       => $this->descricaoDespesa($d),
                'categoria'       => $d->categoria?->nome,
                'forma'           => $d->tipo_pagamento,
                'conta'           => $d->banco?->nome,
                // Compra no cartão paga pela fatura sai da conta escolhida no pagamento.
                'banco_id'        => $d->pago_com_banco_id ?? $d->forma_pagamento,
                'registrado_em'   => $d->created_at,
                'valor_previsto'  => (float) ($d->valor_previsto ?? $d->valor),
                'valor_realizado' => $d->data_pagamento ? (float) $d->valor : null,
                'status'          => $d->data_pagamento ? 'concluido' : 'pendente',
                'observacao'      => $d->observacoes,
            ]);

        $receitas = Receita::withoutGlobalScope('tenant')->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereBetween('data_prevista_recebimento', [$de, $ate])->orWhereBetween('data_recebimento', [$de, $ate]))
            ->with(['categoria', 'banco'])->get()
            ->map(fn ($r) => [
                'ref'             => 'receita:' . $r->id,
                'origem'          => 'manual',
                'ordem'           => 200000 + $r->id,
                'tipo'            => 'receita',
                'data'            => $r->data_prevista_recebimento,
                'realizado_em'    => $r->data_recebimento,
                'descricao'       => $r->observacoes ?? $r->categoria?->nome ?? 'Receita',
                'categoria'       => $r->categoria?->nome,
                'forma'           => $r->tipo_pagamento,
                'conta'           => $r->banco?->nome,
                'banco_id'        => $r->forma_recebimento,
                'registrado_em'   => $r->created_at,
                'valor_previsto'  => (float) ($r->valor_previsto ?? $r->valor),
                'valor_realizado' => $r->data_recebimento ? (float) $r->valor : null,
                'status'          => $r->data_recebimento ? 'concluido' : 'pendente',
                'observacao'      => $r->observacoes,
            ]);

        return $planilha->concat($despesas)->concat($receitas);
    }

    /** Previsto conta pela data prevista; realizado, pela data em que aconteceu. */
    private function totais(Collection $movimentos, CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $soma = fn (string $tipo, string $quando, string $campo) => round($movimentos
            ->filter(fn ($m) => $m['tipo'] === $tipo && $m[$quando] && $m[$quando]->between($inicio, $fim))
            ->sum($campo), 2);

        $receitas = ['previsto' => $soma('receita', 'data', 'valor_previsto'), 'realizado' => $soma('receita', 'realizado_em', 'valor_realizado')];
        $despesas = ['previsto' => $soma('despesa', 'data', 'valor_previsto'), 'realizado' => $soma('despesa', 'realizado_em', 'valor_realizado')];
        $saldo    = [
            'previsto'  => round($receitas['previsto'] - $despesas['previsto'], 2),
            'realizado' => round($receitas['realizado'] - $despesas['realizado'], 2),
        ];

        return [
            'receitas'            => $receitas,
            'despesas'            => $despesas,
            'saldo'               => $saldo,
            'economia_pct'        => $this->pct($saldo['realizado'], $receitas['realizado']),
            'comprometimento_pct' => $this->pct($despesas['realizado'], $receitas['realizado']),
        ];
    }

    /** Percentual em pontos percentuais; divisor zero resulta em 0. */
    private function pct(float $parte, float $todo): float
    {
        return $todo != 0.0 ? round($parte / $todo * 100, 2) : 0.0;
    }

    private function igual(?string $a, string $b): bool
    {
        return $a !== null && mb_strtolower(trim($a)) === $b;
    }
}
