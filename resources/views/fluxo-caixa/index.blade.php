@extends('layouts.main')
@section('title', 'Contas a Pagar / Receber')
@section('page-title', 'Contas a Pagar / Receber')

@section('content')

{{-- ─── Filtro de período ─────────────────────────────────────────────────── --}}
<style>
.fc-filtro { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.fc-atalhos { display:flex; gap:6px; }
.fc-atalho-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:8px; font-size:12px; font-weight:700;
    text-decoration:none; border:1px solid var(--color-border);
    transition:all .15s; white-space:nowrap;
}
.fc-atalho-btn.ativo { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }
.fc-atalho-btn:not(.ativo) { background:var(--color-bg-card); color:var(--color-text-muted); }
.fc-atalho-btn:not(.ativo):hover { background:var(--color-bg-container); }
.fc-custom { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.fc-periodo-label {
    display:flex; align-items:center; gap:6px;
    font-size:11px; color:var(--color-text-subtle); padding-top:8px;
    border-top:1px solid var(--color-border); margin-top:10px;
}
@media (max-width:640px) {
    .fc-filtro { flex-direction:column; align-items:stretch; }
    .fc-atalhos { justify-content:center; }
    .fc-custom { justify-content:center; }
    .fc-custom input.form-control { max-width:calc(50% - 24px) !important; }
}
.fc-kpi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:12px; }
@media (max-width:640px) { .fc-kpi-grid { grid-template-columns:1fr; } }
</style>

<div class="card filtros-bar">
    <div class="fc-filtro">

        {{-- Atalhos de período --}}
        <div class="fc-atalhos">
            <a href="{{ route('fluxo-caixa.index', ['periodo' => 'semana']) }}"
               class="fc-atalho-btn {{ $periodo === 'semana' ? 'ativo' : '' }}">
                <i class="fa-solid fa-calendar-week"></i> Esta Semana
            </a>
            <a href="{{ route('fluxo-caixa.index', ['periodo' => 'mes']) }}"
               class="fc-atalho-btn {{ $periodo === 'mes' ? 'ativo' : '' }}">
                <i class="fa-solid fa-calendar"></i> Este Mês
            </a>
        </div>

        {{-- Período personalizado --}}
        <form method="GET" action="{{ route('fluxo-caixa.index') }}" id="form-periodo">
            <input type="hidden" name="periodo" value="custom">
            <div class="fc-custom">
                <input type="date" name="inicio" value="{{ $inicio }}" class="form-control"
                       style="max-width:138px;font-size:12px;">
                <span style="color:var(--color-text-subtle);font-size:13px;">→</span>
                <input type="date" name="fim" value="{{ $fim }}" class="form-control"
                       style="max-width:138px;font-size:12px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-check"></i> Aplicar
                </button>
            </div>
        </form>

    </div>

    {{-- Período ativo exibido --}}
    <div class="fc-periodo-label">
        <i class="fa-regular fa-calendar" style="color:var(--color-text-faint);"></i>
        {{ \Carbon\Carbon::parse($inicio)->locale('pt_BR')->isoFormat('D [de] MMMM') }}
        <span style="color:var(--color-text-faint);">→</span>
        {{ \Carbon\Carbon::parse($fim)->locale('pt_BR')->isoFormat('D [de] MMMM [de] YYYY') }}
        @if(in_array($periodo, ['semana','mes']))
        <span style="background:{{ $periodo === 'semana' ? 'var(--color-violet-soft)' : 'var(--color-info-soft)' }};color:{{ $periodo === 'semana' ? 'var(--color-violet)' : 'var(--color-info)' }};font-size:10px;font-weight:700;padding:1px 8px;border-radius:20px;margin-left:4px;">
            {{ $periodo === 'semana' ? 'Esta Semana' : 'Este Mês' }}
        </span>
        @endif
    </div>
</div>

