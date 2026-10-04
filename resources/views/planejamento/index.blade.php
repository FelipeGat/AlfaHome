@extends('layouts.main')
@section('title', 'Planejamento')
@section('page-title', 'Planejamento — previsto x realizado')

@section('content')
@php
    $brl = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $pct = fn ($v) => number_format($v, 2, ',', '.') . '%';
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $tituloMes = ucfirst($meses[$mes->month - 1]) . ' de ' . $mes->year;
    $formas = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Débito', 'cartao' => 'Cartão', 'credito' => 'Crédito', 'transferencia' => 'Transferência', 'boleto' => 'Boleto', 'deposito' => 'Depósito', 'outros' => 'Outros'];
    $saldoReal = $resumo['saldo']['realizado'];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:14px; }
.plan-duo  { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:20px; }
@media (max-width:900px) { .plan-grid { grid-template-columns:repeat(2,1fr); } .plan-duo { grid-template-columns:1fr; } }
@media (max-width:480px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
</style>

@include('planejamento._abas', ['ultima' => $resumo['ultima_importacao']])

{{-- Mês de análise --}}
<div class="section-header mb-4">
    <div style="display:flex;align-items:center;gap:8px;">
        <a class="btn btn-ghost btn-sm" href="{{ route('planejamento.index', ['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" title="Mês anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <strong style="font-size:16px;min-width:170px;text-align:center;">{{ $tituloMes }}</strong>
        <a class="btn btn-ghost btn-sm" href="{{ route('planejamento.index', ['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" title="Próximo mês"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    <form method="GET" action="{{ route('planejamento.index') }}" style="display:flex;gap:6px;align-items:center;">
        <input type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="form-control" style="width:auto;" onchange="this.form.submit()">
    </form>
</div>

{{-- Realizado --}}
<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Receitas realizadas', 'valor' => $brl($resumo['receitas']['realizado']), 'cor' => 'var(--color-success)', 'nota' => 'Previsto ' . $brl($resumo['receitas']['previsto'])])
    @include('planejamento._kpi', ['rotulo' => 'Despesas realizadas', 'valor' => $brl($resumo['despesas']['realizado']), 'cor' => 'var(--color-danger)', 'nota' => 'Previsto ' . $brl($resumo['despesas']['previsto'])])
    @include('planejamento._kpi', ['rotulo' => 'Saldo do mês', 'valor' => $brl($saldoReal), 'cor' => $saldoReal >= 0 ? 'var(--color-success)' : 'var(--color-danger)', 'nota' => 'Previsto ' . $brl($resumo['saldo']['previsto'])])
    @include('planejamento._kpi', ['rotulo' => 'Economia', 'valor' => $pct($resumo['economia_pct']), 'nota' => 'Comprometimento da renda ' . $pct($resumo['comprometimento_pct'])])
</div>

{{-- Posição --}}
<div class="plan-grid" style="margin-bottom:20px;">
    @include('planejamento._kpi', ['rotulo' => 'Limite disponível', 'valor' => $brl($resumo['cartoes']['limite_disponivel']), 'nota' => $pct($resumo['cartoes']['utilizado_pct']) . ' do limite utilizado'])
    @include('planejamento._kpi', ['rotulo' => 'Faturas em aberto', 'valor' => $brl($resumo['cartoes']['faturas_abertas'])])
    @include('planejamento._kpi', ['rotulo' => 'Saldo de dívidas', 'valor' => $brl($resumo['dividas']['saldo_total']), 'nota' => $resumo['dividas']['quantidade'] . ' ' . ($resumo['dividas']['quantidade'] === 1 ? 'dívida' : 'dívidas')])
    @include('planejamento._kpi', ['rotulo' => 'Metas — progresso médio', 'valor' => $pct($resumo['metas']['progresso_medio_pct']), 'nota' => $resumo['metas']['quantidade'] . ' ' . ($resumo['metas']['quantidade'] === 1 ? 'meta' : 'metas')])
</div>

<div class="plan-duo">
    {{-- Previsto x realizado --}}
    <div class="card" style="padding:18px 20px;">
        <div class="card-title" style="margin-bottom:12px;">Previsto x realizado</div>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th></th><th class="plan-num">Previsto</th><th class="plan-num">Realizado</th><th class="plan-num">Diferença</th></tr>
                </thead>
                <tbody>
                    @foreach(['receitas' => 'Receitas', 'despesas' => 'Despesas', 'saldo' => 'Saldo'] as $chave => $rotulo)
                        @php $dif = round($resumo[$chave]['realizado'] - $resumo[$chave]['previsto'], 2); @endphp
                        <tr>
                            <td><strong>{{ $rotulo }}</strong></td>
                            <td class="plan-num">{{ $brl($resumo[$chave]['previsto']) }}</td>
                            <td class="plan-num">{{ $brl($resumo[$chave]['realizado']) }}</td>
                            <td class="plan-num" style="color:var(--color-text-muted);">{{ $dif > 0 ? '+' : '' }}{{ number_format($dif, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Despesas por categoria --}}
    <div class="card" style="padding:18px 20px;">
        <div class="card-title" style="margin-bottom:12px;">Despesas por categoria</div>
        @forelse($resumo['por_categoria'] as $categoria)
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span>{{ $categoria['categoria'] }}</span>
                    <span class="plan-num"><strong>{{ $brl($categoria['realizado']) }}</strong> <span style="color:var(--color-text-muted);">· {{ $pct($categoria['pct']) }}</span></span>
                </div>
                <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ min($categoria['pct'], 100) }}%;background:var(--color-primary);"></div></div>
            </div>
        @empty
            <div class="empty-state"><i class="fa-solid fa-chart-pie"></i><p>Nenhuma despesa realizada neste mês.</p></div>
        @endforelse
    </div>
