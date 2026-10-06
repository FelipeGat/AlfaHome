@extends('layouts.main')
@section('title', 'Investimentos')
@section('page-title', 'Investimentos')

@section('content')

{{-- ── KPIs ──────────────────────────────────────────────────────────────── --}}
<style>
.inv-kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
@media (max-width:640px) { .inv-kpi-row { grid-template-columns:1fr; } }
</style>

{{-- Total Aportado — linha própria --}}
<div class="card" style="padding:16px 20px;margin-bottom:14px;border-left:4px solid var(--color-amber);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
    <div>
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Total Aportado</div>
        <div style="font-size:24px;font-weight:700;color:var(--color-amber);">{{ brl($totalAportado) }}</div>
    </div>
    <div style="font-size:12px;color:var(--color-text-muted);background:var(--color-warning-soft);padding:6px 12px;border-radius:20px;font-weight:600;">
        <i class="fa-solid fa-seedling" style="color:var(--color-amber);"></i>
        {{ $investimentos->count() }} ativo{{ $investimentos->count() !== 1 ? 's' : '' }} na carteira
    </div>
</div>

{{-- Valor Atual | Rendimento Total | Melhor Ativo — mesma linha --}}
<div class="inv-kpi-row">

    <div class="card" style="padding:16px 18px;">
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Valor Atual</div>
        <div style="font-size:22px;font-weight:700;color:{{ $totalAtual >= $totalAportado ? 'var(--color-success)' : 'var(--color-danger)' }};">
            {{ brl($totalAtual) }}
        </div>
        <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">Posição consolidada</div>
    </div>

    <div class="card" style="padding:16px 18px;">
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Rendimento Total</div>
        <div style="font-size:22px;font-weight:700;color:{{ $ganhoTotal >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
            {{ $ganhoTotal >= 0 ? '+' : '' }}{{ brl(abs($ganhoTotal)) }}
        </div>
        <div style="font-size:11px;color:{{ $ganhoPercent >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};margin-top:2px;font-weight:600;">
            <i class="fa-solid {{ $ganhoPercent >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
            {{ $ganhoPercent >= 0 ? '+' : '' }}{{ number_format(abs($ganhoPercent), 2, ',', '.') }}%
        </div>
    </div>

    @if($investimentos->count() > 0)
    @php $melhor = $investimentos->sortByDesc('ganho_percentual')->first(); @endphp
    <div class="card" style="padding:16px 18px;">
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Melhor Ativo</div>
        <div style="font-size:14px;font-weight:700;color:var(--color-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $melhor->nome_ativo }}</div>
        <div style="font-size:13px;color:var(--color-success);margin-top:2px;font-weight:600;">
            {{ $melhor->ganho_percentual >= 0 ? '+' : '' }}{{ number_format($melhor->ganho_percentual, 2, ',', '.') }}%
        </div>
    </div>
    @else
    <div class="card" style="padding:16px 18px;opacity:.4;">
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">Melhor Ativo</div>
        <div style="font-size:14px;font-weight:600;color:var(--color-text-muted);">—</div>
    </div>
    @endif

</div>

{{-- ── Toolbar ───────────────────────────────────────────────────────────── --}}
<div class="section-header mb-4">
    <span style="font-size:13px;color:var(--color-text-subtle);">
        @if($investimentos->isEmpty()) Nenhum ativo cadastrado @else {{ $investimentos->count() }} ativo{{ $investimentos->count() !== 1 ? 's' : '' }} na carteira @endif
    </span>
    <button class="btn btn-amber" onclick="openModal('modal-novo-investimento')">
        <i class="fa-solid fa-plus"></i> Novo Investimento
    </button>
</div>

@if($investimentos->isEmpty())
<div class="card">
    <div class="empty-state">
        <i class="fa-solid fa-seedling" style="color:var(--color-amber);"></i>
        <p>Nenhum investimento registrado ainda.<br>Comece adicionando seu primeiro ativo!</p>
    </div>
</div>
@else

