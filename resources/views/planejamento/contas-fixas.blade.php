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
    @include('planejamento._kpi', ['rotulo' => 'Receitas fixas por mês', 'valor' => $brl($contas['resumo']['receitas_total']), 'cor' => 'var(--color-success)', 'nota' => $contas['receitas']->count() . ' ' . ($contas['receitas']->count() === 1 ? 'receita' : 'receitas')])
    @include('planejamento._kpi', ['rotulo' => 'Despesas fixas por mês', 'valor' => $brl($contas['resumo']['despesas_total']), 'cor' => 'var(--color-danger)', 'nota' => $contas['despesas']->count() . ' ' . ($contas['despesas']->count() === 1 ? 'conta' : 'contas')])
    @include('planejamento._kpi', ['rotulo' => 'Sobra das fixas', 'valor' => $brl($contas['resumo']['sobra']), 'cor' => $contas['resumo']['sobra'] < 0 ? 'var(--color-danger)' : 'var(--color-text)', 'nota' => 'Antes dos gastos do dia a dia e do cartão'])
</div>

@if($contas['itens']->isEmpty())
    <div class="card" style="padding:18px 20px;"><div class="empty-state"><i class="fa-solid fa-calendar-check"></i><p>Nenhuma conta fixa na planilha.</p></div></div>
@else
    @foreach(['receitas' => ['Receitas fixas', 'Nenhuma receita fixa na planilha. Cadastre na aba Contas Fixas com a categoria Renda fixa ou Renda variável.', 'Recebido'], 'despesas' => ['Despesas fixas', 'Nenhuma despesa fixa na planilha.', 'Pago']] as $lado => [$titulo, $vazio, $feito])
        <div class="card" style="padding:18px 20px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:10px;">
                <div class="card-title">{{ $titulo }}</div>
                <strong class="plan-num" style="color:{{ $lado === 'receitas' ? 'var(--color-success)' : 'var(--color-danger)' }};">{{ $brl($contas['resumo'][$lado . '_total']) }} por mês</strong>
            </div>
            @if($contas[$lado]->isEmpty())
                <p style="font-size:13px;color:var(--color-text-muted);">{{ $vazio }}</p>
            @else
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="plan-num">Dia</th><th>Conta</th><th>Categoria</th><th>Forma</th>
                                <th class="plan-num">Previsto</th><th class="plan-num">Realizado</th><th>Situação</th><th>Desde</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contas[$lado] as $c)
                                <tr>
                                    <td class="plan-num"><strong>{{ $c->dia_vencimento ?? '—' }}</strong></td>
                                    <td>{{ $c->conta }}@if($c->observacao)<div style="font-size:11px;color:var(--color-text-muted);">{{ $c->observacao }}</div>@endif</td>
                                    <td>{{ $c->categoria ?? '—' }}</td>
                                    <td>{{ $formas[$c->forma] ?? ($c->forma ?? '—') }}</td>
                                    <td class="plan-num">{{ $brl($c->valor_previsto) }}</td>
                                    <td class="plan-num">{{ $brl($c->valor_realizado) }}</td>
                                    <td>
                                        @if($c->status === 'concluido')
                                            <span class="badge badge-success">{{ $c->status_planilha ?? $feito }}</span>
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
            @endif
        </div>
    @endforeach
    <p style="font-size:12px;color:var(--color-text-muted);">
        Lista de referência: os totais do mês vêm dos lançamentos. Na tela do mês, o sistema avisa quando uma conta fixa não foi lançada.
    </p>
@endif
@endsection