</div>

{{-- Lançamentos do mês --}}
<div class="card" style="padding:18px 20px;">
    <div class="card-title" style="margin-bottom:12px;">Lançamentos de {{ $tituloMes }} <span style="font-weight:400;color:var(--color-text-muted);">({{ count($lancamentos) }})</span></div>
    @if(empty($lancamentos))
        <div class="empty-state"><i class="fa-solid fa-list-ul"></i><p>Nenhum lançamento neste mês.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th><th>Descrição</th><th>Categoria</th><th>Forma</th><th>Conta</th>
                        <th class="plan-num">Previsto</th><th class="plan-num">Realizado</th><th class="plan-num">Diferença</th><th>Situação</th><th>Origem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lancamentos as $l)
                        <tr>
                            <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($l['data'])->format('d/m') }}</td>
                            <td>
                                <i class="fa-solid {{ $l['tipo'] === 'receita' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}" style="color:{{ $l['tipo'] === 'receita' ? 'var(--color-success)' : 'var(--color-danger)' }};" title="{{ $l['tipo'] === 'receita' ? 'Receita' : 'Despesa' }}"></i>
                                {{ $l['descricao'] }}
                                @if($l['observacao'] && $l['observacao'] !== $l['descricao'])
                                    <div style="font-size:11px;color:var(--color-text-muted);">{{ $l['observacao'] }}</div>
                                @endif
                            </td>
                            <td>{{ $l['categoria'] ?? '—' }}</td>
                            <td>{{ $formas[$l['forma']] ?? ($l['forma'] ?? '—') }}</td>
                            <td>{{ $l['conta'] ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($l['valor_previsto']) }}</td>
                            <td class="plan-num">{{ $brl($l['valor_realizado']) }}</td>
                            <td class="plan-num">
                                @if($l['diferenca'] !== null && $l['diferenca'] != 0)
                                    <strong style="color:var(--color-warning);">{{ $l['diferenca'] > 0 ? '+' : '' }}{{ number_format($l['diferenca'], 2, ',', '.') }}</strong>
                                @else
                                    <span style="color:var(--color-text-faint);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($l['status'] === 'concluido')
                                    <span class="badge badge-success">{{ $l['tipo'] === 'receita' ? 'Recebido' : 'Pago' }}</span>
                                @else
                                    <span class="badge badge-warning">Pendente</span>
                                @endif
                            </td>
                            <td>
                                @if($l['origem'] === 'planilha')
                                    <span class="badge badge-gray" title="Mantido na planilha — altere lá e importe de novo">Planilha</span>
                                @else
                                    <span class="badge badge-blue" title="Lançado no sistema">Sistema</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size:12px;color:var(--color-text-muted);margin-top:10px;">
            Lançamentos com origem <strong>Planilha</strong> são mantidos na planilha: para alterar, edite lá e importe de novo.
        </p>
    @endif
</div>
@endsection
