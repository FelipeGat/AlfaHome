@extends('layouts.main')
@section('title', 'Cartões e parceladas')
@section('page-title', 'Planejamento — cartões e compras parceladas')

@section('content')
@php
    $brl = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', '.') . '%';
    $rc = $cartoes['resumo'];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:900px) { .plan-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:480px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Limite disponível', 'valor' => $brl($rc['limite_disponivel']), 'cor' => 'var(--color-success)'])
    @include('planejamento._kpi', ['rotulo' => 'Limite utilizado', 'valor' => $pct($rc['utilizado_pct']), 'cor' => $rc['utilizado_pct'] >= 80 ? 'var(--color-danger)' : 'var(--color-text)', 'nota' => $brl($rc['limite_utilizado']) . ' de ' . $brl($rc['limite_total'])])
    @include('planejamento._kpi', ['rotulo' => 'Faturas em aberto', 'valor' => $brl($rc['faturas_abertas'])])
    @include('planejamento._kpi', ['rotulo' => 'Saldo das parceladas', 'valor' => $brl($parceladas['resumo']['saldo_total']), 'nota' => $brl($parceladas['resumo']['parcela_mensal_total']) . ' em parcelas por mês'])
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <div class="card-title" style="margin-bottom:12px;">Cartões</div>
    @if($cartoes['itens']->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-credit-card"></i><p>Nenhum cartão na planilha.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cartão</th><th>Banco</th>
                        <th class="plan-num">Limite total</th><th class="plan-num">Utilizado</th><th class="plan-num">Disponível</th>
                        <th style="min-width:120px;">Uso</th>
                        <th class="plan-num">Fecha dia</th><th class="plan-num">Vence dia</th><th class="plan-num">Fatura atual</th><th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cartoes['itens'] as $c)
                        <tr>
                            <td><strong>{{ $c->nome }}</strong>@if($c->observacao)<div style="font-size:11px;color:var(--color-text-muted);">{{ $c->observacao }}</div>@endif</td>
                            <td>{{ $c->banco ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($c->limite_total) }}</td>
                            <td class="plan-num">{{ $brl($c->limite_utilizado) }}</td>
                            <td class="plan-num">{{ $brl($c->limite_disponivel) }}</td>
                            <td>
                                @if($c->utilizado_pct !== null)
                                    <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ min($c->utilizado_pct, 100) }}%;background:{{ $c->utilizado_pct >= 80 ? 'var(--color-danger)' : 'var(--color-primary)' }};"></div></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">{{ $pct($c->utilizado_pct) }}</div>
                                @else
                                    <span style="color:var(--color-text-faint);">—</span>
                                @endif
                            </td>
                            <td class="plan-num">{{ $c->dia_fechamento ?? '—' }}</td>
                            <td class="plan-num">{{ $c->dia_vencimento ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($c->fatura_atual) }}</td>
                            <td>@if($c->status_fatura)<span class="badge {{ mb_strtolower($c->status_fatura) === 'aberta' ? 'badge-warning' : 'badge-success' }}">{{ $c->status_fatura }}</span>@else<span style="color:var(--color-text-faint);">—</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="card" style="padding:18px 20px;">
    <div class="card-title" style="margin-bottom:12px;">Compras parceladas</div>
    @if($parceladas['itens']->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-layer-group"></i><p>Nenhuma compra parcelada na planilha.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Compra</th><th>Cartão</th><th>Data</th>
                        <th class="plan-num">Valor total</th><th class="plan-num">Parcela</th>
                        <th style="min-width:130px;">Pagas</th>
                        <th class="plan-num">Restantes</th><th class="plan-num">Saldo</th><th>Próx. vencimento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($parceladas['itens'] as $p)
                        @php $andamento = $p->parcelas ? min((int) $p->parcelas_pagas / $p->parcelas * 100, 100) : 0; @endphp
                        <tr>
                            <td><strong>{{ $p->compra }}</strong>@if($p->observacao)<div style="font-size:11px;color:var(--color-text-muted);">{{ $p->observacao }}</div>@endif</td>
                            <td>
                                {{ $p->cartao ?? '—' }}
                                @if($p->cartao && ! $p->plan_cartao_id)
                                    <i class="fa-solid fa-circle-exclamation" style="color:var(--color-warning);" title="Este cartão não está na aba Cartões da planilha"></i>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{{ $p->data?->format('d/m/Y') ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($p->valor_total) }}</td>
                            <td class="plan-num">{{ $p->parcelas ? $p->parcelas . ' x ' . number_format($p->valor_parcela, 2, ',', '.') : '—' }}</td>
                            <td>
                                <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ $andamento }}%;background:var(--color-primary);"></div></div>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">{{ $p->parcelas_pagas ?? 0 }} de {{ $p->parcelas ?? '—' }}</div>
                            </td>
                            <td class="plan-num">{{ $p->parcelas_restantes ?? '—' }}</td>
                            <td class="plan-num"><strong>{{ $brl($p->saldo) }}</strong></td>
                            <td style="white-space:nowrap;">{{ $p->proximo_vencimento?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
