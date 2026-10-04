@extends('layouts.main')
@section('title', 'Metas')
@section('page-title', 'Planejamento — metas')

@section('content')
@php
    $brl = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $prioridades = ['alta' => ['Alta', 'badge-danger'], 'media' => ['Média', 'badge-warning'], 'baixa' => ['Baixa', 'badge-gray']];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:640px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Progresso médio', 'valor' => number_format($metas['resumo']['progresso_medio_pct'], 2, ',', '.') . '%', 'cor' => 'var(--color-primary)'])
    @include('planejamento._kpi', ['rotulo' => 'Metas', 'valor' => $metas['resumo']['quantidade']])
</div>

@if($metas['itens']->isEmpty())
    <div class="card"><div class="empty-state"><i class="fa-solid fa-bullseye"></i><p>Nenhuma meta na planilha.</p></div></div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(380px,100%),1fr));gap:16px;">
        @foreach($metas['itens'] as $m)
            <div class="card" style="padding:18px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
                    <div>
                        <div style="font-size:15px;font-weight:700;">{{ $m->nome }}</div>
                        <div style="font-size:12px;color:var(--color-text-muted);">{{ $m->objetivo ?? '—' }}</div>
                    </div>
                    @if($m->prioridade)<span class="badge {{ $prioridades[$m->prioridade][1] }}">Prioridade {{ mb_strtolower($prioridades[$m->prioridade][0]) }}</span>@endif
                </div>

                <div style="display:flex;justify-content:space-between;align-items:baseline;">
                    <div style="font-size:24px;font-weight:700;color:var(--color-primary);">{{ $brl($m->valor_atual !== null ? (float) $m->valor_atual : null) }}</div>
                    <div style="font-size:13px;color:var(--color-text-muted);">de {{ $brl($m->valor_alvo !== null ? (float) $m->valor_alvo : null) }}</div>
                </div>
                <div class="progress-bar" style="margin-top:8px;"><div class="progress-bar-fill" style="width:{{ min($m->concluido_pct, 100) }}%;background:var(--color-primary);"></div></div>
                <div style="font-size:12px;color:var(--color-text-muted);margin:4px 0 12px;">{{ number_format($m->concluido_pct, 2, ',', '.') }}% concluído</div>

                <table class="table" style="font-size:13px;">
                    <tbody>
                        <tr><td>Falta</td><td class="plan-num"><strong>{{ $brl($m->falta) }}</strong></td></tr>
                        <tr><td>Aporte mensal</td><td class="plan-num">{{ $brl($m->aporte_mensal !== null ? (float) $m->aporte_mensal : null) }}</td></tr>
                        <tr><td>Prazo</td><td class="plan-num">{{ $m->prazo?->format('d/m/Y') ?? '' }}</td></tr>
                    </tbody>
                </table>
                @if($m->observacao)<div style="font-size:12px;color:var(--color-text-muted);margin-top:8px;">{{ $m->observacao }}</div>@endif
            </div>
        @endforeach
    </div>
@endif
@endsection
