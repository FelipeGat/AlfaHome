@extends('layouts.main')
@section('title', 'Receitas')
@section('page-title', 'Receitas')

@section('content')

{{-- ─── Filtros ─────────────────────────────────────────────────────────── --}}
@php
    $dtR         = \Carbon\Carbon::parse($inicio);
    $dtRAnt      = $dtR->copy()->subMonth();
    $dtRProx     = $dtR->copy()->addMonth();
    $mesNomeR    = $dtR->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    $ehMesAtualR = $dtR->format('Y-m') === now()->format('Y-m');
    $ehHojeR     = $inicio === now()->format('Y-m-d') && $fim === now()->format('Y-m-d');

    $filtrosAtivosR = array_filter(['familiar_id'=>$familiarId,'banco_id'=>$bancoId,'categoria_id'=>$categoriaId,'tipo_pagamento'=>$tipoPag]);

    $urlRMesAnt  = route('receitas.index', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtRAnt->startOfMonth()->format('Y-m-d'), 'fim' => $dtRAnt->copy()->endOfMonth()->format('Y-m-d')]));
    $urlRMesProx = route('receitas.index', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtRProx->startOfMonth()->format('Y-m-d'), 'fim' => $dtRProx->copy()->endOfMonth()->format('Y-m-d')]));
    $urlRHoje    = route('receitas.index', array_filter(array_merge($filtrosAtivosR, ['inicio' => now()->format('Y-m-d'), 'fim' => now()->format('Y-m-d')])));
    $urlRMesAtu  = route('receitas.index', array_filter($filtrosAtivosR));
    $urlRTodas   = route('receitas.index', array_filter(array_merge($filtrosAtivosR, ['inicio' => $inicio, 'fim' => $fim])));

    $temFiltroAtivoR = $bancoId || $categoriaId || $tipoPag;
@endphp

<div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-bottom:12px;">
    <button class="btn btn-success" onclick="openModal('modal-nova-receita')">
        <i class="fa-solid fa-plus"></i> Nova Receita
    </button>
</div>