{{-- ── Cards de Investimento ────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(420px,100%),1fr));gap:20px;">
@foreach($investimentos as $inv)
@php
    $positivo     = $inv->ganho_reais >= 0;
    $corGanho     = $positivo ? 'var(--color-success)' : 'var(--color-danger)';
    $bgGanho      = $positivo ? 'var(--color-success-soft)' : 'var(--color-danger-soft)';
    $iconeGanho   = $positivo ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
    $tipoIcone    = match(true) {
        str_contains(strtolower($inv->tipo_investimento), 'tesouro')   => 'fa-landmark',
        str_contains(strtolower($inv->tipo_investimento), 'renda fix') => 'fa-landmark',
        str_contains(strtolower($inv->tipo_investimento), 'fii')       => 'fa-building',
        str_contains(strtolower($inv->tipo_investimento), 'ação')      => 'fa-chart-line',
        str_contains(strtolower($inv->tipo_investimento), 'acao')      => 'fa-chart-line',
        str_contains(strtolower($inv->tipo_investimento), 'cripto')    => 'fa-bitcoin-sign',
        str_contains(strtolower($inv->tipo_investimento), 'poupan')    => 'fa-piggy-bank',
        str_contains(strtolower($inv->tipo_investimento), 'cdb')       => 'fa-building-columns',
        default => 'fa-seedling',
    };
    $temHistorico = count($inv->chart_labels) > 1;
    $chartId      = 'chart-' . $inv->id;
@endphp

