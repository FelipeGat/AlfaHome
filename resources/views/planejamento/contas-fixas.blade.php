@extends('layouts.main')
@section('title', 'Contas fixas')
@section('page-title', 'Planejamento — contas fixas')

@section('content')
@php
    $brl = fn ($v) => brl($v);
    $formas = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Débito', 'cartao' => 'Cartão', 'transferencia' => 'Transferência', 'boleto' => 'Boleto'];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:640px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Previsto por mês', 'valor' => $brl($contas['resumo']['previsto_total'])])
    @include('planejamento._kpi', ['rotulo' => 'Realizado', 'valor' => $brl($contas['resumo']['realizado_total'])])
    @include('planejamento._kpi', ['rotulo' => 'Contas fixas', 'valor' => $contas['itens']->count()])
</div>

<div class="card" style="padding:18px 20px;">
    @if($contas['itens']->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-calendar-check"></i><p>Nenhuma conta fixa na planilha.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th class="plan-num">Vence dia</th><th>Conta</th><th>Categoria</th><th>Forma</th>
                        <th class="plan-num">Previsto</th><th class="plan-num">Realizado</th><th>Situação</th><th>Desde</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contas['itens'] as $c)
                        <tr>
                            <td class="plan-num"><strong>{{ $c->dia_vencimento ?? '—' }}</strong></td>
                            <td>{{ $c->conta }}@if($c->observacao)<div style="font-size:11px;color:var(--color-text-muted);">{{ $c->observacao }}</div>@endif</td>
                            <td>{{ $c->categoria ?? '—' }}</td>
                            <td>{{ $formas[$c->forma] ?? ($c->forma ?? '—') }}</td>
                            <td class="plan-num">{{ $brl($c->valor_previsto) }}</td>
                            <td class="plan-num">{{ $brl($c->valor_realizado) }}</td>
                            <td>
                                @if($c->status === 'concluido')
                                    <span class="badge badge-success">{{ $c->status_planilha ?? 'Pago' }}</span>
                                @elseif($c->status === 'pendente')
                                    <span class="badge badge-warning">{{ $c->status_planilha ?? 'Pendente' }}</span>
                                @else
                                    <span class="badge badge-gray">{{ $c->status_planilha }}</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{{ $c->mes_inicial?->format('m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size:12px;color:var(--color-text-muted);margin-top:10px;">
            Lista de referência: estas contas não somam nos totais do mês, que vêm dos lançamentos.
        </p>
    @endif
</div>
@endsection
