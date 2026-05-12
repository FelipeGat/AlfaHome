<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardSnapshotService
{
    public function snapshot(int $tenantId, ?string $inicio = null, ?string $fim = null, ?int $familiarId = null): array
    {
        $inicio = $inicio ?: now()->startOfMonth()->format('Y-m-d');
        $fim    = $fim    ?: now()->endOfMonth()->format('Y-m-d');

        $mesAtual = Carbon::parse($inicio);

        $totalReceitas = DB::table('receitas')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
            ->when($familiarId, fn($q) => $q->where(fn($s) =>
                $s->where('quem_recebeu', $familiarId)->orWhereNull('quem_recebeu')
            ))
            ->sum('valor');

        $totalDespesas = DB::table('despesas')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('data_compra', [$inicio, $fim])
            ->when($familiarId, fn($q) => $q->where(fn($s) =>
                $s->where('quem_comprou', $familiarId)->orWhereNull('quem_comprou')
            ))
            ->sum('valor');

        $bancos = DB::table('bancos')
            ->where('tenant_id', $tenantId)
            ->when($familiarId, fn($q) => $q->where('titular_id', $familiarId))
            ->orderBy('nome')
            ->get(['nome', 'saldo', 'cor', 'logo'])
            ->map(fn($b) => [
                'nome'  => $b->nome,
                'saldo' => (float) $b->saldo,
                'cor'   => $b->cor,
                'logo'  => $b->logo,
            ]);

        $cartoes = DB::table('bancos')
            ->where('tenant_id', $tenantId)
            ->where('tem_cartao_credito', true)
            ->when($familiarId, fn($q) => $q->where('titular_id', $familiarId))
            ->get(['nome', 'saldo_cartao', 'limite_cartao', 'cor'])
            ->map(fn($c) => [
                'nome'       => $c->nome,
                'fatura'     => (float) $c->saldo_cartao,
                'limite'     => (float) $c->limite_cartao,
                'percentual' => $c->limite_cartao > 0
                    ? round(($c->saldo_cartao / $c->limite_cartao) * 100, 1)
                    : 0,
            ]);

        $lancamentos = DB::table('receitas')
            ->leftJoin('categorias', 'receitas.categoria_id', '=', 'categorias.id')
            ->selectRaw("receitas.id, 'receita' as tipo, receitas.valor, receitas.data_prevista_recebimento as data, COALESCE(categorias.nome,'Sem categoria') as categoria, receitas.tipo_pagamento as tipo_pagamento")
            ->where('receitas.tenant_id', $tenantId)
            ->whereNull('receitas.deleted_at')
            ->union(
                DB::table('despesas')
                    ->leftJoin('categorias', 'despesas.categoria_id', '=', 'categorias.id')
                    ->selectRaw("despesas.id, 'despesa' as tipo, despesas.valor, despesas.data_compra as data, COALESCE(categorias.nome,'Sem categoria') as categoria, despesas.tipo_pagamento as tipo_pagamento")
                    ->where('despesas.tenant_id', $tenantId)
                    ->whereNull('despesas.deleted_at')
            )
            ->orderByDesc('data')
            ->limit(5)
            ->get();

        return [
            'cached_at' => now()->toIso8601String(),
            'periodo'   => [
                'inicio' => $inicio,
                'fim'    => $fim,
                'mes'    => ucfirst($mesAtual->translatedFormat('F Y')),
            ],
            'kpis' => [
                'receitas' => (float) $totalReceitas,
                'despesas' => (float) $totalDespesas,
                'saldo'    => (float) ($totalReceitas - $totalDespesas),
            ],
            'bancos'      => $bancos,
            'cartoes'     => $cartoes,
            'lancamentos' => $lancamentos,
            'totais'      => [
                'saldo_contas' => (float) $bancos->sum('saldo'),
                'fatura_total' => (float) $cartoes->sum('fatura'),
                'limite_total' => (float) $cartoes->sum('limite'),
            ],
        ];
    }
}