<div class="card" style="padding:0;overflow:hidden;">

    {{-- Header do card --}}
    <div style="padding:16px 18px 12px;border-bottom:1px solid var(--color-border);">
        <div style="display:flex;align-items:flex-start;gap:12px;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;background:var(--color-warning-soft);flex-shrink:0;">
                <i class="fa-solid {{ $tipoIcone }}" style="color:var(--color-amber);font-size:17px;"></i>
            </span>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:15px;color:var(--color-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    {{ $inv->nome_ativo }}
                </div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                    <span class="badge badge-amber" style="font-size:10px;">{{ $inv->tipo_investimento }}</span>
                    @if($inv->banco)
                    <span style="font-size:11px;color:var(--color-text-subtle);"><i class="fa-solid fa-building-columns" style="font-size:9px;"></i> {{ $inv->banco->nome }}</span>
                    @endif
                    @if($inv->percentual_mensal)
                    <span style="font-size:11px;color:var(--color-success);font-weight:600;" title="Taxa mensal configurada">
                        <i class="fa-solid fa-percent" style="font-size:9px;"></i> {{ number_format($inv->percentual_mensal, 2, ',', '.') }}%/mês
                    </span>
                    @endif
                </div>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0;">
                @if(Auth::user()->temPermissao('investimentos', 'editar'))
                <button onclick="editarInvestimento({{ $inv->id }}, {{ $inv->toJson() }})"
                    class="btn btn-ghost btn-icon btn-sm" title="Editar" style="width:28px;height:28px;padding:0;">
                    <i class="fa-solid fa-pen" style="font-size:11px;"></i>
                </button>
                @endif
                @if(Auth::user()->temPermissao('investimentos', 'excluir'))
                <form method="POST" action="{{ route('investimentos.destroy', $inv) }}"
                    onsubmit="return confirm('Excluir {{ addslashes($inv->nome_ativo) }}? Todos os registros de rendimento também serão excluídos.')"
                    style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm text-red" title="Excluir" style="width:28px;height:28px;padding:0;">
                        <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;padding:14px 18px;gap:8px;border-bottom:1px solid var(--color-border);">
        <div>
            <div style="font-size:10px;color:var(--color-text-subtle);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Aportado</div>
            <div style="font-size:14px;font-weight:700;color:var(--color-text);">{{ brl($inv->valor_aportado) }}</div>
            <div style="font-size:10px;color:var(--color-text-muted);">{{ $inv->data_aporte->format('d/m/Y') }}</div>
        </div>
        <div>
            <div style="font-size:10px;color:var(--color-text-subtle);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Valor Atual</div>
            <div style="font-size:14px;font-weight:700;color:{{ $corGanho }};">{{ brl($inv->valor_atual_calc) }}</div>
            <div style="font-size:10px;color:var(--color-text-muted);">{{ $temHistorico ? 'Atualizado' : 'Sem atualizações' }}</div>
        </div>
        <div style="background:{{ $bgGanho }};border-radius:8px;padding:6px 8px;text-align:center;">
            <div style="font-size:10px;color:{{ $corGanho }};font-weight:600;text-transform:uppercase;margin-bottom:2px;">
                <i class="fa-solid {{ $iconeGanho }}"></i> Rendimento
            </div>
            <div style="font-size:14px;font-weight:700;color:{{ $corGanho }};">
                {{ $inv->ganho_percentual >= 0 ? '+' : '' }}{{ number_format($inv->ganho_percentual, 2, ',', '.') }}%
            </div>
            <div style="font-size:10px;color:{{ $corGanho }};font-weight:500;">
                {{ $inv->ganho_reais >= 0 ? '+' : '' }}{{ brl(abs($inv->ganho_reais)) }}
            </div>
        </div>
    </div>

    {{-- Gráfico --}}
    <div style="padding:12px 18px 8px;">
        @if($temHistorico)
        <canvas id="{{ $chartId }}" height="90" style="width:100%;"></canvas>
        @else
        <div style="display:flex;align-items:center;justify-content:center;height:70px;background:var(--color-bg);border-radius:8px;border:1px dashed var(--color-border);">
            <span style="font-size:12px;color:var(--color-text-muted);">
                <i class="fa-solid fa-chart-line" style="margin-right:6px;"></i>
                @if($inv->percentual_mensal)
                    Projeção calculada com {{ number_format($inv->percentual_mensal, 2, ',', '.') }}%/mês
                @else
                    Registre o rendimento para ver o gráfico
                @endif
            </span>
        </div>
        @endif
    </div>

    {{-- Histórico de rendimentos --}}
    @if($inv->rendimentos->count() > 0)
    <div style="padding:0 18px 10px;">
        <div style="font-size:11px;font-weight:600;color:var(--color-text-subtle);margin-bottom:6px;">HISTÓRICO</div>
        <div style="max-height:100px;overflow-y:auto;display:flex;flex-direction:column;gap:4px;">
            @foreach($inv->rendimentos->sortByDesc('data') as $rend)
            @php
                $rendIdx = $inv->rendimentos->sortBy('data')->search(fn($r) => $r->id === $rend->id);
                $valorAnterior = $rendIdx > 0 ? (float) $inv->rendimentos->sortBy('data')->values()[$rendIdx - 1]->valor_atual : (float) $inv->valor_aportado;
                $varRend = $valorAnterior > 0 ? (((float)$rend->valor_atual - $valorAnterior) / $valorAnterior) * 100 : 0;
            @endphp
            <div style="display:flex;align-items:center;gap:8px;padding:4px 8px;background:var(--color-bg);border-radius:6px;font-size:11px;">
                <span style="color:var(--color-text-muted);white-space:nowrap;">{{ $rend->data->format('d/m/Y') }}</span>
                <span style="font-weight:600;color:var(--color-text);flex:1;">{{ brl($rend->valor_atual) }}</span>
                <span style="font-weight:600;color:{{ $varRend >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    {{ $varRend >= 0 ? '+' : '' }}{{ number_format($varRend, 2, ',', '.') }}%
                </span>
                @if($rend->observacoes)
                <span style="color:var(--color-text-muted);" title="{{ $rend->observacoes }}"><i class="fa-solid fa-comment" style="font-size:10px;"></i></span>
                @endif
                @if(Auth::user()->temPermissao('investimentos', 'excluir'))
                <form method="POST" action="{{ route('investimentos.rendimentos.destroy', [$inv, $rend]) }}" onsubmit="return confirm('Excluir este registro?')" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--color-danger);padding:0;line-height:1;" title="Excluir">
                        <i class="fa-solid fa-times" style="font-size:10px;"></i>
                    </button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Footer com ação de registrar rendimento --}}
    @if(Auth::user()->temPermissao('investimentos', 'editar'))
    <div style="padding:10px 18px;border-top:1px solid var(--color-border);background:var(--color-bg);">
        <button onclick="abrirModalRendimento({{ $inv->id }}, '{{ addslashes($inv->nome_ativo) }}', {{ (float)$inv->valor_atual_calc }})"
            class="btn btn-secondary btn-sm" style="font-size:12px;width:100%;">
            <i class="fa-solid fa-plus"></i> Registrar Rendimento
        </button>
    </div>
    @endif