{{-- ─── KPIs ──────────────────────────────────────────────────────────────── --}}
<div class="fc-kpi-grid">

    <div class="card" style="border-top:3px solid var(--color-success);">
        <div class="kpi-label"><i class="fa-solid fa-arrow-trend-up" style="color:var(--color-success);"></i> A Receber</div>
        <div class="kpi-value text-green">R$ {{ number_format($totalAReceber,2,',','.') }}</div>
        @if($totalRecebido > 0)
        <div class="kpi-sub" style="color:var(--color-success);">
            <i class="fa-solid fa-check"></i> R$ {{ number_format($totalRecebido,2,',','.') }} já recebido
        </div>
        @endif
    </div>

    <div class="card" style="border-top:3px solid var(--color-danger);">
        <div class="kpi-label"><i class="fa-solid fa-arrow-trend-down" style="color:var(--color-danger);"></i> A Pagar</div>
        <div class="kpi-value text-red">R$ {{ number_format($totalAPagar,2,',','.') }}</div>
        @if($totalPago > 0)
        <div class="kpi-sub" style="color:var(--color-success);">
            <i class="fa-solid fa-check"></i> R$ {{ number_format($totalPago,2,',','.') }} já pago
        </div>
        @endif
    </div>

    <div class="card" style="border-top:3px solid {{ $saldoProjetado >= 0 ? 'var(--color-indigo)' : 'var(--color-warning)' }};">
        <div class="kpi-label"><i class="fa-solid fa-scale-balanced" style="color:var(--color-primary);"></i> Saldo Projetado</div>
        <div class="kpi-value {{ $saldoProjetado >= 0 ? '' : 'text-red' }}" style="{{ $saldoProjetado >= 0 ? 'color:var(--color-primary)' : '' }}">
            R$ {{ number_format(abs($saldoProjetado),2,',','.') }}
        </div>
        <div class="kpi-sub">{{ $saldoProjetado >= 0 ? 'Superávit no período' : 'Déficit no período' }}</div>
    </div>

</div>

@php
    $totalDespesas = $despesas->count();
    $pagas = $despesas->whereNotNull('data_pagamento')->count();
    $totalReceitas = $receitas->count();
    $recebidas = $receitas->whereNotNull('data_recebimento')->count();
@endphp
<div class="card" style="border-top:3px solid var(--color-text-subtle);margin-bottom:20px;">
    <div class="kpi-label"><i class="fa-solid fa-list-check"></i> Progresso</div>
    <div style="margin-top:8px;display:flex;gap:24px;flex-wrap:wrap;">
        <div style="flex:1;min-width:180px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px;">
                <span class="text-muted">Pago</span>
                <span class="fw-600">{{ $pagas }}/{{ $totalDespesas }}</span>
            </div>
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width:{{ $totalDespesas > 0 ? round($pagas/$totalDespesas*100) : 0 }}%;background:var(--color-danger);"></div>
            </div>
        </div>
        <div style="flex:1;min-width:180px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:3px;">
                <span class="text-muted">Recebido</span>
                <span class="fw-600">{{ $recebidas }}/{{ $totalReceitas }}</span>
            </div>
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width:{{ $totalReceitas > 0 ? round($recebidas/$totalReceitas*100) : 0 }}%;background:var(--color-success);"></div>
            </div>
        </div>
    </div>
</div>

