@extends('layouts.main')
@section('title', 'Extrato')
@section('page-title', 'Extrato')

@section('content')
@php
    $brl = fn ($v) => brl($v);
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $tituloMes = ucfirst($meses[$mes->month - 1]) . ' de ' . $mes->year;
    $formas = ['dinheiro' => 'Dinheiro', 'pix' => 'PIX', 'debito' => 'Débito', 'cartao' => 'Cartão', 'credito' => 'Crédito', 'transferencia' => 'Transferência', 'boleto' => 'Boleto', 'deposito' => 'Depósito', 'outros' => 'Outros'];
    $link = fn (array $muda) => route('planejamento.lancamentos', array_filter(array_merge(['mes' => $mes->format('Y-m')], $filtros, $muda), fn ($v) => $v !== null && $v !== ''));
    $filtrando = array_filter($filtros, fn ($v) => $v !== null && $v !== '');
@endphp

<style>
.lc-totais { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
@media (max-width:900px) { .lc-totais { grid-template-columns:repeat(2,1fr); } }
.lc-total { padding:14px 16px; }
.lc-total small { display:block; font-size:12px; color:var(--color-text-muted); margin-bottom:4px; }
.lc-total strong { font-size:18px; font-variant-numeric:tabular-nums; }
.lc-chips { display:flex; flex-wrap:wrap; gap:6px; }
.lc-dia { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--color-text-muted); padding:14px 0 6px; border-bottom:1px solid var(--color-border); }
.lc-item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--color-border); }
.lc-icone { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.lc-texto { flex:1; min-width:0; }
.lc-texto .lc-desc { font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.lc-texto .lc-meta { font-size:12px; color:var(--color-text-muted); }
.lc-valor { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
.lc-valor .lc-sit { font-size:11px; }
</style>

{{-- Mês --}}
<div class="section-header mb-4">
    <div style="display:flex;align-items:center;gap:8px;">
        <a class="btn btn-ghost btn-sm" href="{{ $link(['mes' => $mes->copy()->subMonth()->format('Y-m')]) }}" title="Mês anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <strong style="font-size:16px;min-width:170px;text-align:center;">{{ $tituloMes }}</strong>
        <a class="btn btn-ghost btn-sm" href="{{ $link(['mes' => $mes->copy()->addMonth()->format('Y-m')]) }}" title="Próximo mês"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    <span style="font-size:12px;color:var(--color-text-muted);">
        <i class="fa-solid fa-file-excel"></i> Mantidos na planilha — para mudar, altere no Excel.
    </span>
</div>

{{-- Totais do mês --}}
<div class="lc-totais">
    <div class="card lc-total"><small>Entrou</small><strong style="color:var(--color-success);">{{ $brl($totais['entradas']) }}</strong></div>
    <div class="card lc-total"><small>Saiu</small><strong style="color:var(--color-danger);">{{ $brl($totais['saidas']) }}</strong></div>
    <div class="card lc-total"><small>Falta receber</small><strong>{{ $brl($totais['a_receber']) }}</strong></div>
    <div class="card lc-total"><small>Falta pagar</small><strong>{{ $brl($totais['a_pagar']) }}</strong></div>
</div>

{{-- Filtros --}}
<div class="card" style="padding:14px 16px;margin-bottom:16px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div class="lc-chips">
            <a href="{{ $link(['tipo' => null]) }}" class="btn btn-sm {{ ! $filtros['tipo'] ? 'btn-primary' : 'btn-ghost' }}">Tudo</a>
            <a href="{{ $link(['tipo' => 'despesa']) }}" class="btn btn-sm {{ $filtros['tipo'] === 'despesa' ? 'btn-primary' : 'btn-ghost' }}">Saídas</a>
            <a href="{{ $link(['tipo' => 'receita']) }}" class="btn btn-sm {{ $filtros['tipo'] === 'receita' ? 'btn-primary' : 'btn-ghost' }}">Entradas</a>
        </div>
        <div class="lc-chips">
            <a href="{{ $link(['situacao' => null]) }}" class="btn btn-sm {{ ! $filtros['situacao'] ? 'btn-primary' : 'btn-ghost' }}">Pagos e pendentes</a>
            <a href="{{ $link(['situacao' => 'pendente']) }}" class="btn btn-sm {{ $filtros['situacao'] === 'pendente' ? 'btn-primary' : 'btn-ghost' }}">Pendentes</a>
            <a href="{{ $link(['situacao' => 'concluido']) }}" class="btn btn-sm {{ $filtros['situacao'] === 'concluido' ? 'btn-primary' : 'btn-ghost' }}">Pagos</a>
        </div>
    </div>
    <form method="GET" action="{{ route('planejamento.lancamentos') }}" style="display:flex;flex-wrap:wrap;gap:8px;">
        <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
        @if($filtros['tipo'])<input type="hidden" name="tipo" value="{{ $filtros['tipo'] }}">@endif
        @if($filtros['situacao'])<input type="hidden" name="situacao" value="{{ $filtros['situacao'] }}">@endif
        <input type="search" name="q" value="{{ $filtros['q'] }}" placeholder="Buscar por descrição, categoria, conta ou valor" class="form-control" style="flex:2;min-width:180px;">
        <select name="categoria" class="form-control" style="flex:1;min-width:150px;" onchange="this.form.submit()">
            <option value="">Todas as categorias</option>
            @foreach($categorias as $c)<option value="{{ $c }}" @selected($filtros['categoria'] === $c)>{{ $c }}</option>@endforeach
        </select>
        <select name="conta" class="form-control" style="flex:1;min-width:150px;" onchange="this.form.submit()">
            <option value="">Todas as contas e cartões</option>
            @foreach($contas as $c)<option value="{{ $c }}" @selected($filtros['conta'] === $c)>{{ $c }}</option>@endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
        @if($filtrando)
            <a href="{{ route('planejamento.lancamentos', ['mes' => $mes->format('Y-m')]) }}" class="btn btn-ghost btn-sm">Limpar</a>
        @endif
    </form>
</div>

{{-- Lista por dia --}}
<div class="card" style="padding:4px 16px 12px;">
    @if($qtd === 0)
        <div style="text-align:center;padding:36px 10px;color:var(--color-text-muted);">
            <i class="fa-regular fa-folder-open" style="font-size:28px;margin-bottom:8px;"></i>
            <p>{{ $filtrando ? 'Nenhum lançamento com esses filtros.' : 'Nenhum lançamento na planilha neste mês.' }}</p>
        </div>
    @else
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--color-text-muted);padding-top:12px;">
            <span>{{ $qtd }} {{ $qtd === 1 ? 'lançamento' : 'lançamentos' }}</span>
            <span>Resultado da lista: <strong style="color:{{ $totalLista < 0 ? 'var(--color-danger)' : 'var(--color-success)' }};">{{ $brl($totalLista) }}</strong></span>
        </div>
        @foreach($dias as $data => $itens)
            @php $d = \Carbon\Carbon::parse($data); @endphp
            <div class="lc-dia">{{ $d->format('d/m') }} · {{ $semana[$d->dayOfWeek] }}</div>
            @foreach($itens as $l)
                @php
                    $entrada = $l['tipo'] === 'receita';
                    $pago = $l['status'] === 'concluido';
                    $valor = $l['valor_realizado'] ?? $l['valor_previsto'];
                    $meta = array_filter([$l['categoria'], $l['conta'] ?: ($formas[$l['forma']] ?? $l['forma'])]);
                @endphp
                <div class="lc-item">
                    <div class="lc-icone" style="background:{{ $entrada ? 'rgba(34,197,94,.12)' : 'rgba(239,68,68,.10)' }};color:{{ $entrada ? 'var(--color-success)' : 'var(--color-danger)' }};">
                        <i class="fa-solid {{ $entrada ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                    </div>
                    <div class="lc-texto">
                        <div class="lc-desc" title="{{ $l['observacao'] }}">{{ $l['descricao'] }}</div>
                        <div class="lc-meta">{{ implode(' · ', $meta) ?: '—' }}</div>
                    </div>
                    <div class="lc-valor">
                        <div style="font-weight:600;color:{{ $entrada ? 'var(--color-success)' : 'inherit' }};">{{ $entrada ? '+ ' : '− ' }}{{ $brl($valor) }}</div>
                        @if($pago)
                            <span class="badge badge-success lc-sit">{{ $entrada ? 'Recebido' : 'Pago' }}</span>
                        @else
                            <span class="badge badge-warning lc-sit">Pendente</span>
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
    @endif
</div>
@endsection