</div>
@endforeach
</div>
@endif

{{-- ── Modal Novo Investimento ──────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-novo-investimento">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <i class="fa-solid fa-seedling" style="color:var(--color-amber);"></i>
            <h3>Novo Investimento</h3>
            <button class="modal-close" onclick="closeModal('modal-novo-investimento')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('investimentos.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label class="form-label">Nome do Ativo *</label>
                        <input type="text" name="nome_ativo" class="form-control" required placeholder="Ex: Tesouro Selic 2027, XPTO11, PETR4...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo *</label>
                        <input type="text" name="tipo_investimento" class="form-control" required
                            list="lista-tipos-inv" placeholder="Renda Fixa, FII, Ação...">
                        <datalist id="lista-tipos-inv">
                            <option value="Renda Fixa"><option value="Tesouro Direto"><option value="CDB">
                            <option value="LCI/LCA"><option value="FII"><option value="Ação">
                            <option value="ETF"><option value="Criptomoeda"><option value="Poupança">
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Aporte *</label>
                        <input type="date" name="data_aporte" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor Aportado *</label>
                        <input type="number" name="valor_aportado" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantidade de Cotas</label>
                        <input type="number" name="quantidade_cotas" step="0.000001" min="0" value="0" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label" title="Taxa de crescimento mensal estimada">Taxa Mensal (%)</label>
                        <input type="number" name="percentual_mensal" step="0.01" min="0" max="100" class="form-control"
                            placeholder="Ex: 1.05" id="novo-taxa-mensal"
                            oninput="sincronizarTaxa('novo', 'mensal', this.value)">
                    </div>
                    <div class="form-group">
                        <label class="form-label" title="Taxa de crescimento anual estimada">Taxa Anual (%)</label>
                        <input type="number" name="percentual_anual" step="0.01" min="0" max="100" class="form-control"
                            placeholder="Ex: 12.68" id="novo-taxa-anual"
                            oninput="sincronizarTaxa('novo', 'anual', this.value)">
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Conta / Corretora</label>
                        <select name="banco_id" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2" placeholder="Observações..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-novo-investimento')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-amber"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Editar Investimento ────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-editar-investimento">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-amber);"></i>
            <h3>Editar Investimento</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-investimento')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-editar-investimento">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group span-2">
                        <label class="form-label">Nome do Ativo *</label>
                        <input type="text" name="nome_ativo" id="inv-edit-nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo *</label>
                        <input type="text" name="tipo_investimento" id="inv-edit-tipo" class="form-control" required list="lista-tipos-inv">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Aporte *</label>
                        <input type="date" name="data_aporte" id="inv-edit-data" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input type="number" name="valor_aportado" id="inv-edit-valor" step="0.01" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cotas</label>
                        <input type="number" name="quantidade_cotas" id="inv-edit-cotas" step="0.000001" min="0" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Taxa Mensal (%)</label>
                        <input type="number" name="percentual_mensal" id="edit-taxa-mensal" step="0.01" min="0" max="100" class="form-control"
                            placeholder="Ex: 1.05" oninput="sincronizarTaxa('edit', 'mensal', this.value)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Taxa Anual (%)</label>
                        <input type="number" name="percentual_anual" id="edit-taxa-anual" step="0.01" min="0" max="100" class="form-control"
                            placeholder="Ex: 12.68" oninput="sincronizarTaxa('edit', 'anual', this.value)">
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Conta</label>
                        <select name="banco_id" id="inv-edit-banco" class="form-control">
                            <option value="">— Nenhuma —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="inv-edit-obs" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-editar-investimento')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-amber"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Registrar Rendimento ───────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-rendimento">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <i class="fa-solid fa-chart-line" style="color:var(--color-success);"></i>
            <h3>Registrar Rendimento</h3>
            <button class="modal-close" onclick="closeModal('modal-rendimento')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-rendimento">
                @csrf
                <div style="display:flex;flex-direction:column;gap:12px;">

                    <div style="padding:8px 12px;background:var(--color-success-soft);border-radius:6px;border:1px solid var(--color-success);font-size:12px;color:var(--color-success);">
                        <i class="fa-solid fa-seedling"></i>
                        <strong id="rend-nome-ativo">—</strong>
                        &nbsp;·&nbsp; Valor atual: <strong id="rend-valor-atual-label">—</strong>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Data *</label>
                        <input type="date" name="data" id="rend-data" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Como deseja registrar? *</label>
                        <div style="display:flex;gap:8px;">
                            <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid var(--color-border);border-radius:6px;cursor:pointer;font-size:13px;transition:all .15s;" id="lbl-tipo-valor">
                                <input type="radio" name="tipo_entrada" value="valor" checked onchange="onTipoRendChange()" style="accent-color:var(--color-success);">
                                <span><strong>Valor atual</strong><br><small style="color:var(--color-text-muted);">Ex: R$ 5.450,00</small></span>
                            </label>
                            <label style="flex:1;display:flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid var(--color-border);border-radius:6px;cursor:pointer;font-size:13px;transition:all .15s;" id="lbl-tipo-percentual">
                                <input type="radio" name="tipo_entrada" value="percentual" onchange="onTipoRendChange()" style="accent-color:var(--color-success);">
                                <span><strong>Variação %</strong><br><small style="color:var(--color-text-muted);">Ex: +1,2%</small></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="campo-valor-atual">
                        <label class="form-label">Valor Atual (R$) *</label>
                        <input type="number" name="valor_atual" id="rend-valor" step="0.01" min="0" class="form-control" placeholder="0,00">
                    </div>

                    <div class="form-group" id="campo-percentual" style="display:none;">
                        <label class="form-label">Variação (%) *</label>
                        <div style="position:relative;">
                            <input type="number" name="percentual" id="rend-percentual" step="0.01" min="-100" max="1000" class="form-control" placeholder="Ex: 1.20" oninput="calcularPreviewRendimento()">
                            <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:12px;color:var(--color-text-muted);">%</span>
                        </div>
                        <div id="rend-preview" style="display:none;margin-top:6px;padding:6px 10px;background:var(--color-success-soft);border-radius:6px;font-size:12px;color:var(--color-success);font-weight:600;"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observações</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Ex: Declaração mensal, CDB vencido...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-rendimento')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn" style="background:var(--color-success);color:#fff;"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Inicializa gráficos ───────────────────────────────────────────────────
@php
$_chartMap = [];
foreach ($investimentos as $_inv) {
    $_chartMap['chart-' . $_inv->id] = [
        'labels'  => $_inv->chart_labels,
        'valores' => $_inv->chart_valores,
        'ganho'   => $_inv->ganho_reais,
    ];
}
@endphp
const chartDataMap = @json($_chartMap);

document.addEventListener('DOMContentLoaded', function () {
    Object.entries(chartDataMap).forEach(([id, data]) => {
        const canvas = document.getElementById(id);
        if (!canvas || data.labels.length < 2) return;

        const positivo = data.ganho >= 0;
        const cor      = positivo ? 'var(--color-success)' : 'var(--color-danger)';
        const corBg    = positivo ? 'rgba(22,163,74,.1)' : 'rgba(220,38,38,.08)';

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.valores,
                    borderColor: cor,
                    backgroundColor: corBg,
                    borderWidth: 2,
                    pointRadius: data.labels.length > 12 ? 2 : 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: cor,
                    fill: true,
                    tension: 0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' R$ ' + ctx.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2})
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { font: { size: 10 }, maxTicksLimit: 8, color: '#94a3b8' },
                        grid: { display: false },
                    },
                    y: {
                        ticks: {
                            font: { size: 10 }, color: '#94a3b8',
                            callback: v => 'R$' + v.toLocaleString('pt-BR', {minimumFractionDigits:0, maximumFractionDigits:0})
                        },
                        grid: { color: 'rgba(0,0,0,.04)' },
                    }
                }
            }
        });
    });
});

