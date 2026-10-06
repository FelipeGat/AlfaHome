@extends('layouts.main')
@section('title', 'Planejamento')
@section('page-title', 'Planejamento — previsto x realizado')

@section('content')
@php
    $brl = fn ($v) => brl($v);
    $pct = fn ($v) => number_format($v, 2, ',', '.') . '%';
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $tituloMes = ucfirst($meses[$mes->month - 1]) . ' de ' . $mes->year;
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

{{-- Onde o mês fugiu do previsto: a lista completa fica em Lançamentos --}}
@php $diferentes = array_values(array_filter($lancamentos, fn ($l) => $l['diferenca'] !== null && $l['diferenca'] != 0)); @endphp
<div class="card" style="padding:18px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
        <div class="card-title">Realizado diferente do previsto <span style="font-weight:400;color:var(--color-text-muted);">({{ count($diferentes) }})</span></div>
        <a href="{{ route('planejamento.lancamentos', ['mes' => $mes->format('Y-m')]) }}" style="font-size:13px;font-weight:600;color:var(--color-primary);text-decoration:none;">Ver os {{ count($lancamentos) }} lançamentos do mês &rarr;</a>
    </div>
    @if(empty($lancamentos))
        <div class="empty-state"><i class="fa-solid fa-list-ul"></i><p>Nenhum lançamento neste mês.</p></div>
    @elseif(empty($diferentes))
        <p style="font-size:13px;color:var(--color-text-muted);">Tudo o que já foi pago ou recebido bateu com o previsto.</p>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr><th>Data</th><th>Descrição</th><th class="plan-num">Previsto</th><th class="plan-num">Realizado</th><th class="plan-num">Diferença</th></tr>
                </thead>
                <tbody>
                    @foreach($diferentes as $l)
                        <tr>
                            <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($l['data'])->format('d/m') }}</td>
                            <td>
                                <i class="fa-solid {{ $l['tipo'] === 'receita' ? 'fa-arrow-down' : 'fa-arrow-up' }}" style="color:{{ $l['tipo'] === 'receita' ? 'var(--color-success)' : 'var(--color-danger)' }};"></i>
                                {{ $l['descricao'] }}
                                <div style="font-size:11px;color:var(--color-text-muted);">{{ $l['categoria'] ?? '—' }}</div>
                            </td>
                            <td class="plan-num">{{ $brl($l['valor_previsto']) }}</td>
                            <td class="plan-num">{{ $brl($l['valor_realizado']) }}</td>
                            <td class="plan-num"><strong style="color:var(--color-warning);">{{ $l['diferenca'] > 0 ? '+' : '' }}{{ number_format($l['diferenca'], 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p style="font-size:12px;color:var(--color-text-muted);margin-top:10px;">
        Os lançamentos são mantidos na planilha: para alterar, edite lá e importe de novo.
    </p>
</div>
@endsection