{{-- ─── Tabelas lado a lado ────────────────────────────────────────────────── --}}
<div class="grid-2">

    {{-- ── A RECEBER ──────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-title" style="color:var(--color-success);">
            <i class="fa-solid fa-arrow-trend-up" style="color:var(--color-success);"></i>
            A Receber
            <span class="badge badge-green" style="margin-left:auto;">{{ $receitas->count() }}</span>
        </div>

        @if($receitas->isEmpty())
            <div class="empty-state" style="padding:24px 0;">
                <i class="fa-solid fa-inbox" style="font-size:28px;"></i>
                <p>Nenhuma receita no período.</p>
            </div>
        @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th style="text-align:right;">Valor</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receitas as $r)
                    @php $recebida = !is_null($r->data_recebimento); @endphp
                    <tr style="{{ $recebida ? 'opacity:.55;' : '' }}">
                        <td>
                            @if($recebida)
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-success-soft);align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-check" style="color:var(--color-success);font-size:11px;"></i>
                                </span>
                            @elseif($r->status === 'vencido')
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-danger-soft);align-items:center;justify-content:center;" title="Vencido">
                                    <i class="fa-solid fa-exclamation" style="color:var(--color-danger);font-size:11px;"></i>
                                </span>
                            @else
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-success-soft);align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-clock" style="color:var(--color-success);font-size:10px;"></i>
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-600" style="font-size:12px;{{ $recebida ? 'text-decoration:line-through;' : '' }}">
                                {{ $r->observacoes ?? $r->categoria?->nome ?? '—' }}
                            </div>
                            @if($r->familiar)
                            <div style="font-size:10px;color:var(--color-text-subtle);">
                                <i class="fa-solid fa-user"></i> {{ $r->familiar->nome }}
                            </div>
                            @endif
                            @if($recebida)
                            <div style="font-size:10px;color:var(--color-success);">
                                Recebido em {{ $r->data_recebimento->format('d/m/Y') }}
                            </div>
                            @endif
                        </td>
                        <td style="white-space:nowrap;font-size:12px;">
                            {{ $r->data_prevista_recebimento->format('d/m') }}
                            @if($r->status === 'vencido' && !$recebida)
                            <div style="font-size:10px;color:var(--color-danger);">Vencido</div>
                            @endif
                        </td>
                        <td style="text-align:right;font-weight:700;color:var(--color-success);white-space:nowrap;font-size:13px;">
                            R$ {{ number_format($r->valor,2,',','.') }}
                        </td>
                        <td>
                            @if($recebida)
                                <form method="POST" action="{{ route('fluxo-caixa.estornar-receita', $r) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Estornar baixa"
                                        style="font-size:10px;color:var(--color-text-subtle);"
                                        onclick="return confirm('Desfazer o recebimento?')">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                </form>
                            @else
                                <button onclick="abrirBaixaReceita({{ $r->id }}, '{{ addslashes($r->observacoes ?? $r->categoria?->nome ?? 'Receita') }}', {{ $r->valor }})"
                                    class="btn btn-success btn-sm" style="font-size:11px;white-space:nowrap;">
                                    <i class="fa-solid fa-check"></i> Baixar
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── A PAGAR ─────────────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-title" style="color:var(--color-danger);">
            <i class="fa-solid fa-arrow-trend-down" style="color:var(--color-danger);"></i>
            A Pagar
            <span class="badge badge-red" style="margin-left:auto;">{{ $despesas->count() }}</span>
        </div>

        @if($despesas->isEmpty())
            <div class="empty-state" style="padding:24px 0;">
                <i class="fa-solid fa-inbox" style="font-size:28px;"></i>
                <p>Nenhuma despesa no período.</p>
            </div>
        @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:36px;"></th>
                        <th>Descrição</th>
                        <th>Vence</th>
                        <th style="text-align:right;">Valor</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($despesas as $d)
                    @php $paga = !is_null($d->data_pagamento); @endphp
                    <tr style="{{ $paga ? 'opacity:.55;' : '' }}">
                        <td>
                            @if($paga)
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-success-soft);align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-check" style="color:var(--color-success);font-size:11px;"></i>
                                </span>
                            @elseif($d->status === 'vencido')
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-danger-soft);align-items:center;justify-content:center;" title="Vencido">
                                    <i class="fa-solid fa-triangle-exclamation" style="color:var(--color-danger);font-size:10px;"></i>
                                </span>
                            @else
                                <span style="display:inline-flex;width:26px;height:26px;border-radius:50%;background:var(--color-danger-soft);align-items:center;justify-content:center;">
                                    <i class="fa-solid fa-clock" style="color:var(--color-danger);font-size:10px;"></i>
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-600" style="font-size:12px;{{ $paga ? 'text-decoration:line-through;' : '' }}">
                                {{ $d->observacoes ?? $d->fornecedor?->nome ?? $d->categoria?->nome ?? '—' }}
                            </div>
                            @if($d->banco)
                            <div style="font-size:10px;color:var(--color-text-subtle);">
                                <i class="fa-solid fa-credit-card"></i> {{ $d->banco->nome }}
                            </div>
                            @endif
                            @if($paga)
                            <div style="font-size:10px;color:var(--color-success);">
                                Pago em {{ $d->data_pagamento->format('d/m/Y') }}
                            </div>
                            @endif
                        </td>
                        <td style="white-space:nowrap;font-size:12px;">
                            {{ $d->data_compra->format('d/m') }}
                            @if($d->status === 'vencido' && !$paga)
                            <div style="font-size:10px;color:var(--color-danger);">Vencido</div>
                            @endif
                        </td>
                        <td style="text-align:right;font-weight:700;color:var(--color-danger);white-space:nowrap;font-size:13px;">
                            R$ {{ number_format($d->valor,2,',','.') }}
                        </td>
                        <td>
                            @if($paga)
                                <form method="POST" action="{{ route('fluxo-caixa.estornar-despesa', $d) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Estornar baixa"
                                        style="font-size:10px;color:var(--color-text-subtle);"
                                        onclick="return confirm('Desfazer o pagamento?')">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                </form>
                            @else
                                <button onclick="abrirBaixaDespesa({{ $d->id }}, '{{ addslashes($d->observacoes ?? $d->fornecedor?->nome ?? $d->categoria?->nome ?? 'Despesa') }}', {{ $d->valor }})"
                                    class="btn btn-danger btn-sm" style="font-size:11px;white-space:nowrap;">
                                    <i class="fa-solid fa-check"></i> Baixar
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>{{-- /grid-2 --}}