// ── Modal Rendimento ─────────────────────────────────────────────────────
let rendValorAtualBase = 0;

function abrirModalRendimento(invId, nome, valorAtual) {
    rendValorAtualBase = valorAtual;
    document.getElementById('form-rendimento').action = `/investimentos/${invId}/rendimentos`;
    document.getElementById('rend-nome-ativo').textContent = nome;
    document.getElementById('rend-valor-atual-label').textContent =
        'R$ ' + valorAtual.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('rend-valor').value = valorAtual.toFixed(2);
    document.getElementById('rend-percentual').value = '';
    document.getElementById('rend-preview').style.display = 'none';
    // Garante tipo "valor" selecionado
    document.querySelector('[name="tipo_entrada"][value="valor"]').checked = true;
    onTipoRendChange();
    openModal('modal-rendimento');
}

function onTipoRendChange() {
    const tipo = document.querySelector('[name="tipo_entrada"]:checked').value;
    document.getElementById('campo-valor-atual').style.display  = tipo === 'valor'      ? '' : 'none';
    document.getElementById('campo-percentual').style.display   = tipo === 'percentual' ? '' : 'none';
    // Destaque visual nas labels
    document.getElementById('lbl-tipo-valor').style.borderColor      = tipo === 'valor'      ? 'var(--color-success)' : 'var(--color-border)';
    document.getElementById('lbl-tipo-percentual').style.borderColor = tipo === 'percentual' ? 'var(--color-success)' : 'var(--color-border)';
    if (tipo === 'percentual') calcularPreviewRendimento();
}

