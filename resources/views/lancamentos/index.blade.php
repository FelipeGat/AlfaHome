@extends('layouts.main')
@section('title', 'Lançamentos')
@section('page-title', 'Lançamentos')

@section('content')

{{-- ─── Alertas de validação ─────────────────────────────────────────────── --}}
@if($errors->any())
<div class="alert alert-danger mb-3">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif
@if(session('success'))
<div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif

{{-- ─── Filtros + Navegação de meses ────────────────────────────────────── --}}
@php
    $mesAtual     = \Carbon\Carbon::parse($inicio);
    $mesAnterior  = $mesAtual->copy()->subMonth();
    $mesProximo   = $mesAtual->copy()->addMonth();
    $mesNome      = $mesAtual->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    $ehMesAtual   = $mesAtual->format('Y-m') === now()->format('Y-m');

    $urlMesAnterior = route('lancamentos.index', array_merge(request()->except(['inicio','fim']), [
        'inicio' => $mesAnterior->startOfMonth()->format('Y-m-d'),
        'fim'    => $mesAnterior->copy()->endOfMonth()->format('Y-m-d'),
    ]));
    $urlMesProximo = route('lancamentos.index', array_merge(request()->except(['inicio','fim']), [
        'inicio' => $mesProximo->startOfMonth()->format('Y-m-d'),
        'fim'    => $mesProximo->copy()->endOfMonth()->format('Y-m-d'),
    ]));
    $urlMesAtualReal = route('lancamentos.index', array_merge(request()->except(['inicio','fim']), [
        'inicio' => now()->startOfMonth()->format('Y-m-d'),
        'fim'    => now()->endOfMonth()->format('Y-m-d'),
    ]));
    $urlHoje = route('lancamentos.index', array_merge(request()->except(['inicio','fim']), [
        'inicio' => now()->format('Y-m-d'),
        'fim'    => now()->format('Y-m-d'),
    ]));
    $ehHoje = $inicio === now()->format('Y-m-d') && $fim === now()->format('Y-m-d');
@endphp

<style>
/* ── Filtros Lançamentos — regras específicas desta página ── */
/* Wrapper esquerdo: bancos + separador + tipo agrupados no desktop */
.filtro-esquerda { display:flex; align-items:center; gap:10px; flex-shrink:0; }

/* Mobile: reordenamento e tipo full-width */
@media (max-width:640px) {
    .filtro-esquerda { display:contents; } /* filhos entram no flex pai */
    .filtro-grupo-centro  { order:1; padding-top:2px; }
    .filtro-grupo-tipo    { order:2; justify-content:stretch; }
    .filtro-grupo-tipo form { width:100%; }
    .filtro-grupo-tipo .seg-control { width:100%; }
    .filtro-grupo-tipo .seg-btn { flex:1; text-align:center; }
    .filtro-grupo-bancos  { order:3; justify-content:center; }
}
</style>