{{-- ─── Modal Baixar Receita ──────────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-baixa-receita">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <i class="fa-solid fa-check-circle" style="color:var(--color-success);"></i>
            <h3>Confirmar Recebimento</h3>
            <button class="modal-close" onclick="closeModal('modal-baixa-receita')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="baixa-receita-desc"
                 style="font-size:13px;font-weight:600;margin-bottom:4px;color:var(--color-text);"></div>
            <div id="baixa-receita-previsto"
                 style="font-size:12px;color:var(--color-text-subtle);margin-bottom:14px;"></div>
            <form method="POST" action="" id="form-baixa-receita">
                @csrf
                <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="form-group">
                        <label class="form-label">Valor Recebido *</label>
                        <input type="number" name="valor" id="baixa-receita-valor"
                               class="form-control" required step="0.01" min="0.01" inputmode="decimal">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Recebimento *</label>
                        <input type="date" name="data_recebimento" id="baixa-receita-data"
                               class="form-control" required value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>
                <div id="baixa-receita-diff"
                     style="font-size:12px;margin:-2px 0 10px 2px;min-height:16px;"></div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-baixa-receita')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check"></i> Confirmar Recebimento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ─── Modal Baixar Despesa ──────────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-baixa-despesa">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <i class="fa-solid fa-check-circle" style="color:var(--color-danger);"></i>
            <h3>Confirmar Pagamento</h3>
            <button class="modal-close" onclick="closeModal('modal-baixa-despesa')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="baixa-despesa-desc"
                 style="font-size:13px;font-weight:600;margin-bottom:4px;color:var(--color-text);"></div>
            <div id="baixa-despesa-previsto"
                 style="font-size:12px;color:var(--color-text-subtle);margin-bottom:14px;"></div>
            <form method="POST" action="" id="form-baixa-despesa">
                @csrf
                <div class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div class="form-group">
                        <label class="form-label">Valor Pago *</label>
                        <input type="number" name="valor" id="baixa-despesa-valor"
                               class="form-control" required step="0.01" min="0.01" inputmode="decimal">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Pagamento *</label>
                        <input type="date" name="data_pagamento" id="baixa-despesa-data"
                               class="form-control" required value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>
                <div id="baixa-despesa-diff"
                     style="font-size:12px;margin:-2px 0 10px 2px;min-height:16px;"></div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-baixa-despesa')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-check"></i> Confirmar Pagamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function fmtBRL(v) {
    return 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function atualizarDiffBaixa(prefix, valorPrevisto) {
    const inp  = document.getElementById(`${prefix}-valor`);
    const out  = document.getElementById(`${prefix}-diff`);
    if (!inp || !out) return;
    const novo = parseFloat(inp.value);
    if (isNaN(novo) || novo <= 0) { out.textContent = ''; return; }
    const diff = +(novo - valorPrevisto).toFixed(2);
    if (diff === 0) {
        out.innerHTML = `<i class="fa-solid fa-check" style="color:var(--color-success);"></i> <span style="color:var(--color-text-subtle);">Valor igual ao previsto.</span>`;
    } else if (diff > 0) {
        out.innerHTML = `<i class="fa-solid fa-arrow-up" style="color:var(--color-success);"></i> <span style="color:var(--color-success);font-weight:600;">${fmtBRL(diff)} a mais</span> <span style="color:var(--color-text-subtle);">que o previsto.</span>`;
    } else {
        out.innerHTML = `<i class="fa-solid fa-arrow-down" style="color:var(--color-danger);"></i> <span style="color:var(--color-danger);font-weight:600;">${fmtBRL(Math.abs(diff))} a menos</span> <span style="color:var(--color-text-subtle);">que o previsto.</span>`;
    }
}

function abrirBaixaReceita(id, desc, valor) {
    document.getElementById('form-baixa-receita').action = `/fluxo-caixa/baixar-receita/${id}`;
    document.getElementById('baixa-receita-desc').textContent = desc;
    document.getElementById('baixa-receita-previsto').textContent = `Valor previsto: ${fmtBRL(valor)}`;
    const inp = document.getElementById('baixa-receita-valor');
    inp.value = Number(valor).toFixed(2);
    inp.dataset.previsto = String(valor);
    inp.oninput = () => atualizarDiffBaixa('baixa-receita', valor);
    document.getElementById('baixa-receita-diff').textContent = '';
    document.getElementById('baixa-receita-data').value = '{{ now()->format('Y-m-d') }}';
    openModal('modal-baixa-receita');
}

function abrirBaixaDespesa(id, desc, valor) {
    document.getElementById('form-baixa-despesa').action = `/fluxo-caixa/baixar-despesa/${id}`;
    document.getElementById('baixa-despesa-desc').textContent = desc;
    document.getElementById('baixa-despesa-previsto').textContent = `Valor previsto: ${fmtBRL(valor)}`;
    const inp = document.getElementById('baixa-despesa-valor');
    inp.value = Number(valor).toFixed(2);
    inp.dataset.previsto = String(valor);
    inp.oninput = () => atualizarDiffBaixa('baixa-despesa', valor);
    document.getElementById('baixa-despesa-diff').textContent = '';
    document.getElementById('baixa-despesa-data').value = '{{ now()->format('Y-m-d') }}';
    openModal('modal-baixa-despesa');
}
</script>
@endpush