<div class="card filtros-bar">
    <div class="filtros-lanc">

        {{-- Mês --}}
        <div class="filtro-grupo filtro-grupo-centro">
            <div style="display:flex;align-items:center;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;">
                <a href="{{ $urlRMesAnt }}" class="nav-mes-btn" title="Mês anterior"><i class="fa-solid fa-chevron-left" style="font-size:12px;"></i></a>
                <span class="mes-label-btn">{{ $ehHojeR ? 'Hoje' : ucfirst($mesNomeR) }}</span>
                <a href="{{ $urlRMesProx }}" class="nav-mes-btn" title="Próximo mês"><i class="fa-solid fa-chevron-right" style="font-size:12px;"></i></a>
            </div>
            <div style="display:flex;gap:6px;">
                <a href="{{ $urlRHoje }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;
                          border:1px solid {{ $ehHojeR ? 'var(--color-primary)' : 'var(--color-border)' }};
                          background:{{ $ehHojeR ? 'var(--color-primary)' : 'var(--color-bg-card)' }};
                          color:{{ $ehHojeR ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};">
                    <i class="fa-solid fa-calendar-day"></i> Hoje
                </a>
                @if(!$ehMesAtualR && !$ehHojeR)
                <a href="{{ $urlRMesAtu }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;border:1px solid var(--color-border);background:var(--color-bg-card);color:var(--color-text-muted);">
                    <i class="fa-solid fa-rotate-left"></i> Mês Atual
                </a>
                @endif
            </div>
        </div>

        {{-- Membros --}}
        @if($familiares->isNotEmpty())
        <div class="filtro-grupo filtro-grupo-members">
            <div class="av-grupo">
                <a href="{{ $urlRTodas }}" class="av-item" title="Todos da Casa">
                    <div class="av-circulo" style="border:3px solid {{ !$familiarId ? 'var(--color-primary)' : 'transparent' }};outline:{{ !$familiarId ? 'none' : '2px solid var(--color-border)' }};background:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-bg-inset)' }};box-shadow:{{ !$familiarId ? '0 0 0 2px var(--color-primary)44' : 'none' }};">
                        <i class="fa-solid fa-house" style="font-size:13px;color:{{ !$familiarId ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};"></i>
                    </div>
                    <span class="av-nome" style="color:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-text-subtle)' }};font-weight:{{ !$familiarId ? '700' : '400' }};">Todos</span>
                </a>
                @foreach($familiares as $fam)
                @php
                    $rSel  = $familiarId === $fam->id;
                    $rIni  = implode('', array_map(fn($p) => strtoupper(substr($p,0,1)), array_slice(explode(' ',$fam->nome),0,2)));
                    $rCors = ['#6366f1','#0ea5e9','#16a34a','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
                    $rCor  = $rCors[$fam->id % count($rCors)];
                    $rUrl  = $rSel
                        ? route('receitas.index', array_filter(array_merge($filtrosAtivosR, ['inicio'=>$inicio,'fim'=>$fim])))
                        : route('receitas.index', array_filter(array_merge($filtrosAtivosR, ['inicio'=>$inicio,'fim'=>$fim,'familiar_id'=>$fam->id])));
                @endphp
                <a href="{{ $rUrl }}" class="av-item" title="{{ $fam->nome }}">
                    <div class="av-circulo" style="border:3px solid {{ $rSel ? $rCor : 'transparent' }};outline:{{ $rSel ? 'none' : '2px solid var(--color-border)' }};box-shadow:{{ $rSel ? '0 0 0 2px '.$rCor.'44' : 'none' }};">
                        @if($fam->foto)
                            <img src="{{ Storage::url($fam->foto) }}" alt="{{ $fam->nome }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:{{ $rCor }};color:#fff;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;border-radius:50%;">{{ $rIni }}</div>
                        @endif
                    </div>
                    <span class="av-nome" style="color:{{ $rSel ? $rCor : 'var(--color-text-subtle)' }};font-weight:{{ $rSel ? '700' : '400' }};">{{ explode(' ',$fam->nome)[0] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

{{-- ─── Filtros avançados: Banco / Categoria / Tipo ──────────────────────── --}}
<form method="GET" action="{{ route('receitas.index') }}" id="form-filtros-rec"
      style="margin-bottom:12px;">
    <input type="hidden" name="familiar_id" value="{{ $familiarId }}">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">

        {{-- Intervalo de datas --}}
        <div style="display:flex;align-items:center;gap:4px;border:1px solid var(--color-border);border-radius:6px;padding:3px 8px;background:var(--color-bg-card);">
            <i class="fa-solid fa-calendar-range" style="font-size:11px;color:var(--color-text-subtle);"></i>
            <input type="date" name="inicio" value="{{ $inicio }}"
                   onchange="document.getElementById('form-filtros-rec').submit()"
                   style="font-size:12px;border:none;outline:none;background:transparent;color:var(--color-text-muted);cursor:pointer;">
            <span style="font-size:11px;color:var(--color-text-subtle);">até</span>
            <input type="date" name="fim" value="{{ $fim }}"
                   onchange="document.getElementById('form-filtros-rec').submit()"
                   style="font-size:12px;border:none;outline:none;background:transparent;color:var(--color-text-muted);cursor:pointer;">
        </div>

        {{-- Banco / Conta de recebimento --}}
        <select name="banco_id" onchange="document.getElementById('form-filtros-rec').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $bancoId ? 'var(--color-success)' : 'var(--color-border)' }};border-radius:6px;background:{{ $bancoId ? 'var(--color-success-soft)' : 'var(--color-bg-card)' }};color:{{ $bancoId ? 'var(--color-success)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:150px;">
            <option value="">Todas as contas</option>
            @foreach($bancos as $b)
                <option value="{{ $b->id }}" {{ $bancoId == $b->id ? 'selected' : '' }}>{{ $b->nome }}</option>
            @endforeach
        </select>

        {{-- Categoria --}}
        <select name="categoria_id" onchange="document.getElementById('form-filtros-rec').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $categoriaId ? 'var(--color-success)' : 'var(--color-border)' }};border-radius:6px;background:{{ $categoriaId ? 'var(--color-success-soft)' : 'var(--color-bg-card)' }};color:{{ $categoriaId ? 'var(--color-success)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:140px;">
            <option value="">Todas as categorias</option>
            @foreach($categorias as $c)
                <option value="{{ $c->id }}" {{ $categoriaId == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
            @endforeach
        </select>

        {{-- Tipo de Recebimento --}}
        <select name="tipo_pagamento" onchange="document.getElementById('form-filtros-rec').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $tipoPag ? 'var(--color-success)' : 'var(--color-border)' }};border-radius:6px;background:{{ $tipoPag ? 'var(--color-success-soft)' : 'var(--color-bg-card)' }};color:{{ $tipoPag ? 'var(--color-success)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:140px;">
            <option value="">Todos os tipos</option>
            <option value="dinheiro"    {{ $tipoPag === 'dinheiro'    ? 'selected' : '' }}>Dinheiro</option>
            <option value="pix"         {{ $tipoPag === 'pix'         ? 'selected' : '' }}>Pix</option>
            <option value="transferencia"{{ $tipoPag === 'transferencia'? 'selected' : '' }}>Transferência</option>
            <option value="deposito"    {{ $tipoPag === 'deposito'    ? 'selected' : '' }}>Depósito</option>
            <option value="outros"      {{ $tipoPag === 'outros'      ? 'selected' : '' }}>Outros</option>
        </select>

        @if($temFiltroAtivoR)
        <a href="{{ route('receitas.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'familiar_id'=>$familiarId])) }}"
           style="font-size:12px;color:var(--color-text-muted);text-decoration:none;padding:5px 10px;border:1px solid var(--color-border);border-radius:9999px;white-space:nowrap;">
            <i class="fa-solid fa-xmark"></i> Limpar filtros
        </a>
        @endif
    </div>
</form>

@php
    $receitasPorData = collect($receitas->items())->groupBy(fn($r) => $r->data_prevista_recebimento->format('d/m/Y'));
@endphp

<div class="card ext-card">

    {{-- Cabeçalho --}}
    <div class="ext-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-arrow-trend-up" style="color:var(--color-success);font-size:13px;"></i>
            <span style="font-size:14px;font-weight:600;color:var(--color-text);">Receitas</span>
            <span style="font-size:11px;color:var(--color-text-muted);">
                {{ \Carbon\Carbon::parse($inicio)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($fim)->format('d/m/Y') }}
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13px;font-weight:700;color:var(--color-success);">+ {{ brl($totalValor) }}</span>
            <span style="font-size:11px;font-weight:700;color:var(--color-text-muted);background:var(--color-bg-inset);padding:3px 10px;border-radius:20px;">{{ $receitas->total() }}</span>
        </div>
    </div>

    @if($receitasPorData->isNotEmpty())

    @foreach($receitasPorData as $dataFmt => $itens)
    @php
        $dtR2     = $itens->first()->data_prevista_recebimento;
        $isHojeR2 = $dtR2->isToday();
        $isOntR2  = $dtR2->isYesterday();
        $labelR2  = $isHojeR2 ? 'Hoje · '.$dataFmt : ($isOntR2 ? 'Ontem · '.$dataFmt : $dtR2->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'));
        $totalDiaR = $itens->sum('valor');
    @endphp

    <div class="ext-date-header">
        <span class="ext-date-label">{{ $labelR2 }}</span>
        <span style="font-size:11.5px;font-weight:700;color:var(--color-success);">+ {{ brl($totalDiaR) }}</span>
    </div>

    @foreach($itens as $receita)
    @php
        $rSt    = $receita->status;
        $rStOk  = $rSt === 'recebido';
        $rStCls = $rStOk ? 's-ok' : ($rSt === 'vencido' ? 's-venc' : 's-pend');
        $rStLbl = $rStOk ? 'Recebido' : ($rSt === 'vencido' ? 'Vencido' : 'A receber');
        $rStIco = $rStOk ? 'fa-check' : ($rSt === 'vencido' ? 'fa-triangle-exclamation' : 'fa-clock');
        $rDesc  = $receita->observacoes ?? '—';
        $rIcone = $receita->categoria?->icone ?? 'fa-circle-dollar-sign';
        $rConta = $receita->banco?->nome ?? '—';
        $rCor   = $receita->banco?->cor ?? 'var(--color-text-subtle)';
    @endphp

    <div class="ext-row ext-credito">

        <div class="ext-icone ext-credito">
            <i class="fa-solid {{ $rIcone }}" style="font-size:16px;color:var(--color-success);"></i>
        </div>

        <div class="ext-info">
            <div class="ext-desc" title="{{ $rDesc }}">{{ $rDesc }}</div>
            <div class="ext-meta">
                <span style="font-size:11px;font-weight:600;color:var(--color-text-muted);background:var(--color-bg-inset);padding:2px 7px;border-radius:5px;white-space:nowrap;">
                    <i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $receita->data_prevista_recebimento->format('d/m') }}
                </span>
                <span class="ext-conta-pill">
                    <span class="ext-dot" style="background:{{ $rCor }};"></span>
                    {{ $rConta }}
                </span>
                @if($receita->categoria)
                <span class="ext-tag ext-tag-cat">{{ $receita->categoria->nome }}</span>
                @endif
                @if($receita->familiar)
                <span class="ext-tag" style="background:var(--color-success-soft);color:var(--color-success);">{{ $receita->familiar->nome }}</span>
                @endif
                @if($receita->recorrente)
                <span class="ext-tag ext-tag-rec"><i class="fa-solid fa-rotate" style="font-size:8px;"></i> Recorrente</span>
                @endif
            </div>
        </div>

        <div class="ext-valor-col">
            <div class="ext-valor ext-credito">+ {{ brl($receita->valor) }}</div>
            <div class="ext-status {{ $rStCls }}">
                <i class="fa-solid {{ $rStIco }}" style="font-size:8px;"></i> {{ $rStLbl }}
            </div>
        </div>

        <div class="ext-actions">
            <button onclick="editarReceita({{ $receita->id }}, {{ $receita->toJson() }})" class="ext-edit-btn" title="Editar">
                <i class="fa-solid fa-pen" style="font-size:11px;"></i>
            </button>
            <button onclick="excluirReceita({{ $receita->id }}, {{ $receita->grupo_recorrencia_id ? 'true' : 'false' }})" class="ext-del-btn" title="Excluir">
                <i class="fa-solid fa-trash" style="font-size:11px;"></i>
            </button>
        </div>

    </div>
    @endforeach
    @endforeach

    <div class="ext-footer">
        <span style="font-size:10px;font-weight:700;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-right:auto;">Total do período</span>
        <div class="ext-footer-item">
            <span class="ext-footer-dot" style="background:var(--color-success);"></span>
            <span style="font-size:12.5px;font-weight:700;color:var(--color-success);">+ {{ brl($totalValor) }}</span>
        </div>
    </div>

    @else
    <div style="text-align:center;padding:48px 20px;">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--color-success-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fa-solid fa-inbox" style="font-size:22px;color:var(--color-success);opacity:.5;"></i>
        </div>
        <p style="font-size:13px;font-weight:600;color:var(--color-text-muted);margin-bottom:4px;">Nenhuma receita encontrada</p>
        <p style="font-size:12px;color:var(--color-text-subtle);">Ajuste o período ou os filtros acima.</p>
    </div>
    @endif

</div>

<div style="margin-top:12px;">{{ $receitas->links() }}</div>

{{-- Modal Nova Receita --}}
<div class="modal-backdrop" id="modal-nova-receita">
    <div class="modal">
        <div class="modal-header">
            <i class="fa-solid fa-plus" style="color:var(--color-success);"></i>
            <h3>Nova Receita</h3>
            <button class="modal-close" onclick="closeModal('modal-nova-receita')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('receitas.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input type="number" name="valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data Prevista *</label>
                        <input type="date" name="data_prevista_recebimento" id="rec-novo-dpr" class="form-control" value="{{ date('Y-m-d') }}" required onchange="recNovoSyncPago()">
                    </div>
                    <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:500;color:var(--color-text);margin-bottom:6px;">
                            <input type="checkbox" id="rec-novo-marcar-recebida" onchange="toggleMarcarRecebida(this)" style="width:16px;height:16px;cursor:pointer;accent-color:var(--color-success);">
                            Marcar como recebida
                        </label>
                        <input type="date" name="data_recebimento" id="rec-novo-dr" class="form-control" style="display:none;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem Recebeu</label>
                        <select name="quem_recebeu" class="form-control">
                            <option value="">🏠 Todos da Casa</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <div style="display:flex;gap:6px;">
                            <select name="categoria_id" class="form-control" id="rec-novo-categoria_id" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('categoria-receita','rec-novo-categoria_id')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Nova categoria">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta de Recebimento</label>
                        <select name="forma_recebimento" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Recebimento</label>
                        <select name="tipo_pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="deposito">🏦 Depósito Bancário</option>
                            <option value="outros">📌 Outros</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nº de Parcelas <span style="font-size:11px; color:var(--color-muted);">(0 = sem limite)</span></label>
                        <input type="number" name="parcelas" id="rec-novo-parcelas" value="1" min="0" max="360" class="form-control"
                               oninput="onParcelasChangeReceita(this,'rec-novo-recorrente','rec-novo-frequencia-row')">
                    </div>
                    <div class="form-group" id="rec-novo-frequencia-row" style="display:none;">
                        <label class="form-label">Frequência</label>
                        <select name="frequencia" class="form-control">
                            <option value="mensal">Mensal</option>
                            <option value="diaria">Diária</option>
                            <option value="semanal">Semanal</option>
                            <option value="quinzenal">Quinzenal</option>
                            <option value="trimestral">Trimestral</option>
                            <option value="semestral">Semestral</option>
                            <option value="anual">Anual</option>
                        </select>
                    </div>
                    {{-- campo oculto ativado pelo JS --}}
                    <input type="hidden" name="recorrente" id="rec-novo-recorrente" value="">

                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-nova-receita')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar Receita --}}
<div class="modal-backdrop" id="modal-editar-receita">
    <div class="modal">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-success);"></i>
            <h3>Editar Receita</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-receita')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-editar-receita">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input type="number" name="valor" id="r-edit-valor" step="0.01" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data Prevista *</label>
                        <input type="date" name="data_prevista_recebimento" id="r-edit-dpr" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Recebimento</label>
                        <input type="date" name="data_recebimento" id="r-edit-dr" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem Recebeu</label>
                        <select name="quem_recebeu" id="r-edit-quem" class="form-control">
                            <option value="">🏠 Todos da Casa</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <div style="display:flex;gap:6px;">
                            <select name="categoria_id" id="r-edit-cat" class="form-control" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('categoria-receita','r-edit-cat')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Nova categoria">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <select name="forma_recebimento" id="r-edit-banco" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Recebimento</label>
                        <select name="tipo_pagamento" id="r-edit-tipo" class="form-control">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="deposito">🏦 Depósito Bancário</option>
                            <option value="outros">📌 Outros</option>
                        </select>
                    </div>

                    {{-- Escopo da edição (só para recorrentes) --}}
                    <div id="r-edit-escopo-container" style="display:none;" class="form-group span-2">
                        <div class="alert alert-info" style="padding:8px 12px; border-radius:6px; background:var(--color-warning-soft); border:1px solid var(--color-amber); font-size:13px; margin-bottom:8px;">
                            <i class="fa-solid fa-rotate" style="color:var(--color-amber);"></i>
                            Esta é uma receita recorrente. Escolha o que deseja alterar:
                        </div>
                        <select name="escopo" id="r-edit-escopo" class="form-control">
                            <option value="apenas_esta">Alterar somente esta ocorrência / este mês</option>
                            <option value="esta_e_futuras">Alterar esta e todas as próximas ocorrências</option>
                        </select>
                    </div>

                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="r-edit-obs" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-editar-receita')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" action="" id="form-excluir-receita" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="escopo" id="r-escopo-excluir" value="apenas_esta">
</form>

{{-- ── Modal de confirmação de exclusão ──────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-confirmar-exclusao-receita" style="z-index:1100;">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--color-danger);"></i>
            <h3>Excluir Receita</h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-exclusao-receita')">&times;</button>
        </div>
        <div class="modal-body">
            {{-- Lançamento simples --}}
            <div id="exc-r-bloco-simples">
                <p style="font-size:14px;color:var(--color-text);margin:0 0 20px;">
                    Tem certeza que deseja excluir esta receita? Essa ação não pode ser desfeita.
                </p>
                <div class="modal-footer" style="padding:0;">
                    <button type="button" onclick="closeModal('modal-confirmar-exclusao-receita')" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="button" onclick="confirmarExclusaoReceita('apenas_esta')" class="btn" style="background:var(--color-danger);color:#fff;">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                </div>
            </div>
            {{-- Lançamento recorrente --}}
            <div id="exc-r-bloco-recorrente" style="display:none;">
                <p style="font-size:14px;color:var(--color-text);margin:0 0 16px;">
                    Esta é uma <strong>receita recorrente</strong>. O que você deseja fazer?
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:2px solid var(--color-border);border-radius:8px;cursor:pointer;" id="lbl-exc-r-apenas">
                        <input type="radio" name="opcao-excluir-r" value="apenas_esta" checked onchange="excRAtualizarSelecao()" style="margin-top:2px;accent-color:var(--color-success);">
                        <div>
                            <div style="font-weight:600;font-size:13px;">Somente esta</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">Remove apenas esta ocorrência</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:2px solid var(--color-border);border-radius:8px;cursor:pointer;" id="lbl-exc-r-futuras">
                        <input type="radio" name="opcao-excluir-r" value="esta_e_futuras" onchange="excRAtualizarSelecao()" style="margin-top:2px;accent-color:var(--color-success);">
                        <div>
                            <div style="font-weight:600;font-size:13px;">Esta e todas as próximas</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">Remove esta e todas as ocorrências futuras da recorrência</div>
                        </div>
                    </label>
                </div>
                <div class="modal-footer" style="padding:0;">
                    <button type="button" onclick="closeModal('modal-confirmar-exclusao-receita')" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="button" onclick="confirmarExclusaoReceita()" class="btn" style="background:var(--color-danger);color:#fff;">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Marcar como recebida (nova receita) ──────────────────────────────────────
function toggleMarcarRecebida(cb) {
    const dataField = document.getElementById('rec-novo-dr');
    if (cb.checked) {
        dataField.value = document.getElementById('rec-novo-dpr').value;
        dataField.style.display = '';
    } else {
        dataField.value = '';
        dataField.style.display = 'none';
    }
}

function recNovoSyncPago() {
    const cb = document.getElementById('rec-novo-marcar-recebida');
    if (cb && cb.checked) {
        document.getElementById('rec-novo-dr').value = document.getElementById('rec-novo-dpr').value;
    }
}

// ── Parcelas (nova receita) ────────────────────────────────────────────────────
function onParcelasChangeReceita(inputParcelas, idRecorrente, idFreqRow) {
    const val      = parseInt(inputParcelas.value) || 1;
    const recInput = document.getElementById(idRecorrente);
    const freqRow  = document.getElementById(idFreqRow);

    if (recInput) recInput.value = (val !== 1) ? '1' : '';
    if (freqRow)  freqRow.style.display = (val !== 1) ? '' : 'none';
}

// ── Modal de edição ───────────────────────────────────────────────────────────
function editarReceita(id, data) {
    document.getElementById('form-editar-receita').action = `/receitas/${id}`;
    document.getElementById('r-edit-valor').value   = data.valor;
    document.getElementById('r-edit-dpr').value     = data.data_prevista_recebimento ? data.data_prevista_recebimento.substring(0,10) : '';
    document.getElementById('r-edit-dr').value      = data.data_recebimento ? data.data_recebimento.substring(0,10) : '';
    document.getElementById('r-edit-quem').value    = data.quem_recebeu || '';
    document.getElementById('r-edit-cat').value     = data.categoria_id || '';
    document.getElementById('r-edit-banco').value   = data.forma_recebimento || '';
    document.getElementById('r-edit-tipo').value    = data.tipo_pagamento || '';
    document.getElementById('r-edit-obs').value     = data.observacoes || '';

    const escopoContainer = document.getElementById('r-edit-escopo-container');
    escopoContainer.style.display = data.grupo_recorrencia_id ? 'block' : 'none';
    if (data.grupo_recorrencia_id) {
        document.getElementById('r-edit-escopo').value = 'apenas_esta';
    }

    openModal('modal-editar-receita');
}

// ── Exclusão ──────────────────────────────────────────────────────────────────
let _excReceitaId = null;

function excluirReceita(id, isRecorrente) {
    _excReceitaId = id;
    document.getElementById('form-excluir-receita').action = `/receitas/${id}`;

    if (isRecorrente) {
        document.getElementById('exc-r-bloco-simples').style.display    = 'none';
        document.getElementById('exc-r-bloco-recorrente').style.display = '';
        document.querySelector('[name="opcao-excluir-r"][value="apenas_esta"]').checked = true;
        excRAtualizarSelecao();
    } else {
        document.getElementById('exc-r-bloco-simples').style.display    = '';
        document.getElementById('exc-r-bloco-recorrente').style.display = 'none';
    }
    openModal('modal-confirmar-exclusao-receita');
}

function excRAtualizarSelecao() {
    const val = document.querySelector('[name="opcao-excluir-r"]:checked').value;
    document.getElementById('lbl-exc-r-apenas').style.borderColor  = val === 'apenas_esta'    ? 'var(--color-success)' : 'var(--color-border)';
    document.getElementById('lbl-exc-r-futuras').style.borderColor = val === 'esta_e_futuras' ? 'var(--color-success)' : 'var(--color-border)';
}

function confirmarExclusaoReceita(escopoFixo) {
    const escopo = escopoFixo || document.querySelector('[name="opcao-excluir-r"]:checked').value;
    document.getElementById('r-escopo-excluir').value = escopo;
    document.getElementById('form-excluir-receita').submit();
    closeModal('modal-confirmar-exclusao-receita');
}

// ── Cadastro rápido (categoria) ──────────────────────────────────────────────
function criarRapido(tipo, selectId) {
    const labels = {
        'categoria-receita': 'Nova Categoria (Receita)',
        'categoria-despesa': 'Nova Categoria (Despesa)',
        'fornecedor': 'Novo Fornecedor',
    };
    const nome = prompt(labels[tipo] || 'Nome:');
    if (!nome || !nome.trim()) return;

    let url, body;
    if (tipo === 'fornecedor') {
        url = '/fornecedores/rapido';
        body = JSON.stringify({ nome: nome.trim() });
    } else {
        const tipoCategoria = tipo === 'categoria-receita' ? 'RECEITA' : 'DESPESA';
        url = '/categorias/rapido';
        body = JSON.stringify({ nome: nome.trim(), tipo: tipoCategoria });
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: body,
    })
    .then(r => { if (!r.ok) throw r; return r.json(); })
    .then(data => {
        const seletores = document.querySelectorAll('select[name="categoria_id"]');
        seletores.forEach(sel => {
            const opt = new Option(data.nome, data.id);
            sel.appendChild(opt);
        });

        const selectOrigem = document.getElementById(selectId);
        if (selectOrigem) selectOrigem.value = data.id;
    })
    .catch(() => alert('Erro ao cadastrar. Verifique se você tem permissão.'));
}
</script>
@endpush