<div class="card" style="padding:12px 16px;margin-bottom:18px;position:relative;">
    <div class="filtros-lanc">

        {{-- ─ WRAPPER ESQUERDO (desktop: lado esquerdo | mobile: display:contents) ─ --}}
        <div class="filtro-esquerda">

            {{-- Bolinhas de banco --}}
            @php
                $todasContasUrl = route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'tipo'=>$tipo,'familiar_id'=>$familiarId]));
            @endphp
            <div class="filtro-grupo filtro-grupo-bancos">
                <div class="av-grupo">
                    <a href="{{ $todasContasUrl }}" class="av-item" title="Todas as contas">
                        <div class="av-circulo"
                             style="border:3px solid {{ !$bancoId ? 'var(--color-primary)' : 'transparent' }};
                                    outline:{{ !$bancoId ? 'none' : '2px solid var(--color-border)' }};
                                    background:{{ !$bancoId ? 'var(--color-primary)' : 'var(--color-bg-inset)' }};
                                    box-shadow:{{ !$bancoId ? '0 0 0 2px var(--color-primary)44' : 'none' }};">
                            <i class="fa-solid fa-wallet" style="font-size:13px;color:{{ !$bancoId ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};"></i>
                        </div>
                        <span class="av-nome" style="color:{{ !$bancoId ? 'var(--color-primary)' : 'var(--color-text-subtle)' }};font-weight:{{ !$bancoId ? '700' : '400' }};">Todas</span>
                    </a>

                    @foreach($bancos as $b)
                    @php
                        $bSel   = $bancoId == $b->id;
                        $bCor   = $b->cor ?: 'var(--color-text-muted)';
                        $bUrl   = $bSel
                            ? route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'tipo'=>$tipo,'familiar_id'=>$familiarId]))
                            : route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'tipo'=>$tipo,'familiar_id'=>$familiarId,'banco_id'=>$b->id]));
                        $bIcone = match(true) {
                            $b->eh_dinheiro ?? false        => 'fa-money-bill-wave',
                            $b->tem_cartao_credito ?? false => 'fa-credit-card',
                            $b->tem_poupanca ?? false       => 'fa-piggy-bank',
                            default                         => 'fa-building-columns',
                        };
                    @endphp
                    <a href="{{ $bUrl }}" class="av-item" title="{{ $b->nome }}">
                        <div class="av-circulo"
                             style="border:3px solid {{ $bSel ? $bCor : 'transparent' }};
                                    outline:{{ $bSel ? 'none' : '2px solid var(--color-border)' }};
                                    box-shadow:{{ $bSel ? '0 0 0 2px '.$bCor.'44' : 'none' }};
                                    background:{{ $bSel ? $bCor : 'var(--color-bg-inset)' }};">
                            @if($b->logo)
                                <img src="{{ asset('img/bancos/' . $b->logo) }}" alt="{{ $b->nome }}" style="width:100%;height:100%;object-fit:contain;border-radius:50%;padding:4px;">
                            @else
                                <i class="fa-solid {{ $bIcone }}" style="font-size:13px;color:{{ $bSel ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};"></i>
                            @endif
                        </div>
                        <span class="av-nome" style="color:{{ $bSel ? $bCor : 'var(--color-text-subtle)' }};font-weight:{{ $bSel ? '700' : '400' }};">{{ explode(' ', $b->nome)[0] }}</span>
                    </a>
                    @endforeach
                </div>
            </div>

            <div class="separador-v" style="width:1px;height:30px;background:var(--color-border);flex-shrink:0;align-self:center;"></div>

            {{-- Botões Todos / Saídas / Entradas --}}
            <div class="filtro-grupo filtro-grupo-tipo">
                <form method="GET" action="{{ route('lancamentos.index') }}" id="form-filtro">
                    <input type="hidden" name="inicio"      id="f-inicio"  value="{{ $inicio }}">
                    <input type="hidden" name="fim"         id="f-fim"     value="{{ $fim }}">
                    <input type="hidden" name="banco_id"    value="{{ $bancoId }}">
                    <input type="hidden" name="familiar_id" value="{{ $familiarId }}">
                    <input type="hidden" name="tipo_pagamento" id="f-tipo-pag" value="{{ $tipoPagamento }}">

                    {{-- Date picker oculto --}}
                    <div id="date-picker-wrapper" style="display:none;position:absolute;background:var(--color-bg-card);border:1px solid var(--color-border);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);padding:16px;z-index:200;margin-top:8px;left:16px;max-width:340px;">
                        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:10px;font-weight:600;">Período personalizado</div>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <div><div style="font-size:11px;color:var(--color-text-subtle);margin-bottom:4px;">De</div><input type="date" id="dp-inicio" value="{{ $inicio }}" class="form-control" style="max-width:148px;"></div>
                            <div><div style="font-size:11px;color:var(--color-text-subtle);margin-bottom:4px;">Até</div><input type="date" id="dp-fim" value="{{ $fim }}" class="form-control" style="max-width:148px;"></div>
                            <button type="button" onclick="aplicarPeriodoCustom()" class="btn btn-primary btn-sm" style="margin-top:14px;"><i class="fa-solid fa-check"></i> Aplicar</button>
                        </div>
                    </div>

                    <div class="seg-control">
                        @foreach(['todos' => 'Todos', 'debito' => '↓ Saídas', 'credito' => '↑ Entradas'] as $val => $label)
                        <button type="submit" name="tipo" value="{{ $val }}" class="seg-btn"
                            style="background:{{ $tipo === $val ? 'var(--color-primary)' : 'var(--color-bg-card)' }};
                                   color:{{ $tipo === $val ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>
                </form>
            </div>

            {{-- Filtro por tipo de pagamento --}}
            <div style="display:flex;align-items:center;">
                <select id="sel-tipo-pag"
                        onchange="document.getElementById('f-tipo-pag').value=this.value;document.getElementById('form-filtro').submit()"
                        style="font-size:12px;padding:5px 10px;border:1px solid {{ $tipoPagamento ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $tipoPagamento ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $tipoPagamento ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:130px;">
                    <option value="">Todos os tipos</option>
                    <option value="dinheiro"     {{ $tipoPagamento === 'dinheiro'     ? 'selected' : '' }}>Dinheiro</option>
                    <option value="pix"          {{ $tipoPagamento === 'pix'          ? 'selected' : '' }}>Pix</option>
                    <option value="debito"       {{ $tipoPagamento === 'debito'       ? 'selected' : '' }}>Cartão Débito</option>
                    <option value="credito"      {{ $tipoPagamento === 'credito'      ? 'selected' : '' }}>Cartão Crédito</option>
                    <option value="transferencia"{{ $tipoPagamento === 'transferencia'? 'selected' : '' }}>Transferência</option>
                    <option value="boleto"       {{ $tipoPagamento === 'boleto'       ? 'selected' : '' }}>Boleto</option>
                </select>
            </div>

        </div>{{-- /filtro-esquerda --}}

        {{-- ─ CENTRO: navegação de mês + Hoje ─ --}}
        <div class="filtro-grupo filtro-grupo-centro">
            <div style="display:flex;align-items:center;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;">
                <a href="{{ $urlMesAnterior }}" class="nav-mes-btn" title="Mês anterior"><i class="fa-solid fa-chevron-left" style="font-size:12px;"></i></a>
                <button type="button" class="mes-label-btn" onclick="toggleDatePicker()" title="Clique para personalizar período">
                    {{ $ehHoje ? 'Hoje' : ucfirst($mesNome) }}
                </button>
                <a href="{{ $urlMesProximo }}" class="nav-mes-btn" title="Próximo mês"><i class="fa-solid fa-chevron-right" style="font-size:12px;"></i></a>
            </div>
            <div style="display:flex;gap:6px;">
                <a href="{{ $urlHoje }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;
                          border:1px solid {{ $ehHoje ? 'var(--color-primary)' : 'var(--color-border)' }};
                          background:{{ $ehHoje ? 'var(--color-primary)' : 'var(--color-bg-card)' }};
                          color:{{ $ehHoje ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};transition:all .15s;">
                    <i class="fa-solid fa-calendar-day"></i> Hoje
                </a>
                @if(!$ehMesAtual && !$ehHoje)
                <a href="{{ $urlMesAtualReal }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;border:1px solid var(--color-border);background:var(--color-bg-card);color:var(--color-text-muted);transition:all .15s;">
                    <i class="fa-solid fa-rotate-left"></i> Mês Atual
                </a>
                @endif
            </div>
        </div>

        {{-- ─ DIREITA: avatares dos membros ─ --}}
        @if($familiares->isNotEmpty())
        @php $todasUrl = route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'banco_id'=>$bancoId,'tipo'=>$tipo])); @endphp
        <div class="filtro-grupo filtro-grupo-members">
            <div class="av-grupo">
                <a href="{{ $todasUrl }}" class="av-item" title="Todos os membros">
                    <div class="av-circulo"
                         style="border:3px solid {{ !$familiarId ? 'var(--color-primary)' : 'transparent' }};
                                outline:{{ !$familiarId ? 'none' : '2px solid var(--color-border)' }};
                                background:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-bg-inset)' }};
                                box-shadow:{{ !$familiarId ? '0 0 0 2px var(--color-primary)44' : 'none' }};">
                        <i class="fa-solid fa-house" style="font-size:13px;color:{{ !$familiarId ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};"></i>
                    </div>
                    <span class="av-nome" style="color:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-text-subtle)' }};font-weight:{{ !$familiarId ? '700' : '400' }};">Todos</span>
                </a>

                @foreach($familiares as $fam)
                @php
                    $isSelected = $familiarId === $fam->id;
                    $iniciais   = implode('', array_map(fn($p) => strtoupper(substr($p, 0, 1)), array_slice(explode(' ', $fam->nome), 0, 2)));
                    $cores      = ['#6366f1','#0ea5e9','#16a34a','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
                    $cor        = $cores[$fam->id % count($cores)];
                    $famUrl     = $isSelected
                        ? route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'banco_id'=>$bancoId,'tipo'=>$tipo]))
                        : route('lancamentos.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'banco_id'=>$bancoId,'tipo'=>$tipo,'familiar_id'=>$fam->id]));
                @endphp
                <a href="{{ $famUrl }}" class="av-item" title="{{ $fam->nome }}">
                    <div class="av-circulo"
                         style="border:3px solid {{ $isSelected ? $cor : 'transparent' }};
                                outline:{{ $isSelected ? 'none' : '2px solid var(--color-border)' }};
                                box-shadow:{{ $isSelected ? '0 0 0 2px '.$cor.'44' : 'none' }};">
                        @if($fam->foto)
                            <img src="{{ Storage::url($fam->foto) }}" alt="{{ $fam->nome }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:{{ $cor }};color:#fff;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;border-radius:50%;">{{ $iniciais }}</div>
                        @endif
                    </div>
                    <span class="av-nome" style="color:{{ $isSelected ? $cor : 'var(--color-text-subtle)' }};font-weight:{{ $isSelected ? '700' : '400' }};">{{ explode(' ', $fam->nome)[0] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

{{-- ─── Cards de resumo ──────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
    <div class="card" style="padding:12px;text-align:center;">
        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;white-space:nowrap;"><i class="fa-solid fa-arrow-trend-up" style="color:var(--color-success);"></i> Entradas</div>
        <div class="fw-700" style="color:var(--color-success);font-size:clamp(13px,3vw,20px);font-weight:700;line-height:1.2;">{{ brl($totalEntradas) }}</div>
    </div>
    <div class="card" style="padding:12px;text-align:center;">
        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;white-space:nowrap;"><i class="fa-solid fa-arrow-trend-down" style="color:var(--color-danger);"></i> Saídas</div>
        <div class="fw-700" style="color:var(--color-danger);font-size:clamp(13px,3vw,20px);font-weight:700;line-height:1.2;">{{ brl($totalSaidas) }}</div>
    </div>
    <div class="card" style="padding:12px;text-align:center;">
        <div style="font-size:11px;color:var(--color-text-muted);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="fa-solid fa-scale-balanced" style="color:var(--color-violet);"></i> Saldo</div>
        <div class="fw-700" style="color:{{ $saldoPeriodo >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};font-size:clamp(13px,3vw,20px);font-weight:700;line-height:1.2;">
            {{ brl($saldoPeriodo) }}
        </div>
    </div>
</div>

{{-- ─── Botões de ação ──────────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:24px;">
    <button onclick="openModal('modal-manual')" class="btn-acao" style="border-color:var(--color-primary);background:var(--color-violet-soft);" onmouseover="this.style.background='var(--color-violet-soft)'" onmouseout="this.style.background='var(--color-violet-soft)'">
        <div class="btn-acao-icon" style="background:var(--color-primary);"><i class="fa-solid fa-pen-to-square" style="color:#fff;font-size:18px;"></i></div>
        <div class="btn-acao-txt"><span class="btn-acao-titulo">Lançamento Manual</span><span class="btn-acao-sub">Despesa ou receita</span></div>
    </button>
    <button onclick="document.getElementById('camera-input').click()" class="btn-acao" style="border-color:var(--color-success);background:var(--color-success-soft);" onmouseover="this.style.background='var(--color-success-soft)'" onmouseout="this.style.background='var(--color-success-soft)'">
        <div class="btn-acao-icon" style="background:var(--color-success);"><i class="fa-solid fa-receipt" style="color:#fff;font-size:18px;"></i></div>
        <div class="btn-acao-txt"><span class="btn-acao-titulo">Cupom / NF</span><span class="btn-acao-sub">Escanear ou foto</span></div>
    </button>
    <button onclick="openModal('modal-importar')" class="btn-acao" style="border-color:var(--color-info);background:var(--color-info-soft);" onmouseover="this.style.background='var(--color-info-soft)'" onmouseout="this.style.background='var(--color-info-soft)'">
        <div class="btn-acao-icon" style="background:var(--color-info);"><i class="fa-solid fa-file-arrow-up" style="color:#fff;font-size:18px;"></i></div>
        <div class="btn-acao-txt"><span class="btn-acao-titulo">Importar Extrato</span><span class="btn-acao-sub">OFX / CSV</span></div>
    </button>
</div>

<input type="file" id="camera-input" accept="image/*" capture="environment" style="display:none;">

<style>
.btn-acao {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:8px;padding:18px 10px;border-radius:12px;border:2px dashed;
    cursor:pointer;transition:background .15s;
}
.btn-acao-icon { width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.btn-acao-txt  { display:flex;flex-direction:column;align-items:center;gap:2px;text-align:center; }
.btn-acao-titulo { font-weight:700;font-size:13px;color:var(--color-text);line-height:1.2; }
.btn-acao-sub    { font-size:10px;color:var(--color-text-muted); }

@media (max-width:480px) {
    .btn-acao { padding:10px 6px;gap:5px; }
    .btn-acao-icon { width:34px;height:34px; }
    .btn-acao-icon i { font-size:14px !important; }
    .btn-acao-titulo { font-size:11px; }
    .btn-acao-sub { display:none; }
}
</style>

{{-- ─── Extrato ──────────────────────────────────────────────────────────── --}}
<div class="card ext-card">

    {{-- Cabeçalho do card --}}
    <div class="ext-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-list-ul" style="color:var(--color-text-subtle);font-size:13px;"></i>
            <span style="font-size:14px;font-weight:600;color:var(--color-text);">Extrato</span>
            @if($bancoBuscado)
            <span style="font-size:12px;color:var(--color-primary);font-weight:600;">— {{ $bancoBuscado->nome }}</span>
            @endif
        </div>
        <span style="font-size:11px;font-weight:700;color:var(--color-text-muted);background:var(--color-bg-inset);padding:3px 10px;border-radius:20px;">
            {{ count($movimentacoes) }} lançamentos
        </span>
    </div>

    @if(count($movimentacoes) > 0)
    @php
        $movsPorData = [];
        foreach ($movimentacoes as $mov) {
            $movsPorData[$mov['data_fmt']][] = $mov;
        }
    @endphp

    @foreach($movsPorData as $dataFmt => $movsDia)
    @php
        $dt = $movsDia[0]['data']; // Carbon instance
        $isHoje  = $dt->copy()->startOfDay()->equalTo(now()->startOfDay());
        $isOntem = $dt->copy()->startOfDay()->equalTo(now()->subDay()->startOfDay());
        if ($isHoje)       $dataLabel = 'Hoje · ' . $dataFmt;
        elseif ($isOntem)  $dataLabel = 'Ontem · ' . $dataFmt;
        else               $dataLabel = $dt->locale('pt_BR')->isoFormat('dddd, D [de] MMMM');
        $totalDia = array_sum(array_map(fn($m) => $m['tipo'] === 'credito' ? $m['valor'] : -$m['valor'], $movsDia));
    @endphp

    {{-- Cabeçalho do dia --}}
    <div class="ext-date-header">
        <span class="ext-date-label">{{ $dataLabel }}</span>
        <span style="font-size:11.5px;font-weight:700;color:{{ $totalDia >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
            {{ $totalDia >= 0 ? '+' : '−' }} {{ brl(abs($totalDia)) }}
        </span>
    </div>

    @foreach($movsDia as $mov)
    @php
        $st = $mov['status'];
        $stOk    = in_array($st, ['pago', 'recebido']);
        $stClass = $stOk ? 's-ok' : ($st === 'vencido' ? 's-venc' : 's-pend');
        $stLabel = $stOk
            ? ($mov['tipo'] === 'credito' ? 'Recebido' : 'Pago')
            : ($st === 'vencido' ? 'Vencido' : ($mov['tipo'] === 'credito' ? 'A receber' : 'A pagar'));
        $stIcon  = $stOk ? 'fa-check' : ($st === 'vencido' ? 'fa-triangle-exclamation' : 'fa-clock');
        $catIcone = $mov['categoria_icone'] ?: ($mov['tipo'] === 'credito' ? 'fa-circle-dollar-sign' : 'fa-cart-shopping');
    @endphp

    <div class="ext-row ext-{{ $mov['tipo'] }}">

        {{-- Ícone da categoria --}}
        <div class="ext-icone ext-{{ $mov['tipo'] }}">
            <i class="fa-solid {{ $catIcone }}"
               style="font-size:16px;color:{{ $mov['tipo'] === 'credito' ? 'var(--color-success)' : 'var(--color-danger)' }};"></i>
        </div>

        {{-- Descrição + meta --}}
        <div class="ext-info">
            <div class="ext-desc" title="{{ $mov['descricao'] }}">{{ $mov['descricao'] }}</div>
            <div class="ext-meta">
                <span class="ext-conta-pill">
                    <span class="ext-dot" style="background:{{ $mov['conta_cor'] }};"></span>
                    {{ $mov['conta'] }}
                </span>
                @if($mov['categoria'])
                <span class="ext-tag ext-tag-cat">{{ $mov['categoria'] }}</span>
                @endif
                @if($mov['recorrente'])
                <span class="ext-tag ext-tag-rec"><i class="fa-solid fa-rotate" style="font-size:8px;"></i> Recorrente</span>
                @endif
                @if(in_array($mov['origem'], ['qr_ocr', 'ocr']))
                <span class="ext-tag ext-tag-doc"><i class="fa-solid fa-camera" style="font-size:8px;"></i> Cupom</span>
                @elseif($mov['origem'] === 'importacao_extrato')
                <span class="ext-tag ext-tag-doc"><i class="fa-solid fa-file-import" style="font-size:8px;"></i> Importado</span>
                @endif
                @if($mov['numero_doc'])
                <span class="ext-tag ext-tag-cat">Nº {{ $mov['numero_doc'] }}</span>
                @endif
            </div>
        </div>

        {{-- Valor + status --}}
        <div class="ext-valor-col">
            <div class="ext-valor ext-{{ $mov['tipo'] }}">
                {{ $mov['tipo'] === 'credito' ? '+' : '−' }} {{ brl($mov['valor']) }}
            </div>
            <div class="ext-status {{ $stClass }}">
                <i class="fa-solid {{ $stIcon }}" style="font-size:8px;"></i> {{ $stLabel }}
            </div>
        </div>

        {{-- Excluir --}}
        <div style="flex-shrink:0;width:28px;">
            @if($mov['model'] === 'despesa')
            <form method="POST" action="{{ route('despesas.destroy', $mov['id']) }}"
                  onsubmit="return confirm('Excluir este lançamento?')">
                @csrf @method('DELETE')
                <button type="submit" class="ext-del-btn" title="Excluir">
                    <i class="fa-solid fa-trash" style="font-size:12px;"></i>
                </button>
            </form>
            @endif
        </div>

    </div>
    @endforeach
    @endforeach

    {{-- Totais do período --}}
    <div class="ext-footer">
        <span style="font-size:10px;font-weight:700;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-right:auto;">Total do período</span>
        <div class="ext-footer-item">
            <span class="ext-footer-dot" style="background:var(--color-danger);"></span>
            <span style="font-size:12.5px;font-weight:700;color:var(--color-danger);">− {{ brl($totalSaidas) }}</span>
        </div>
        <div style="width:1px;height:16px;background:var(--color-border);"></div>
        <div class="ext-footer-item">
            <span class="ext-footer-dot" style="background:var(--color-success);"></span>
            <span style="font-size:12.5px;font-weight:700;color:var(--color-success);">+ {{ brl($totalEntradas) }}</span>
        </div>
    </div>

    @else
    <div style="text-align:center;padding:48px 20px;">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--color-bg-inset);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fa-solid fa-receipt" style="font-size:22px;color:var(--color-text-subtle);"></i>
        </div>
        <p style="font-size:13px;font-weight:600;color:var(--color-text-muted);margin-bottom:4px;">Nenhum lançamento encontrado</p>
        <p style="font-size:12px;color:var(--color-text-subtle);">Use os botões acima para registrar movimentações.</p>
    </div>
    @endif

</div>

{{-- ─── Loading overlay ────────────────────────────────────────────────── --}}
<div id="scan-loading" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;align-items:center;justify-content:center;flex-direction:column;gap:14px;">
    <div style="width:56px;height:56px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:ld-spin 0.7s linear infinite;"></div>
    <div id="scan-loading-msg" style="color:#fff;font-weight:600;font-size:15px;text-align:center;max-width:260px;line-height:1.4;"></div>
</div>
<div id="scan-toast" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--color-text);color:var(--color-bg-card);padding:11px 22px;border-radius:8px;font-size:13px;z-index:600;box-shadow:0 4px 16px rgba(0,0,0,.35);max-width:340px;text-align:center;"></div>
<style>@keyframes ld-spin { to { transform: rotate(360deg); } }</style>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 1: Lançamento Manual                                             --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-backdrop" id="modal-manual">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <i class="fa-solid fa-pen-to-square" style="color:var(--color-primary);"></i>
            <h3>Lançamento Manual</h3>
            <button class="modal-close" onclick="closeModal('modal-manual')">&times;</button>
        </div>
        <div class="modal-body">

            {{-- Tabs Despesa / Receita --}}
            <div style="display:flex;border-bottom:2px solid var(--color-border);margin-bottom:18px;">
                <button type="button" id="tab-despesa" onclick="alternarTipo('despesa')"
                    style="padding:8px 20px;font-weight:600;font-size:13px;border:none;background:none;cursor:pointer;border-bottom:2px solid var(--color-primary);color:var(--color-primary);margin-bottom:-2px;">
                    <i class="fa-solid fa-arrow-trend-down"></i> Saída / Despesa
                </button>
                <button type="button" id="tab-receita" onclick="alternarTipo('receita')"
                    style="padding:8px 20px;font-weight:600;font-size:13px;border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;color:var(--color-text-subtle);margin-bottom:-2px;">
                    <i class="fa-solid fa-arrow-trend-up"></i> Entrada / Receita
                </button>
            </div>

            {{-- Formulário Despesa --}}
            <form method="POST" action="{{ route('despesas.store') }}" id="form-despesa">
                @csrf
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Valor (R$) *</label><input type="number" name="valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00" autofocus></div>
                    <div class="form-group"><label class="form-label">Data *</label><input type="date" name="data_compra" class="form-control" required value="{{ now()->format('Y-m-d') }}"></div>
                    <div class="form-group"><label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-control"><option value="">— Selecione —</option>@foreach($categorias as $cat)<option value="{{ $cat->id }}">{{ $cat->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Estabelecimento</label>
                        <select name="onde_comprou" class="form-control"><option value="">— Selecione —</option>@foreach($fornecedores as $f)<option value="{{ $f->id }}">{{ $f->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Quem Comprou</label>
                        <select name="quem_comprou" class="form-control"><option value="">🏠 Todos da Casa</option>@foreach($familiares as $fam)<option value="{{ $fam->id }}" {{ $fam->id == $meuFamiliarId ? 'selected' : '' }}>{{ $fam->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Conta / Banco</label>
                        <select name="forma_pagamento" class="form-control" id="manual-banco"
                                onchange="onBancoChangeLanc(this,'manual-tipo-pag','manual-info-cartao','manual-parcelas-row','manual-parcelas-input')">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}"
                                    data-nome="{{ strtolower($b->nome) }}"
                                    data-credito="{{ $b->tem_cartao_credito ? 1 : 0 }}"
                                    data-fechamento="{{ $b->dia_fechamento_cartao ?? '' }}"
                                    data-vencimento="{{ $b->dia_vencimento_cartao ?? '' }}"
                                    data-limite="{{ $b->limite_cartao ?? 0 }}"
                                    {{ $bancoId == $b->id ? 'selected' : '' }}>{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Forma de Pagamento</label>
                        <select name="tipo_pagamento" class="form-control" id="manual-tipo-pag"
                                onchange="onTipoPagLanc(this,'manual-banco','manual-info-cartao','manual-parcelas-row','manual-parcelas-input')">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="debito">💳 Cartão de Débito</option>
                            <option value="credito">💳 Cartão de Crédito</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="boleto">🧾 Boleto Bancário</option>
                        </select>
                    </div>

                    {{-- Bloco cartão de crédito: aparece quando tipo = credito --}}
                    <div id="manual-info-cartao" style="display:none;grid-column:span 2;">
                        <div id="manual-aviso-fatura" style="padding:10px 14px;border-radius:7px;background:var(--color-warning-soft);border:1px solid var(--color-amber);font-size:12px;margin-bottom:10px;line-height:1.6;">
                            <i class="fa-solid fa-credit-card" style="color:var(--color-amber);"></i>
                            <strong>Compra no Cartão de Crédito</strong><br>
                            <span id="manual-aviso-fatura-texto">Configure o dia de fechamento e vencimento do cartão nas configurações do banco para cálculo automático de faturas.</span>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label class="form-label">Nº de Parcelas</label>
                                <input type="number" name="parcelas" id="manual-parcelas-input" value="1" min="1" max="48" class="form-control"
                                       oninput="onParcelasLanc(this,'manual-aviso-fatura-texto','manual-banco')">
                            </div>
                            <div style="flex:1;">
                                <label class="form-label" style="color:var(--color-text-muted);font-size:11px;">Valor por parcela</label>
                                <div id="manual-valor-parcela" style="padding:8px 12px;background:var(--color-bg-inset);border-radius:6px;font-weight:700;color:var(--color-text);font-size:13px;">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="grid-column:span 2;">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2" maxlength="120" placeholder="Descrição do lançamento..."></textarea>
                    </div>
                </div>
                <div style="text-align:right;margin-top:16px;">
                    <button type="button" onclick="closeModal('modal-manual')" class="btn btn-secondary" style="margin-right:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Salvar Despesa</button>
                </div>
            </form>

            {{-- Formulário Receita --}}
            <form method="POST" action="{{ route('receitas.store') }}" id="form-receita" style="display:none;">
                @csrf
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Valor (R$) *</label><input type="number" name="valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00"></div>
                    <div class="form-group"><label class="form-label">Data Prevista *</label><input type="date" name="data_prevista_recebimento" class="form-control" required value="{{ now()->format('Y-m-d') }}"></div>
                    <div class="form-group"><label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach(App\Models\Categoria::where('tipo','RECEITA')->orderBy('nome')->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Quem Recebeu</label>
                        <select name="quem_recebeu" class="form-control"><option value="">🏠 Todos da Casa</option>@foreach($familiares as $fam)<option value="{{ $fam->id }}" {{ $fam->id == $meuFamiliarId ? 'selected' : '' }}>{{ $fam->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Conta de Destino</label>
                        <select name="forma_recebimento" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)<option value="{{ $b->id }}" {{ $bancoId == $b->id ? 'selected' : '' }}>{{ $b->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Forma de Recebimento</label>
                        <select name="tipo_pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="deposito">🏦 Depósito Bancário</option>
                            <option value="outros">📌 Outros</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:span 2;">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2" maxlength="120" placeholder="Descrição..."></textarea>
                    </div>
                </div>
                <div style="text-align:right;margin-top:16px;">
                    <button type="button" onclick="closeModal('modal-manual')" class="btn btn-secondary" style="margin-right:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-check"></i> Salvar Receita</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 2: Confirmação Cupom / NF                                        --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-backdrop" id="modal-cupom">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <i class="fa-solid fa-receipt" style="color:var(--color-success);"></i>
            <h3>Confirmar Lançamento — Cupom/NF</h3>
            <button class="modal-close" onclick="closeModal('modal-cupom')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="scan-info-box" style="display:none;background:var(--color-success-soft);border:1px solid var(--color-success);border-radius:8px;padding:12px;margin-bottom:16px;font-size:12px;"></div>
            <form method="POST" action="{{ route('despesas.store') }}" id="form-cupom">
                @csrf
                <input type="hidden" name="origem" value="ocr">
                <input type="hidden" name="numero_documento" id="scan-numero-documento">
                <div class="form-grid">
                    <div class="form-group"><label class="form-label">Valor (R$) *</label><input type="number" name="valor" id="scan-valor" step="0.01" min="0.01" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Data *</label><input type="date" name="data_compra" id="scan-data" class="form-control" required></div>
                    <div class="form-group"><label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-control"><option value="">— Selecione —</option>@foreach($categorias as $cat)<option value="{{ $cat->id }}">{{ $cat->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Estabelecimento</label>
                        <select name="onde_comprou" id="scan-fornecedor" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($fornecedores as $f)<option value="{{ $f->id }}" data-cnpj="{{ $f->cnpj ?? '' }}" data-nome="{{ strtolower($f->nome) }}">{{ $f->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Quem Comprou</label>
                        <select name="quem_comprou" class="form-control"><option value="">🏠 Todos da Casa</option>@foreach($familiares as $fam)<option value="{{ $fam->id }}" {{ $fam->id == $meuFamiliarId ? 'selected' : '' }}>{{ $fam->nome }}</option>@endforeach</select>
                    </div>
                    <div class="form-group"><label class="form-label">Conta / Banco</label>
                        <select name="forma_pagamento" id="scan-banco" class="form-control"
                                onchange="onBancoChangeLanc(this,'scan-tipo-pagamento','scan-info-cartao','scan-parcelas-row','scan-parcelas-input')">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}"
                                    data-nome="{{ strtolower($b->nome) }}"
                                    data-credito="{{ $b->tem_cartao_credito ? 1 : 0 }}"
                                    data-fechamento="{{ $b->dia_fechamento_cartao ?? '' }}"
                                    data-vencimento="{{ $b->dia_vencimento_cartao ?? '' }}"
                                    data-limite="{{ $b->limite_cartao ?? 0 }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label class="form-label">Forma de Pagamento</label>
                        <select name="tipo_pagamento" id="scan-tipo-pagamento" class="form-control"
                                onchange="onTipoPagLanc(this,'scan-banco','scan-info-cartao','scan-parcelas-row','scan-parcelas-input')">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="debito">💳 Cartão de Débito</option>
                            <option value="credito">💳 Cartão de Crédito</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="boleto">🧾 Boleto Bancário</option>
                        </select>
                    </div>

                    {{-- Bloco cartão de crédito (cupom) --}}
                    <div id="scan-info-cartao" style="display:none;grid-column:span 2;">
                        <div id="scan-aviso-fatura" style="padding:10px 14px;border-radius:7px;background:var(--color-warning-soft);border:1px solid var(--color-amber);font-size:12px;margin-bottom:10px;line-height:1.6;">
                            <i class="fa-solid fa-credit-card" style="color:var(--color-amber);"></i>
                            <strong>Compra no Cartão de Crédito</strong><br>
                            <span id="scan-aviso-fatura-texto">Configure o fechamento e vencimento do cartão nas configurações do banco.</span>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label class="form-label">Nº de Parcelas</label>
                                <input type="number" name="parcelas" id="scan-parcelas-input" value="1" min="1" max="48" class="form-control"
                                       oninput="onParcelasLanc(this,'scan-aviso-fatura-texto','scan-banco')">
                            </div>
                            <div style="flex:1;">
                                <label class="form-label" style="color:var(--color-text-muted);font-size:11px;">Valor por parcela</label>
                                <div id="scan-valor-parcela" style="padding:8px 12px;background:var(--color-bg-inset);border-radius:6px;font-weight:700;color:var(--color-text);font-size:13px;">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="grid-column:span 2;"><label class="form-label">Observações</label>
                        <textarea name="observacoes" id="scan-obs" class="form-control" rows="2" maxlength="120"></textarea>
                    </div>
                </div>
                <div style="text-align:right;margin-top:16px;">
                    <button type="button" onclick="closeModal('modal-cupom')" class="btn btn-secondary" style="margin-right:8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Confirmar Lançamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- MODAL 3: Importar Extrato OFX / CSV                                    --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<div class="modal-backdrop" id="modal-importar">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <i class="fa-solid fa-file-arrow-up" style="color:var(--color-info);"></i>
            <h3>Importar Extrato Bancário</h3>
            <button class="modal-close" onclick="fecharImportacao()">&times;</button>
        </div>
        <div class="modal-body">

            {{-- Passo 1: Upload --}}
            <div id="import-step-1">
                <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px;">
                    Selecione o banco de destino e faça upload do arquivo exportado pelo seu internet banking.<br>
                    <strong>Formatos suportados:</strong> OFX, OFC, QFX (Open Financial Exchange) e CSV.
                </p>
                <div class="form-grid" style="margin-bottom:16px;">
                    <div class="form-group">
                        <label class="form-label">Conta / Banco *</label>
                        <select id="import-banco-id" class="form-control">
                            <option value="">— Selecione a conta —</option>
                            @foreach($bancos as $b)<option value="{{ $b->id }}">{{ $b->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Arquivo do Extrato *</label>
                        <input type="file" id="import-arquivo" class="form-control" accept=".ofx,.ofc,.qfx,.csv,.txt">
                    </div>
                </div>
                <div style="background:var(--color-info-soft);border:1px solid var(--color-info);border-radius:8px;padding:12px;font-size:11px;color:var(--color-info);margin-bottom:16px;">
                    <strong><i class="fa-solid fa-circle-info"></i> Como exportar o extrato:</strong><br>
                    • <strong>Bradesco / BB:</strong> Internet Banking → Extrato → Exportar OFX<br>
                    • <strong>Itaú:</strong> Itaú Online → Extrato → Baixar arquivo OFX<br>
                    • <strong>Nubank:</strong> App → Configurações → Exportar extrato (CSV)<br>
                    • <strong>Caixa:</strong> Internet Banking → Extrato → Exportar
                </div>
                <div style="text-align:right;">
                    <button type="button" onclick="fecharImportacao()" class="btn btn-secondary" style="margin-right:8px;">Cancelar</button>
                    <button type="button" onclick="processarArquivo()" class="btn" style="background:var(--color-info);color:#fff;"><i class="fa-solid fa-magnifying-glass"></i> Analisar Arquivo</button>
                </div>
            </div>

            {{-- Passo 2: Preview das transações --}}
            <div id="import-step-2" style="display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div>
                        <div class="fw-600" style="font-size:14px;" id="import-resumo-texto"></div>
                        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">Marque as transações que deseja importar</div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="selecionarTodos(true)" class="btn btn-secondary btn-sm">Selecionar Todos</button>
                        <button type="button" onclick="selecionarTodos(false)" class="btn btn-secondary btn-sm">Desmarcar Todos</button>
                    </div>
                </div>
                <div style="max-height:320px;overflow-y:auto;border:1px solid var(--color-border);border-radius:8px;">
                    <table class="table" style="margin:0;font-size:12px;">
                        <thead style="position:sticky;top:0;background:var(--color-bg-container);z-index:1;">
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="check-all" onchange="selecionarTodos(this.checked)"></th>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th style="text-align:right;">Valor</th>
                                <th>Tipo</th>
                            </tr>
                        </thead>
                        <tbody id="import-tbody"></tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('lancamentos.confirmar-importacao') }}" id="form-importacao">
                    @csrf
                    <input type="hidden" name="banco_id" id="import-banco-id-hidden">
                    <div id="import-campos-hidden"></div>
                    <div style="text-align:right;margin-top:16px;">
                        <button type="button" onclick="voltarImportacao()" class="btn btn-secondary" style="margin-right:8px;"><i class="fa-solid fa-arrow-left"></i> Voltar</button>
                        <button type="submit" class="btn" style="background:var(--color-info);color:#fff;" id="btn-confirmar-importacao">
                            <i class="fa-solid fa-file-import"></i> Importar Selecionados
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- JAVASCRIPT                                                             --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>

// ── Tabs Manual (Despesa / Receita) ──────────────────────────────────────────
function alternarTipo(tipo) {
    const tabD = document.getElementById('tab-despesa');
    const tabR = document.getElementById('tab-receita');
    const formD = document.getElementById('form-despesa');
    const formR = document.getElementById('form-receita');
    const ativo = 'border-bottom:2px solid var(--color-primary);color:var(--color-primary);margin-bottom:-2px;';
    const inativo = 'border-bottom:2px solid transparent;color:var(--color-text-subtle);margin-bottom:-2px;';
    tabD.style.cssText += tipo === 'despesa' ? ativo : inativo;
    tabR.style.cssText += tipo === 'receita' ? ativo : inativo;
    formD.style.display = tipo === 'despesa' ? '' : 'none';
    formR.style.display = tipo === 'receita' ? '' : 'none';
}

// ── Scanner de Cupom ─────────────────────────────────────────────────────────
document.getElementById('camera-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    mostrarLoading('Processando imagem...');

    const reader = new FileReader();
    reader.onload = function(ev) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const max = 1600;
            let w = img.width, h = img.height;
            if (w > max || h > max) { if (w > h) { h = h * max / w; w = max; } else { w = w * max / h; h = max; } }
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            // Tenta QR Code
            const imageData = ctx.getImageData(0, 0, w, h);
            const qr = jsQR(imageData.data, w, h);

            if (qr) {
                atualizarLoading('QR Code detectado! Consultando...');
                enviarParaServidor({ qr_code: qr.data });
            } else {
                atualizarLoading('Lendo cupom com IA...');
                const b64 = canvas.toDataURL('image/jpeg', 0.85).split(',')[1];
                enviarParaServidor({ imagem: b64, mime: 'image/jpeg' });
            }
        };
        img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
    e.target.value = '';
});

function enviarParaServidor(payload) {
    fetch('{{ route("lancamentos.escanear") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(d => {
        esconderLoading();
        if (d.erro) { mostrarToast(d.erro, 7000); return; }
        preencherModalCupom(d);
    })
    .catch(() => { esconderLoading(); mostrarToast('Erro de comunicação. Tente novamente.', 5000); });
}

function preencherModalCupom(d) {
    document.getElementById('scan-valor').value = d.valor || '';
    document.getElementById('scan-data').value  = d.data  || '{{ now()->format("Y-m-d") }}';
    document.getElementById('scan-obs').value   = d.descricao || '';
    document.getElementById('scan-numero-documento').value = d.numero_cupom || '';

    const infoBox = document.getElementById('scan-info-box');
    let html = '<div style="font-weight:700;color:var(--color-success);margin-bottom:6px;"><i class="fa-solid fa-circle-check"></i> Dados detectados automaticamente</div>';
    if (d.estabelecimento) html += `<span style="background:var(--color-success-soft);color:var(--color-success);padding:2px 8px;border-radius:12px;margin-right:6px;">${d.estabelecimento}</span>`;
    if (d.cnpj)            html += `<span style="background:var(--color-bg-inset);color:var(--color-text-muted);padding:2px 8px;border-radius:12px;margin-right:6px;">CNPJ: ${d.cnpj}</span>`;
    if (d.numero_cupom)    html += `<span style="background:var(--color-bg-inset);color:var(--color-text-muted);padding:2px 8px;border-radius:12px;margin-right:6px;">Nº ${d.numero_cupom}</span>`;
    if (d.forma_pagamento) html += `<span style="background:var(--color-violet-soft);color:var(--color-violet);padding:2px 8px;border-radius:12px;">${d.forma_pagamento}</span>`;
    if (d.itens?.length)   html += `<div style="margin-top:8px;border-top:1px solid var(--color-success);padding-top:8px;"><strong>Itens:</strong> ${d.itens.slice(0,5).join(', ')}</div>`;
    infoBox.innerHTML = html;
    infoBox.style.display = '';

    // Auto-seleciona banco e fornecedor
    if (d.forma_pagamento) autoSelecionarBanco(d.forma_pagamento);
    if (d.cnpj || d.estabelecimento) autoSelecionarFornecedor(d.cnpj, d.estabelecimento);

    openModal('modal-cupom');
}

function autoSelecionarBanco(texto) {
    const lower = texto.toLowerCase();
    const sel   = document.getElementById('scan-banco');
    const mapa  = { 'dinheiro': 'carteira', 'espécie': 'carteira', 'especie': 'carteira', 'pix': 'pix', 'crédito': 'crédito', 'credito': 'crédito', 'débito': 'débito', 'debito': 'débito' };
    for (const [k, v] of Object.entries(mapa)) {
        if (lower.includes(k)) {
            for (const opt of sel.options) { if (opt.dataset.nome?.includes(v)) { sel.value = opt.value; return; } }
        }
    }
}

function autoSelecionarFornecedor(cnpj, nome) {
    const sel = document.getElementById('scan-fornecedor');
    const cnpjLimpo = (cnpj || '').replace(/\D/g, '');
    const nomeLower = (nome || '').toLowerCase().slice(0, 5);
    for (const opt of sel.options) {
        if (cnpjLimpo && opt.dataset.cnpj?.replace(/\D/g,'') === cnpjLimpo) { sel.value = opt.value; return; }
        if (nomeLower && opt.dataset.nome?.startsWith(nomeLower)) { sel.value = opt.value; return; }
    }
}

// ── Importação de Extrato ────────────────────────────────────────────────────
let transacoesImportadas = [];

function processarArquivo() {
    const bancoId  = document.getElementById('import-banco-id').value;
    const arquivo  = document.getElementById('import-arquivo').files[0];
    if (!bancoId)  { mostrarToast('Selecione o banco/conta.', 4000); return; }
    if (!arquivo)  { mostrarToast('Selecione um arquivo.', 4000); return; }

    mostrarLoading('Analisando arquivo...');
    const fd = new FormData();
    fd.append('arquivo', arquivo);
    fd.append('banco_id', bancoId);
    fd.append('_token', '{{ csrf_token() }}');

    fetch('{{ route("lancamentos.importar-extrato") }}', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        esconderLoading();
        if (d.erro) { mostrarToast(d.erro, 6000); return; }
        transacoesImportadas = d.transacoes;
        document.getElementById('import-banco-id-hidden').value = bancoId;
        renderizarPreview(d.transacoes, d.total);
    })
    .catch(() => { esconderLoading(); mostrarToast('Erro ao processar arquivo.', 5000); });
}

function renderizarPreview(lista, total) {
    document.getElementById('import-resumo-texto').textContent = `${total} transações encontradas`;
    const tbody = document.getElementById('import-tbody');
    tbody.innerHTML = '';

    lista.forEach((t, i) => {
        const cor  = t.tipo === 'credito' ? 'var(--color-success)' : 'var(--color-danger)';
        const sinal = t.tipo === 'credito' ? '+' : '-';
        const tipoLabel = t.tipo === 'credito' ? '<span style="color:var(--color-success);font-weight:600;">Entrada</span>' : '<span style="color:var(--color-danger);font-weight:600;">Saída</span>';
        tbody.innerHTML += `
        <tr>
            <td><input type="checkbox" name="check_${i}" checked data-idx="${i}"></td>
            <td style="white-space:nowrap;">${t.data ? t.data.split('-').reverse().join('/') : '—'}</td>
            <td style="max-width:260px;word-break:break-word;">${t.descricao || '—'}</td>
            <td style="text-align:right;color:${cor};font-weight:700;">${sinal} R$ ${parseFloat(t.valor).toFixed(2).replace('.',',')}</td>
            <td>${tipoLabel}</td>
        </tr>`;
    });

    document.getElementById('import-step-1').style.display = 'none';
    document.getElementById('import-step-2').style.display = '';
}

function selecionarTodos(val) {
    document.querySelectorAll('#import-tbody input[type=checkbox]').forEach(cb => cb.checked = val);
    const ca = document.getElementById('check-all');
    if (ca) ca.checked = val;
}

document.getElementById('form-importacao').addEventListener('submit', function() {
    const container = document.getElementById('import-campos-hidden');
    container.innerHTML = '';
    const checkboxes = document.querySelectorAll('#import-tbody input[type=checkbox]');
    checkboxes.forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        const t   = transacoesImportadas[idx];
        if (!t || !cb.checked) return;
        const add = (name, val) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name; inp.value = val ?? '';
            container.appendChild(inp);
        };
        add(`transacoes[${idx}][data]`,      t.data);
        add(`transacoes[${idx}][valor]`,     t.valor);
        add(`transacoes[${idx}][tipo]`,      t.tipo);
        add(`transacoes[${idx}][descricao]`, t.descricao);
        add(`transacoes[${idx}][fitid]`,     t.fitid);
        add(`transacoes[${idx}][importar]`,  '1');
    });
});

function voltarImportacao() {
    document.getElementById('import-step-1').style.display = '';
    document.getElementById('import-step-2').style.display = 'none';
}

function fecharImportacao() {
    voltarImportacao();
    closeModal('modal-importar');
}

// ── Date Picker Customizado ──────────────────────────────────────────────────
function toggleDatePicker() {
    const wrapper = document.getElementById('date-picker-wrapper');
    const label   = document.getElementById('mes-label');
    const rect    = label.getBoundingClientRect();
    wrapper.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
    wrapper.style.left = (rect.left  + window.scrollX)      + 'px';
    wrapper.style.display = wrapper.style.display === 'none' ? 'block' : 'none';
}

function aplicarPeriodoCustom() {
    const inicio = document.getElementById('dp-inicio').value;
    const fim    = document.getElementById('dp-fim').value;
    if (!inicio || !fim) { mostrarToast('Selecione as duas datas.', 3000); return; }
    document.getElementById('f-inicio').value = inicio;
    document.getElementById('f-fim').value    = fim;
    document.getElementById('date-picker-wrapper').style.display = 'none';
    document.getElementById('form-filtro').submit();
}

// Fecha date picker ao clicar fora
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('date-picker-wrapper');
    const label   = document.getElementById('mes-label');
    if (!wrapper.contains(e.target) && e.target !== label && !label.contains(e.target)) {
        wrapper.style.display = 'none';
    }
});

// ── Cartão de Crédito: lógica de parcelas e faturas ─────────────────────────

/**
 * Calcula a data do primeiro vencimento da fatura com base na data de compra.
 * Regra real: se a compra foi ANTES ou NO dia de fechamento → próxima fatura (1 mês).
 *             se a compra foi APÓS o fechamento → fatura do mês seguinte (2 meses).
 */
function calcularPrimeiroVencimento(dataCompraStr, diaFechamento, diaVencimento) {
    if (!dataCompraStr || !diaFechamento || !diaVencimento) return null;
    const compra = new Date(dataCompraStr + 'T12:00:00');
    const diaCompra = compra.getDate();
    let ano  = compra.getFullYear();
    let mes  = compra.getMonth(); // 0-based

    if (diaCompra <= diaFechamento) {
        // Dentro do ciclo atual → vence no mês seguinte
        mes += 1;
    } else {
        // Passou do fechamento → próximo ciclo → vence em 2 meses
        mes += 2;
    }
    // Normaliza mês/ano
    if (mes > 11) { ano += Math.floor(mes / 12); mes = mes % 12; }
    const d = new Date(ano, mes, diaVencimento);
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function textoAvisoFatura(bancoSelect, idTexto, nParcelas) {
    const opt = bancoSelect ? bancoSelect.options[bancoSelect.selectedIndex] : null;
    const txtEl = document.getElementById(idTexto);
    if (!opt || !opt.value || !txtEl) return;

    const fechamento = opt.dataset.fechamento;
    const vencimento = opt.dataset.vencimento;
    const limite     = parseFloat(opt.dataset.limite) || 0;

    if (!fechamento || !vencimento) {
        txtEl.innerHTML = '⚠️ Configure o dia de fechamento e vencimento do cartão nas configurações do banco.';
        return;
    }

    // Tenta pegar a data de compra do formulário próximo
    const dataInputs = bancoSelect.closest('form').querySelectorAll('input[type="date"]');
    let dataCompraStr = '';
    dataInputs.forEach(inp => {
        if (inp.name === 'data_compra' || inp.name === 'data_prevista_recebimento') {
            dataCompraStr = inp.value;
        }
    });
    if (!dataCompraStr) dataCompraStr = new Date().toISOString().substring(0,10);

    const primeiroVenc = calcularPrimeiroVencimento(dataCompraStr, parseInt(fechamento), parseInt(vencimento));
    const compraDay   = parseInt(dataCompraStr.split('-')[2]);
    const aviso       = compraDay > parseInt(fechamento)
        ? `⚠️ Compra feita após o fechamento (dia ${fechamento}) — entra na <strong>próxima fatura</strong>.`
        : `✅ Compra dentro do ciclo atual (fechamento dia ${fechamento}).`;

    const parcelaInfo = nParcelas > 1
        ? `<br>📅 <strong>${nParcelas}x</strong> — 1ª parcela vence em <strong>${primeiroVenc}</strong>`
        : `<br>📅 Vencimento da fatura: <strong>${primeiroVenc}</strong>`;

    const limiteInfo = limite > 0
        ? `<br>💳 Limite do cartão: <strong>R$ ${limite.toFixed(2).replace('.',',')}</strong>`
        : '';

    txtEl.innerHTML = aviso + parcelaInfo + limiteInfo;
}

/**
 * Chamado quando o banco é alterado — atualiza dicas de cartão se já estiver em modo crédito.
 */
function onBancoChangeLanc(bancoSelect, idTipoPag, idInfoDiv, idParcelasRow, idParcelasInput) {
    const tipo = document.getElementById(idTipoPag);
    if (tipo && tipo.value === 'credito') {
        const n = parseInt(document.getElementById(idParcelasInput)?.value) || 1;
        textoAvisoFatura(bancoSelect, idInfoDiv.replace('-info-cartao','-aviso-fatura-texto'), n);
    }
}

/**
 * Chamado quando o tipo de pagamento é alterado.
 * Mostra/oculta bloco de cartão de crédito e preenche as dicas.
 */
function onTipoPagLanc(tipoPagSelect, idBanco, idInfoDiv, idParcelasRow, idParcelasInput) {
    const infoDiv     = document.getElementById(idInfoDiv);
    const bancoSelect = document.getElementById(idBanco);
    const isCredito   = tipoPagSelect.value === 'credito';

    if (infoDiv) infoDiv.style.display = isCredito ? 'block' : 'none';

    if (isCredito) {
        const n = parseInt(document.getElementById(idParcelasInput)?.value) || 1;
        // deriva o id do span de texto do aviso
        const idTexto = idInfoDiv.replace('-info-cartao', '-aviso-fatura-texto');
        textoAvisoFatura(bancoSelect, idTexto, n);
        atualizarValorParcela(idBanco, idParcelasInput, idInfoDiv);
    }
}

/**
 * Chamado ao alterar o número de parcelas — atualiza aviso e valor/parcela.
 */
function onParcelasLanc(parcelasInput, idTexto, idBanco) {
    const bancoSelect = document.getElementById(idBanco);
    const n           = parseInt(parcelasInput.value) || 1;
    textoAvisoFatura(bancoSelect, idTexto, n);

    // Atualiza campo valor/parcela — busca o campo valor do mesmo form
    const form  = parcelasInput.closest('form');
    const valor = parseFloat(form.querySelector('input[name="valor"]')?.value) || 0;
    // id do div de valor parcela = prefixo do form
    const prefix = parcelasInput.id.replace('-parcelas-input','');
    const divVP  = document.getElementById(prefix + '-valor-parcela');
    if (divVP && valor > 0) {
        const vp = (valor / n).toFixed(2).replace('.',',');
        divVP.textContent = n > 1 ? `R$ ${vp} × ${n}` : `R$ ${valor.toFixed(2).replace('.',',')} (à vista)`;
    }
}

function atualizarValorParcela(idBanco, idParcelasInput, idInfoDiv) {
    const form     = document.getElementById(idBanco)?.closest('form');
    if (!form) return;
    const valor    = parseFloat(form.querySelector('input[name="valor"]')?.value) || 0;
    const n        = parseInt(document.getElementById(idParcelasInput)?.value) || 1;
    const prefix   = idParcelasInput.replace('-parcelas-input','');
    const divVP    = document.getElementById(prefix + '-valor-parcela');
    if (divVP && valor > 0) {
        const vp = (valor / n).toFixed(2).replace('.',',');
        divVP.textContent = n > 1 ? `R$ ${vp} × ${n}` : `R$ ${valor.toFixed(2).replace('.',',')} (à vista)`;
    }
}

// Atualiza valor/parcela ao digitar o valor quando cartão crédito está ativo
document.addEventListener('DOMContentLoaded', function() {
    ['form-despesa','form-cupom'].forEach(function(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        const valorInput = form.querySelector('input[name="valor"]');
        if (!valorInput) return;
        valorInput.addEventListener('input', function() {
            const tipoPag = form.querySelector('select[name="tipo_pagamento"]');
            if (!tipoPag || tipoPag.value !== 'credito') return;
            const prefix      = formId === 'form-despesa' ? 'manual' : 'scan';
            const parcelasInp = document.getElementById(prefix + '-parcelas-input');
            const idBanco     = formId === 'form-despesa' ? 'manual-banco' : 'scan-banco';
            if (parcelasInp) onParcelasLanc(parcelasInp, prefix + '-aviso-fatura-texto', idBanco);
        });
    });
});

function mostrarLoading(msg) {
    document.getElementById('scan-loading-msg').textContent = msg;
    document.getElementById('scan-loading').style.display = 'flex';
}
function atualizarLoading(msg) { document.getElementById('scan-loading-msg').textContent = msg; }
function esconderLoading()     { document.getElementById('scan-loading').style.display = 'none'; }

function mostrarToast(msg, ms = 5000) {
    const t = document.getElementById('scan-toast');
    t.textContent = msg; t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, ms);
}
</script>

@endsection