function calcularPreviewRendimento() {
    const pct     = parseFloat(document.getElementById('rend-percentual').value);
    const preview = document.getElementById('rend-preview');
    if (isNaN(pct) || !rendValorAtualBase) { preview.style.display = 'none'; return; }

    const novoValor = rendValorAtualBase * (1 + pct / 100);
    const ganho     = novoValor - rendValorAtualBase;
    preview.style.display = '';
    preview.style.background = ganho >= 0 ? 'var(--color-success-soft)' : 'var(--color-danger-soft)';
    preview.style.color      = ganho >= 0 ? 'var(--color-success)' : 'var(--color-danger)';
    preview.textContent = `Novo valor: R$ ${novoValor.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})} (${ganho >= 0 ? '+' : ''}R$ ${ganho.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})})`;
}

// ── Modal Editar ─────────────────────────────────────────────────────────
function editarInvestimento(id, data) {
    document.getElementById('form-editar-investimento').action = `/investimentos/${id}`;
    document.getElementById('inv-edit-nome').value   = data.nome_ativo;
    document.getElementById('inv-edit-tipo').value   = data.tipo_investimento;
    document.getElementById('inv-edit-data').value   = data.data_aporte ? data.data_aporte.substring(0,10) : '';
    document.getElementById('inv-edit-valor').value  = data.valor_aportado;
    document.getElementById('inv-edit-cotas').value  = data.quantidade_cotas || 0;
    document.getElementById('inv-edit-banco').value  = data.banco_id || '';
    document.getElementById('inv-edit-obs').value    = data.observacoes || '';
    document.getElementById('edit-taxa-mensal').value = data.percentual_mensal || '';
    document.getElementById('edit-taxa-anual').value  = data.percentual_anual  || '';
    openModal('modal-editar-investimento');
}

// ── Sincroniza taxa mensal ↔ anual ────────────────────────────────────────
function sincronizarTaxa(prefixo, origem, valor) {
    const v = parseFloat(valor);
    if (isNaN(v) || v <= 0) return;

    const idMensal = prefixo === 'novo' ? 'novo-taxa-mensal' : 'edit-taxa-mensal';
    const idAnual  = prefixo === 'novo' ? 'novo-taxa-anual'  : 'edit-taxa-anual';

    if (origem === 'mensal') {
        // Anual = (1 + mensal/100)^12 - 1
        const anual = (Math.pow(1 + v / 100, 12) - 1) * 100;
        document.getElementById(idAnual).value = anual.toFixed(4);
    } else {
        // Mensal = (1 + anual/100)^(1/12) - 1
        const mensal = (Math.pow(1 + v / 100, 1 / 12) - 1) * 100;
        document.getElementById(idMensal).value = mensal.toFixed(4);
    }
}
</script>
@endpush
