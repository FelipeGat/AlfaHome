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
.prev-linha { display:grid; grid-template-columns:44px minmax(0,1fr) auto; gap:10px; align-items:center; padding:9px 0; border-top:1px solid var(--color-border); }
.prev-dia { font-size:12.5px; font-weight:600; color:var(--color-text-muted); font-variant-numeric:tabular-nums; }
.prev-txt { min-width:0; }
.prev-txt span { display:block; font-size:14px; color:var(--color-text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.prev-txt small { display:block; font-size:12px; color:var(--color-text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.prev-val { display:flex; flex-direction:column; align-items:flex-end; gap:2px; }
.prev-val .badge { font-size:10.5px; }
.prev-linha.feito .prev-txt span, .prev-linha.feito strong { color:var(--color-text-muted); }
.cat-modo { display:inline-flex; background:var(--color-bg); border-radius:8px; padding:3px; gap:2px; }
.cat-modo button { border:0; background:transparent; color:var(--color-text-muted); width:32px; height:28px; border-radius:6px; cursor:pointer; }
.cat-modo button[aria-pressed="true"] { background:var(--color-bg-card); color:var(--color-primary); box-shadow:0 1px 3px rgba(15,23,42,.12); }
.cat-pizza { display:grid; grid-template-columns:180px minmax(0,1fr); gap:20px; align-items:center; }
.cat-svg { width:180px; height:180px; transform:rotate(-90deg); }
.cat-leg { list-style:none; margin:0; padding:0; }
.cat-leg li { display:grid; grid-template-columns:10px minmax(0,1fr) auto auto; gap:8px; align-items:center; padding:6px 0; border-top:1px solid var(--color-border); font-size:13.5px; }
.cat-leg li:first-child { border-top:0; }
.cat-leg i { width:10px; height:10px; border-radius:3px; }
.cat-leg span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cat-leg small { color:var(--color-text-muted); font-size:12px; }
.cat-leg strong { font-weight:600; min-width:84px; text-align:right; }
@media (max-width:560px) { .cat-pizza { grid-template-columns:1fr; justify-items:center; } .cat-leg { width:100%; } }
.diz-cab { display:flex; justify-content:space-between; align-items:baseline; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
.diz-num { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
.diz-num small { display:block; font-size:12px; color:var(--color-text-muted); }
.diz-num strong { font-size:17px; font-weight:600; }
.diz-domingos { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid var(--color-border); }
.diz-dom { border:1px solid var(--color-border); border-radius:10px; padding:6px 12px; min-width:104px; }
.diz-dom span, .diz-dom small { display:block; font-size:12px; color:var(--color-text-muted); }
.diz-dom strong { display:block; font-size:14px; font-weight:600; }
.diz-dom.devolvido { border-color:var(--color-success); background:var(--color-success-soft); }
.diz-dom.devolvido small { color:var(--color-success); }
.diz-dom.atrasado { border-color:var(--color-danger); }
.diz-dom.atrasado small { color:var(--color-danger); }
@media (max-width:767px) { .diz-num { grid-template-columns:1fr 1fr; } }
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

{{-- Previsto do mês em destaque; o realizado vem embaixo --}}
@php
    $saldoPrev = $resumo['saldo']['previsto'];
    $econPrev  = $resumo['receitas']['previsto'] != 0 ? round($saldoPrev / $resumo['receitas']['previsto'] * 100, 2) : 0;
@endphp
<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Receitas previstas', 'valor' => $brl($resumo['receitas']['previsto']), 'cor' => 'var(--color-success)', 'nota' => 'Recebido ' . $brl($resumo['receitas']['realizado'])])
    @include('planejamento._kpi', ['rotulo' => 'Despesas previstas', 'valor' => $brl($resumo['despesas']['previsto']), 'cor' => 'var(--color-danger)', 'nota' => 'Pago ' . $brl($resumo['despesas']['realizado'])])
    @include('planejamento._kpi', ['rotulo' => 'Saldo previsto', 'valor' => $brl($saldoPrev), 'cor' => $saldoPrev >= 0 ? 'var(--color-success)' : 'var(--color-danger)', 'nota' => 'Realizado ' . $brl($saldoReal)])
    @include('planejamento._kpi', ['rotulo' => 'Economia prevista', 'valor' => $pct($econPrev), 'nota' => 'Realizada ' . $pct($resumo['economia_pct']) . ' · renda comprometida ' . $pct($resumo['comprometimento_pct'])])
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

    {{-- Despesas por categoria: pizza ou barras, à escolha (fica guardado neste navegador) --}}
    @php
        $coresCat = ['#2563EB', '#14B8A6', '#F59E0B', '#8B5CF6', '#EC4899', '#0EA5E9', '#94A3B8'];
        $catTop = array_slice($resumo['por_categoria'], 0, 6);
        $catResto = array_slice($resumo['por_categoria'], 6);
        if ($catResto) {
            $catTop[] = ['categoria' => 'Outras (' . count($catResto) . ')', 'realizado' => round(array_sum(array_column($catResto, 'realizado')), 2), 'pct' => round(array_sum(array_column($catResto, 'pct')), 2)];
        }
        $acumCat = 0;
    @endphp
    <div class="card cat-card" style="padding:18px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:12px;">
            <div class="card-title">Despesas por categoria</div>
            @if($catTop)
                <div class="cat-modo" role="group" aria-label="Tipo de gráfico">
                    <button type="button" data-modo="pizza" aria-pressed="true" title="Pizza"><i class="fa-solid fa-chart-pie"></i></button>
                    <button type="button" data-modo="barras" aria-pressed="false" title="Barras"><i class="fa-solid fa-chart-simple"></i></button>
                </div>
            @endif
        </div>
        @if($catTop)
            <div class="cat-pizza">
                <svg viewBox="0 0 42 42" class="cat-svg" role="img" aria-label="Despesas por categoria: {{ collect($catTop)->map(fn ($c) => $c['categoria'] . ' ' . number_format($c['pct'], 1, ',', '.') . '%')->join(', ') }}">
                    <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--color-bg)" stroke-width="6"/>
                    @foreach($catTop as $i => $c)
                        @php $f = max(0, $c['pct'] - ($c['pct'] > 1.5 ? 0.6 : 0)); @endphp
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $coresCat[$i] }}" stroke-width="6" stroke-dasharray="{{ $f }} {{ 100 - $f }}" stroke-dashoffset="{{ -$acumCat }}"><title>{{ $c['categoria'] }}: {{ $brl($c['realizado']) }}</title></circle>
                        @php $acumCat += $c['pct']; @endphp
                    @endforeach
                </svg>
                <ul class="cat-leg">
                    @foreach($catTop as $i => $c)
                        <li><i style="background:{{ $coresCat[$i] }};"></i><span>{{ $c['categoria'] }}</span><small>{{ $pct($c['pct']) }}</small><strong class="plan-num">{{ $brl($c['realizado']) }}</strong></li>
                    @endforeach
                </ul>
            </div>
            <div class="cat-barras" hidden>
                @foreach($resumo['por_categoria'] as $categoria)
                    <div style="margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                            <span>{{ $categoria['categoria'] }}</span>
                            <span class="plan-num"><strong>{{ $brl($categoria['realizado']) }}</strong> <span style="color:var(--color-text-muted);">· {{ $pct($categoria['pct']) }}</span></span>
                        </div>
                        <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ min($categoria['pct'], 100) }}%;background:var(--color-primary);"></div></div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty-state"><i class="fa-solid fa-chart-pie"></i><p>Nenhuma despesa realizada neste mês.</p></div>
        @endif
    </div>
</div>

{{-- Dízimo: 10% das receitas da base, devolvido todo domingo --}}
@if($dizimo['domingos'] || $dizimo['devolvido'] > 0)
    <div class="card diz" style="padding:16px 20px;margin-bottom:14px;">
        <div class="diz-cab">
            <div class="card-title"><i class="fa-solid fa-hand-holding-heart" style="color:var(--color-primary);"></i> Dízimo de {{ $meses[$mes->month - 1] }}</div>
            <span style="font-size:12.5px;color:var(--color-text-muted);">10% de salários, pró-labore, Vixcard, trimestral e ticket · todo domingo</span>
        </div>
        <div class="diz-num">
            <div><small>Devido (10% de {{ $brl($dizimo['base_recebida']) }} recebidos)</small><strong class="plan-num">{{ $brl($dizimo['dizimo_devido']) }}</strong></div>
            <div><small>Devolvido</small><strong class="plan-num" style="color:var(--color-success);">{{ $brl($dizimo['devolvido']) }}</strong></div>
            <div><small>Falta</small><strong class="plan-num" style="color:{{ $dizimo['falta'] > 0 ? 'var(--color-danger)' : 'var(--color-text)' }};">{{ $brl($dizimo['falta']) }}</strong></div>
            <div><small>Previsto no mês (10% de {{ $brl($dizimo['base_prevista']) }})</small><strong class="plan-num">{{ $brl($dizimo['dizimo_previsto']) }}</strong></div>
        </div>
        @if($dizimo['domingos'])
            <div class="diz-domingos">
                @foreach($dizimo['domingos'] as $d)
                    <div class="diz-dom {{ $d['situacao'] }}" title="{{ implode(', ', $d['receitas']) }}">
                        <span>Dom {{ \Carbon\Carbon::parse($d['data'])->format('d/m') }}</span>
                        <strong class="plan-num">{{ $brl($d['valor']) }}</strong>
                        <small>{{ ['devolvido' => 'Devolvido', 'pendente' => 'Pendente', 'atrasado' => 'Atrasado'][$d['situacao']] }}</small>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif

{{-- O que o mês tem a receber e a pagar --}}
<div class="plan-duo">
    @foreach(['receber' => ['A receber', 'Recebido', 'var(--color-success)', 'fa-arrow-down'], 'pagar' => ['A pagar', 'Pago', 'var(--color-danger)', 'fa-arrow-up']] as $lado => [$titulo, $feitoTxt, $cor, $icone])
        @php $p = $previsto[$lado]; @endphp
        <div class="card prev-card" style="padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                <div class="card-title">{{ $titulo }} em {{ $meses[$mes->month - 1] }}</div>
                <strong class="plan-num" style="font-size:17px;color:{{ $cor }};">{{ $brl($p['total']) }}</strong>
            </div>
            <p style="font-size:12.5px;color:var(--color-text-muted);margin:0 0 8px;">{{ $feitoTxt }} {{ $brl($p['feito']) }} · falta {{ $brl($p['falta']) }}</p>
            @forelse($p['itens'] as $i)
                <div class="prev-linha {{ $i['feito'] ? 'feito' : '' }}">
                    <span class="prev-dia">{{ \Carbon\Carbon::parse($i['data'])->format('d/m') }}</span>
                    <span class="prev-txt"><span>{{ $i['descricao'] }}</span><small>{{ $i['detalhe'] }}</small></span>
                    <span class="prev-val">
                        <strong class="plan-num">{{ $brl($i['valor']) }}</strong>
                        @if($i['feito'])<span class="badge badge-success">{{ $feitoTxt }}</span>
                        @elseif($i['atrasado'])<span class="badge badge-danger">Atrasado</span>
                        @else<span class="badge badge-warning">Pendente</span>@endif
                    </span>
                </div>
            @empty
                <p style="font-size:13px;color:var(--color-text-muted);">Nada previsto {{ $lado === 'receber' ? 'a receber' : 'a pagar' }} neste mês.</p>
            @endforelse
        </div>
    @endforeach
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

@push('scripts')
<script>
(function () {
    var card = document.querySelector('.cat-card'); if (!card) return;
    var pizza = card.querySelector('.cat-pizza'), barras = card.querySelector('.cat-barras');
    function aplicar(modo) {
        if (!pizza || !barras) return;
        pizza.hidden = modo !== 'pizza'; barras.hidden = modo !== 'barras';
        card.querySelectorAll('.cat-modo button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.modo === modo ? 'true' : 'false'); });
    }
    var salvo = 'pizza';
    try { salvo = localStorage.getItem('alfahome-grafico-categorias') || 'pizza'; } catch (e) {}
    aplicar(salvo);
    card.querySelectorAll('.cat-modo button').forEach(function (b) {
        b.addEventListener('click', function () { aplicar(b.dataset.modo); try { localStorage.setItem('alfahome-grafico-categorias', b.dataset.modo); } catch (e) {} });
    });
})();
</script>
@endpush
