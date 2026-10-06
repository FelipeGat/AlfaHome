@extends('layouts.main')
@section('title', 'Dívidas')
@section('page-title', 'Planejamento — dívidas')

@section('content')
@php
    $brl = fn ($v) => brl($v);
    $prioridades = ['alta' => ['Alta', 'badge-danger'], 'media' => ['Média', 'badge-warning'], 'baixa' => ['Baixa', 'badge-gray']];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:640px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Saldo total de dívidas', 'valor' => $brl($dividas['resumo']['saldo_total']), 'cor' => 'var(--color-danger)'])
    @include('planejamento._kpi', ['rotulo' => 'Parcelas por mês', 'valor' => $brl($dividas['resumo']['parcela_mensal_total'])])
    @include('planejamento._kpi', ['rotulo' => 'Dívidas', 'valor' => $dividas['resumo']['quantidade']])
</div>

@if($dividas['itens']->isEmpty())
    <div class="card"><div class="empty-state"><i class="fa-solid fa-hand-holding-dollar"></i><p>Nenhuma dívida na planilha.</p></div></div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(380px,100%),1fr));gap:16px;">
        @foreach($dividas['itens'] as $d)
            <div class="card" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
                    <div>
                        <div style="font-size:15px;font-weight:700;">{{ $d->nome }}</div>
                        <div style="font-size:12px;color:var(--color-text-muted);">{{ $d->credor ?? '—' }}</div>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                        @if($d->status)<span class="badge badge-blue">{{ $d->status }}</span>@endif
                        @if($d->prioridade)<span class="badge {{ $prioridades[$d->prioridade][1] }}">Prioridade {{ mb_strtolower($prioridades[$d->prioridade][0]) }}</span>@endif
                    </div>
                </div>

                <div style="font-size:24px;font-weight:700;color:var(--color-danger);">{{ $brl($d->saldo_atual) }}</div>
                <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px;">de {{ $brl($d->saldo_inicial) }}</div>

                @if($d->amortizado_pct !== null)
                    <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ max(min($d->amortizado_pct, 100), 0) }}%;background:var(--color-success);"></div></div>
                    <div style="font-size:12px;color:var(--color-text-muted);margin:4px 0 12px;">Já amortizado: <strong>{{ $brl($d->amortizado) }}</strong> ({{ number_format($d->amortizado_pct, 2, ',', '.') }}%)</div>
                @endif

                <table class="table" style="font-size:13px;">
                    <tbody>
                        <tr><td>Parcela mensal</td><td class="plan-num"><strong>{{ $brl($d->parcela_mensal) }}</strong></td></tr>
                        <tr><td>Taxa mensal</td><td class="plan-num">{{ $d->taxa_mensal !== null ? rtrim(rtrim(number_format((float) $d->taxa_mensal * 100, 4, ',', '.'), '0'), ',') . '%' : '—' }}</td></tr>
                        <tr><td>Vence no dia</td><td class="plan-num">{{ $d->dia_vencimento ?? '—' }}</td></tr>
                        <tr><td>Previsão de quitação</td><td class="plan-num">{{ $d->previsao_quitacao?->format('d/m/Y') ?? '—' }}</td></tr>
                    </tbody>
                </table>
                @if($d->observacao)<div style="font-size:12px;color:var(--color-text-muted);margin-top:8px;">{{ $d->observacao }}</div>@endif
            </div>
        @endforeach
    </div>
@endif
@endsection
