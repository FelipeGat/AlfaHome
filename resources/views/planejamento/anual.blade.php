@extends('layouts.main')
@section('title', 'Planejamento anual')
@section('page-title', 'Planejamento — visão anual')

@section('content')
@php
    $brl = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
    $pct = fn ($v) => number_format($v, 2, ',', '.') . '%';
    $nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    $total = $anual['total'];
@endphp

<style>.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="section-header mb-4">
    <div style="display:flex;align-items:center;gap:8px;">
        <a class="btn btn-ghost btn-sm" href="{{ route('planejamento.anual', ['ano' => $anual['ano'] - 1]) }}" title="Ano anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <strong style="font-size:16px;min-width:70px;text-align:center;">{{ $anual['ano'] }}</strong>
        <a class="btn btn-ghost btn-sm" href="{{ route('planejamento.anual', ['ano' => $anual['ano'] + 1]) }}" title="Próximo ano"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</div>

<div class="card" style="padding:18px 20px;">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th class="plan-num">Receitas prev.</th><th class="plan-num">Receitas real.</th>
                    <th class="plan-num">Despesas prev.</th><th class="plan-num">Despesas real.</th>
                    <th class="plan-num">Saldo prev.</th><th class="plan-num">Saldo real.</th>
                    <th class="plan-num">Economia</th><th class="plan-num">Comprometimento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($anual['meses'] as $i => $m)
                    @php $vazio = $m['receitas']['previsto'] == 0 && $m['receitas']['realizado'] == 0 && $m['despesas']['previsto'] == 0 && $m['despesas']['realizado'] == 0; @endphp
                    <tr style="{{ $vazio ? 'color:var(--color-text-faint);' : '' }}">
                        <td><a href="{{ route('planejamento.index', ['mes' => $m['mes']]) }}" style="color:inherit;font-weight:600;">{{ $nomes[$i] }}/{{ $anual['ano'] }}</a></td>
                        <td class="plan-num">{{ $brl($m['receitas']['previsto']) }}</td>
                        <td class="plan-num">{{ $brl($m['receitas']['realizado']) }}</td>
                        <td class="plan-num">{{ $brl($m['despesas']['previsto']) }}</td>
                        <td class="plan-num">{{ $brl($m['despesas']['realizado']) }}</td>
                        <td class="plan-num">{{ $brl($m['saldo']['previsto']) }}</td>
                        <td class="plan-num" style="{{ $vazio ? '' : 'color:' . ($m['saldo']['realizado'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)') . ';font-weight:600;' }}">{{ $brl($m['saldo']['realizado']) }}</td>
                        <td class="plan-num">{{ $pct($m['economia_pct']) }}</td>
                        <td class="plan-num">{{ $pct($m['comprometimento_pct']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight:700;border-top:2px solid var(--color-border-strong);">
                    <td>Total {{ $anual['ano'] }}</td>
                    <td class="plan-num">{{ $brl($total['receitas']['previsto']) }}</td>
                    <td class="plan-num">{{ $brl($total['receitas']['realizado']) }}</td>
                    <td class="plan-num">{{ $brl($total['despesas']['previsto']) }}</td>
                    <td class="plan-num">{{ $brl($total['despesas']['realizado']) }}</td>
                    <td class="plan-num">{{ $brl($total['saldo']['previsto']) }}</td>
                    <td class="plan-num" style="color:{{ $total['saldo']['realizado'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">{{ $brl($total['saldo']['realizado']) }}</td>
                    <td class="plan-num">{{ $pct($total['economia_pct']) }}</td>
                    <td class="plan-num">{{ $pct($total['comprometimento_pct']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
