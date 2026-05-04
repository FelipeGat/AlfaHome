@extends('layouts.main')
@section('title', 'Despesas')
@section('page-title', 'Despesas')

@section('content')

{{-- ─── Filtros ─────────────────────────────────────────────────────────── --}}
@php
    $dtD         = \Carbon\Carbon::parse($inicio);
    $dtDAnt      = $dtD->copy()->subMonth();
    $dtDProx     = $dtD->copy()->addMonth();
    $mesNomeD    = $dtD->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    $ehMesAtualD = $dtD->format('Y-m') === now()->format('Y-m');
    $ehHojeD     = $inicio === now()->format('Y-m-d') && $fim === now()->format('Y-m-d');

    // Mantém todos os filtros ativos ao navegar entre meses
    $filtrosAtivos = array_filter(['familiar_id'=>$familiarId,'fornecedor_id'=>$fornecedorId,'banco_id'=>$bancoId,'categoria_id'=>$categoriaId,'tipo_pagamento'=>$tipoPag]);

    $urlDMesAnt  = route('despesas.index', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtDAnt->startOfMonth()->format('Y-m-d'), 'fim' => $dtDAnt->copy()->endOfMonth()->format('Y-m-d')]));
    $urlDMesProx = route('despesas.index', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtDProx->startOfMonth()->format('Y-m-d'), 'fim' => $dtDProx->copy()->endOfMonth()->format('Y-m-d')]));
    $urlDHoje    = route('despesas.index', array_filter(array_merge($filtrosAtivos, ['inicio' => now()->format('Y-m-d'), 'fim' => now()->format('Y-m-d')])));
    $urlDMesAtu  = route('despesas.index', array_filter($filtrosAtivos));
    $urlDTodas   = route('despesas.index', array_filter(array_merge($filtrosAtivos, ['inicio' => $inicio, 'fim' => $fim])));

    $temFiltroAtivo = $fornecedorId || $bancoId || $categoriaId || $tipoPag || $statusFiltro;
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
    <div>{{-- espaço --}}</div>
    <button class="btn btn-danger" onclick="openModal('modal-nova-despesa')">
        <i class="fa-solid fa-plus"></i> Nova Despesa
    </button>
</div>

<div class="card filtros-bar">
    <div class="filtros-lanc">

        {{-- Mês --}}
        <div class="filtro-grupo filtro-grupo-centro">
            <div style="display:flex;align-items:center;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;">
                <a href="{{ $urlDMesAnt }}" class="nav-mes-btn" title="Mês anterior"><i class="fa-solid fa-chevron-left" style="font-size:12px;"></i></a>
                <span class="mes-label-btn">{{ $ehHojeD ? 'Hoje' : ucfirst($mesNomeD) }}</span>
                <a href="{{ $urlDMesProx }}" class="nav-mes-btn" title="Próximo mês"><i class="fa-solid fa-chevron-right" style="font-size:12px;"></i></a>
            </div>
            <div style="display:flex;gap:6px;">
                <a href="{{ $urlDHoje }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;
                          border:1px solid {{ $ehHojeD ? 'var(--color-primary)' : 'var(--color-border)' }};
                          background:{{ $ehHojeD ? 'var(--color-primary)' : 'var(--color-bg-card)' }};
                          color:{{ $ehHojeD ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};">
                    <i class="fa-solid fa-calendar-day"></i> Hoje
                </a>
                @if(!$ehMesAtualD && !$ehHojeD)
                <a href="{{ $urlDMesAtu }}"
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
                <a href="{{ $urlDTodas }}" class="av-item" title="Todos da Casa">
                    <div class="av-circulo" style="border:3px solid {{ !$familiarId ? 'var(--color-primary)' : 'transparent' }};outline:{{ !$familiarId ? 'none' : '2px solid var(--color-border)' }};background:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-bg-inset)' }};box-shadow:{{ !$familiarId ? '0 0 0 2px var(--color-primary)44' : 'none' }};">
                        <i class="fa-solid fa-house" style="font-size:13px;color:{{ !$familiarId ? 'var(--color-bg-card)' : 'var(--color-text-muted)' }};"></i>
                    </div>
                    <span class="av-nome" style="color:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-text-subtle)' }};font-weight:{{ !$familiarId ? '700' : '400' }};">Todos</span>
                </a>
                @foreach($familiares as $fam)
                @php
                    $dSel  = $familiarId === $fam->id;
                    $dIni  = implode('', array_map(fn($p) => strtoupper(substr($p,0,1)), array_slice(explode(' ',$fam->nome),0,2)));
                    $dCors = ['#6366f1','#0ea5e9','#16a34a','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
                    $dCor  = $dCors[$fam->id % count($dCors)];
                    $dUrl  = $dSel
                        ? route('despesas.index', array_filter(array_merge($filtrosAtivos, ['inicio'=>$inicio,'fim'=>$fim])))
                        : route('despesas.index', array_filter(array_merge($filtrosAtivos, ['inicio'=>$inicio,'fim'=>$fim,'familiar_id'=>$fam->id])));
                @endphp
                <a href="{{ $dUrl }}" class="av-item" title="{{ $fam->nome }}">
                    <div class="av-circulo" style="border:3px solid {{ $dSel ? $dCor : 'transparent' }};outline:{{ $dSel ? 'none' : '2px solid var(--color-border)' }};box-shadow:{{ $dSel ? '0 0 0 2px '.$dCor.'44' : 'none' }};">
                        @if($fam->foto)
                            <img src="{{ Storage::url($fam->foto) }}" alt="{{ $fam->nome }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:{{ $dCor }};color:#fff;font-weight:700;font-size:12px;display:flex;align-items:center;justify-content:center;border-radius:50%;">{{ $dIni }}</div>
                        @endif
                    </div>
                    <span class="av-nome" style="color:{{ $dSel ? $dCor : 'var(--color-text-subtle)' }};font-weight:{{ $dSel ? '700' : '400' }};">{{ explode(' ',$fam->nome)[0] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>

{{-- ─── Filtros avançados: Fornecedor / Banco / Categoria / Tipo ─────────── --}}
<form method="GET" action="{{ route('despesas.index') }}" id="form-filtros-desp"
      style="margin-bottom:12px;">
    <input type="hidden" name="familiar_id" value="{{ $familiarId }}">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">

        {{-- Intervalo de datas --}}
        <div style="display:flex;align-items:center;gap:4px;border:1px solid var(--color-border);border-radius:6px;padding:3px 8px;background:var(--color-bg-card);">
            <i class="fa-solid fa-calendar-range" style="font-size:11px;color:var(--color-text-subtle);"></i>
            <input type="date" name="inicio" value="{{ $inicio }}"
                   onchange="document.getElementById('form-filtros-desp').submit()"
                   style="font-size:12px;border:none;outline:none;background:transparent;color:var(--color-text-muted);cursor:pointer;">
            <span style="font-size:11px;color:var(--color-text-subtle);">até</span>
            <input type="date" name="fim" value="{{ $fim }}"
                   onchange="document.getElementById('form-filtros-desp').submit()"
                   style="font-size:12px;border:none;outline:none;background:transparent;color:var(--color-text-muted);cursor:pointer;">
        </div>

        {{-- Status: Pago / A Pagar --}}
        <select name="status" onchange="document.getElementById('form-filtros-desp').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $statusFiltro ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $statusFiltro ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $statusFiltro ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:130px;">
            <option value="">Todos os status</option>
            <option value="pago"    {{ $statusFiltro === 'pago'    ? 'selected' : '' }}>✅ Pago</option>
            <option value="a_pagar" {{ $statusFiltro === 'a_pagar' ? 'selected' : '' }}>🕐 A Pagar</option>
            <option value="vencido" {{ $statusFiltro === 'vencido' ? 'selected' : '' }}>⚠️ Vencido</option>
        </select>

        {{-- Fornecedor --}}
        <select name="fornecedor_id" onchange="document.getElementById('form-filtros-desp').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $fornecedorId ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $fornecedorId ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $fornecedorId ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:150px;">
            <option value="">Todos os fornecedores</option>
            @foreach($fornecedores as $f)
                <option value="{{ $f->id }}" {{ $fornecedorId == $f->id ? 'selected' : '' }}>{{ $f->nome }}</option>
            @endforeach
        </select>

        {{-- Banco / Conta --}}
        <select name="banco_id" onchange="document.getElementById('form-filtros-desp').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $bancoId ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $bancoId ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $bancoId ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:140px;">
            <option value="">Todas as contas</option>
            @foreach($bancos as $b)
                <option value="{{ $b->id }}" {{ $bancoId == $b->id ? 'selected' : '' }}>{{ $b->nome }}</option>
            @endforeach
        </select>

        {{-- Categoria --}}
        <select name="categoria_id" onchange="document.getElementById('form-filtros-desp').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $categoriaId ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $categoriaId ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $categoriaId ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:140px;">
            <option value="">Todas as categorias</option>
            @foreach($categorias as $c)
                <option value="{{ $c->id }}" {{ $categoriaId == $c->id ? 'selected' : '' }}>{{ $c->nome }}</option>
            @endforeach
        </select>

        {{-- Tipo de Pagamento --}}
        <select name="tipo_pagamento" onchange="document.getElementById('form-filtros-desp').submit()"
                style="font-size:12px;padding:5px 10px;border:1px solid {{ $tipoPag ? 'var(--color-primary)' : 'var(--color-border)' }};border-radius:6px;background:{{ $tipoPag ? 'var(--color-info-soft)' : 'var(--color-bg-card)' }};color:{{ $tipoPag ? 'var(--color-primary)' : 'var(--color-text-muted)' }};cursor:pointer;min-width:140px;">
            <option value="">Todos os tipos</option>
            <option value="dinheiro"     {{ $tipoPag === 'dinheiro'     ? 'selected' : '' }}>Dinheiro</option>
            <option value="pix"          {{ $tipoPag === 'pix'          ? 'selected' : '' }}>Pix</option>
            <option value="debito"       {{ $tipoPag === 'debito'       ? 'selected' : '' }}>Cartão Débito</option>
            <option value="credito"      {{ $tipoPag === 'credito'      ? 'selected' : '' }}>Cartão Crédito</option>
            <option value="transferencia"{{ $tipoPag === 'transferencia'? 'selected' : '' }}>Transferência</option>
            <option value="boleto"       {{ $tipoPag === 'boleto'       ? 'selected' : '' }}>Boleto</option>
        </select>

        @if($temFiltroAtivo)
        <a href="{{ route('despesas.index', array_filter(['inicio'=>$inicio,'fim'=>$fim,'familiar_id'=>$familiarId])) }}"
           style="font-size:12px;color:var(--color-text-muted);text-decoration:none;padding:5px 10px;border:1px solid var(--color-border);border-radius:9999px;white-space:nowrap;">
            <i class="fa-solid fa-xmark"></i> Limpar filtros
        </a>
        @endif
    </div>
</form>

@php
    $despesasPorData = collect($despesas->items())->groupBy(fn($d) => $d->data_compra->format('d/m/Y'));
@endphp

<div class="card ext-card">

    {{-- Cabeçalho --}}
    <div class="ext-header">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-arrow-trend-down" style="color:var(--color-danger);font-size:13px;"></i>
            <span style="font-size:14px;font-weight:600;color:var(--color-text);">Despesas</span>
            <span style="font-size:11px;color:var(--color-text-muted);">
                {{ \Carbon\Carbon::parse($inicio)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($fim)->format('d/m/Y') }}
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13px;font-weight:700;color:var(--color-danger);">− R$ {{ number_format($totalValor, 2, ',', '.') }}</span>
            <span style="font-size:11px;font-weight:700;color:var(--color-text-muted);background:var(--color-bg-inset);padding:3px 10px;border-radius:20px;">{{ $despesas->total() }}</span>
        </div>
    </div>

    @if($despesasPorData->isNotEmpty())

    @foreach($despesasPorData as $dataFmt => $itens)
    @php
        $dtD2     = $itens->first()->data_compra;
        $isHojeD2 = $dtD2->isToday();
        $isOntD2  = $dtD2->isYesterday();
        $labelD2  = $isHojeD2 ? 'Hoje · '.$dataFmt : ($isOntD2 ? 'Ontem · '.$dataFmt : $dtD2->locale('pt_BR')->isoFormat('dddd, D [de] MMMM'));
        $totalDia2 = $itens->sum('valor');
    @endphp

    <div class="ext-date-header">
        <span class="ext-date-label">{{ $labelD2 }}</span>
        <span style="font-size:11.5px;font-weight:700;color:var(--color-danger);">− R$ {{ number_format($totalDia2, 2, ',', '.') }}</span>
    </div>

    @foreach($itens as $despesa)
    @php
        $dSt    = $despesa->status;
        $dStOk  = $dSt === 'pago';
        $dStCls = $dStOk ? 's-ok' : ($dSt === 'vencido' ? 's-venc' : 's-pend');
        $dStLbl = $dStOk ? 'Pago' : ($dSt === 'vencido' ? 'Vencido' : 'A pagar');
        $dStIco = $dStOk ? 'fa-check' : ($dSt === 'vencido' ? 'fa-triangle-exclamation' : 'fa-clock');
        $dDesc  = $despesa->observacoes ?? $despesa->fornecedor?->nome ?? '—';
        $dIcone = $despesa->categoria?->icone ?? 'fa-cart-shopping';
        $dConta = $despesa->banco?->nome ?? '—';
        $dCor   = $despesa->banco?->cor ?? 'var(--color-text-subtle)';
    @endphp

    <div class="ext-row ext-debito">

        <div class="ext-icone ext-debito">
            <i class="fa-solid {{ $dIcone }}" style="font-size:16px;color:var(--color-danger);"></i>
        </div>

        <div class="ext-info">
            <div class="ext-desc" title="{{ $dDesc }}">{{ $dDesc }}</div>
            <div class="ext-meta">
                <span style="font-size:11px;font-weight:600;color:var(--color-text-muted);background:var(--color-bg-inset);padding:2px 7px;border-radius:5px;white-space:nowrap;">
                    <i class="fa-regular fa-calendar" style="font-size:10px;"></i> {{ $despesa->data_compra->format('d/m') }}
                </span>
                <span class="ext-conta-pill">
                    <span class="ext-dot" style="background:{{ $dCor }};"></span>
                    {{ $dConta }}
                </span>
                @if($despesa->categoria)
                <span class="ext-tag ext-tag-cat">{{ $despesa->categoria->nome }}</span>
                @endif
                @if($despesa->familiar)
                <span class="ext-tag" style="background:var(--color-info-soft);color:var(--color-info);">{{ $despesa->familiar->nome }}</span>
                @endif
                @if($despesa->recorrente)
                <span class="ext-tag ext-tag-rec"><i class="fa-solid fa-rotate" style="font-size:8px;"></i> Recorrente</span>
                @endif
                @if($despesa->parcelas > 1)
                <span class="ext-tag ext-tag-doc">{{ $despesa->parcelas }}x</span>
                @endif
            </div>
        </div>

        <div class="ext-valor-col">
            <div class="ext-valor ext-debito">− R$ {{ number_format($despesa->valor, 2, ',', '.') }}</div>
            <div class="ext-status {{ $dStCls }}">
                <i class="fa-solid {{ $dStIco }}" style="font-size:8px;"></i> {{ $dStLbl }}
            </div>
        </div>

        <div class="ext-actions">
            <button onclick="editarDespesa({{ $despesa->id }}, {{ $despesa->toJson() }})" class="ext-edit-btn" title="Editar">
                <i class="fa-solid fa-pen" style="font-size:11px;"></i>
            </button>
            <button onclick="excluirDespesa({{ $despesa->id }}, {{ $despesa->grupo_recorrencia_id ? 'true' : 'false' }})" class="ext-del-btn" title="Excluir">
                <i class="fa-solid fa-trash" style="font-size:11px;"></i>
            </button>
        </div>

    </div>
    @endforeach
    @endforeach

    <div class="ext-footer">
        <span style="font-size:10px;font-weight:700;color:var(--color-text-subtle);text-transform:uppercase;letter-spacing:.05em;margin-right:auto;">Total do período</span>
        <div class="ext-footer-item">
            <span class="ext-footer-dot" style="background:var(--color-danger);"></span>
            <span style="font-size:12.5px;font-weight:700;color:var(--color-danger);">− R$ {{ number_format($totalValor, 2, ',', '.') }}</span>
        </div>
    </div>

    @else
    <div style="text-align:center;padding:48px 20px;">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--color-danger-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
            <i class="fa-solid fa-inbox" style="font-size:22px;color:var(--color-danger);opacity:.5;"></i>
        </div>
        <p style="font-size:13px;font-weight:600;color:var(--color-text-muted);margin-bottom:4px;">Nenhuma despesa encontrada</p>
        <p style="font-size:12px;color:var(--color-text-subtle);">Ajuste o período ou os filtros acima.</p>
    </div>
    @endif

</div>

<div style="margin-top:12px;">{{ $despesas->links() }}</div>

{{-- Modal Nova Despesa --}}
<div class="modal-backdrop" id="modal-nova-despesa">
    <div class="modal">
        <div class="modal-header">
            <i class="fa-solid fa-plus" style="color:var(--color-primary);"></i>
            <h3>Nova Despesa</h3>
            <button class="modal-close" onclick="closeModal('modal-nova-despesa')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('despesas.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input type="number" name="valor" id="novo-valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data da Despesa *</label>
                        <input type="date" name="data_compra" id="novo-data_compra" class="form-control" value="{{ date('Y-m-d') }}" required onchange="despNovoSyncPago();despesaAtualizarInfoCartao();">
                    </div>
                    <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:500;color:var(--color-text);margin-bottom:6px;">
                            <input type="checkbox" id="novo-marcar-pago" onchange="toggleMarcarPago(this)" style="width:16px;height:16px;cursor:pointer;accent-color:var(--color-primary);">
                            Marcar como pago
                        </label>
                        <input type="date" name="data_pagamento" id="novo-data_pagamento" class="form-control" style="display:none;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem</label>
                        <select name="quem_comprou" class="form-control">
                            <option value="">🏠 Todos da Casa</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Onde</label>
                        <div style="display:flex;gap:6px;">
                            <select name="onde_comprou" class="form-control" id="novo-onde_comprou" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($fornecedores as $f)
                                    <option value="{{ $f->id }}">{{ $f->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('fornecedor','novo-onde_comprou')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Novo fornecedor">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <div style="display:flex;gap:6px;">
                            <select name="categoria_id" class="form-control" id="novo-categoria_id" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('categoria-despesa','novo-categoria_id')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Nova categoria">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta / Banco</label>
                        <select name="forma_pagamento" id="novo-forma_pagamento" class="form-control"
                                onchange="despesaOnBancoChange(this)">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}"
                                    data-credito="{{ $b->tem_cartao_credito ? 1 : 0 }}"
                                    data-fechamento="{{ $b->dia_fechamento_cartao ?? '' }}"
                                    data-vencimento="{{ $b->dia_vencimento_cartao ?? '' }}"
                                    data-limite="{{ $b->limite_cartao ?? 0 }}">
                                    {{ $b->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="tipo_pagamento" id="novo-tipo_pagamento" class="form-control"
                                onchange="despesaOnTipoPagChange(this)">
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
                    <div id="novo-info-credito" style="display:none;" class="form-group span-2">
                        <div style="padding:10px 14px;border-radius:7px;background:var(--color-warning-soft);border:1px solid var(--color-amber);font-size:12px;margin-bottom:10px;line-height:1.7;">
                            <i class="fa-solid fa-credit-card" style="color:var(--color-amber);"></i>
                            <strong>Compra no Cartão de Crédito</strong><br>
                            <span id="novo-aviso-fatura-texto">Selecione o banco para ver as informações de fatura.</span>
                        </div>
                        <div style="display:flex;gap:12px;align-items:flex-end;">
                            <div style="flex:1;">
                                <label class="form-label">Nº de Parcelas <span style="font-size:11px;color:var(--color-text-subtle);">(0 = recorrente mensal)</span></label>
                                <input type="number" name="parcelas" id="novo-parcelas" value="1" min="0" max="48" class="form-control"
                                       oninput="despesaOnParcelasChange(this)">
                            </div>
                            <div style="flex:1;">
                                <label class="form-label" style="color:var(--color-text-muted);font-size:11px;">Valor por parcela</label>
                                <div id="novo-valor-parcela" style="padding:8px 12px;background:var(--color-bg-inset);border-radius:6px;font-weight:700;color:var(--color-text);font-size:13px;">—</div>
                            </div>
                        </div>
                    </div>

                    {{-- Parcelas para despesas recorrentes (não cartão) --}}
                    <div class="form-group" id="novo-parcelas-recorrente-row" style="display:none;">
                        <label class="form-label">Nº de Parcelas <span style="font-size:11px; color:var(--color-muted);">(0 = sem limite)</span></label>
                        <input type="number" name="parcelas" id="novo-parcelas-rec" value="1" min="0" max="360" class="form-control"
                               oninput="onParcelasChange(this,'novo-recorrente','novo-frequencia-row',null,null)">
                    </div>
                    <div class="form-group" id="novo-frequencia-row" style="display:none;">
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
                    {{-- checkbox oculto - ativado automaticamente pelo JS quando parcelas > 1 --}}
                    <input type="hidden" name="recorrente" id="novo-recorrente" value="">

                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="2" placeholder="Observações..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-nova-despesa')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar Despesa --}}
<div class="modal-backdrop" id="modal-editar-despesa">
    <div class="modal">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-primary);"></i>
            <h3>Editar Despesa</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-despesa')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-editar-despesa">
                @csrf @method('PUT')
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor *</label>
                        <input type="number" name="valor" id="edit-valor" step="0.01" min="0.01" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data da Despesa *</label>
                        <input type="date" name="data_compra" id="edit-data_compra" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Pagamento</label>
                        <input type="date" name="data_pagamento" id="edit-data_pagamento" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem</label>
                        <select name="quem_comprou" id="edit-quem_comprou" class="form-control">
                            <option value="">🏠 Todos da Casa</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Onde</label>
                        <div style="display:flex;gap:6px;">
                            <select name="onde_comprou" id="edit-onde_comprou" class="form-control" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($fornecedores as $f)
                                    <option value="{{ $f->id }}">{{ $f->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('fornecedor','edit-onde_comprou')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Novo fornecedor">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <div style="display:flex;gap:6px;">
                            <select name="categoria_id" id="edit-categoria_id" class="form-control" style="flex:1;">
                                <option value="">— Selecione —</option>
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">{{ $c->nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" onclick="criarRapido('categoria-despesa','edit-categoria_id')" class="btn btn-secondary btn-sm" style="white-space:nowrap;padding:6px 10px;" title="Nova categoria">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta / Banco</label>
                        <select name="forma_pagamento" id="edit-forma_pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="tipo_pagamento" id="edit-tipo_pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">⚡ Pix</option>
                            <option value="debito">💳 Cartão de Débito</option>
                            <option value="credito">💳 Cartão de Crédito</option>
                            <option value="transferencia">🔄 Transferência Bancária</option>
                            <option value="boleto">🧾 Boleto Bancário</option>
                        </select>
                    </div>

                    {{-- Escopo da edição (aparece só em despesas recorrentes) --}}
                    <div id="edit-escopo-container" style="display:none;" class="form-group span-2">
                        <div class="alert alert-info" style="padding:8px 12px; border-radius:6px; background:var(--color-warning-soft); border:1px solid var(--color-amber); font-size:13px; margin-bottom:8px;">
                            <i class="fa-solid fa-rotate" style="color:var(--color-amber);"></i>
                            Esta é uma despesa recorrente. Escolha o que deseja alterar:
                        </div>
                        <select name="escopo" id="edit-escopo" class="form-control">
                            <option value="apenas_esta">Alterar somente esta parcela / este mês</option>
                            <option value="esta_e_futuras">Alterar esta e todas as próximas parcelas</option>
                        </select>
                    </div>

                    <div class="form-group span-2">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" id="edit-observacoes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-editar-despesa')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" action="" id="form-excluir-despesa" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="escopo" id="escopo-excluir" value="apenas_esta">
</form>

{{-- ── Mini-modal: criar rápido (fornecedor / categoria) ─────────────────── --}}
<div class="modal-backdrop" id="modal-criar-rapido" style="z-index:1200;">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <i class="fa-solid fa-plus" id="cr-icone" style="color:var(--color-primary);"></i>
            <h3 id="cr-titulo">Novo</h3>
            <button class="modal-close" onclick="closeModal('modal-criar-rapido')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label" id="cr-label">Nome *</label>
                <input type="text" id="cr-nome" class="form-control" maxlength="120" autocomplete="off"
                       placeholder="Digite o nome..."
                       onkeydown="if(event.key==='Enter'){event.preventDefault();confirmarCriarRapido();}">
                <div id="cr-erro" style="display:none;font-size:12px;color:var(--color-danger);margin-top:6px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('modal-criar-rapido')" class="btn btn-secondary">Cancelar</button>
                <button type="button" onclick="confirmarCriarRapido()" class="btn btn-primary" id="cr-btn-salvar">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Modal de confirmação de exclusão ──────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-confirmar-exclusao-despesa" style="z-index:1100;">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <i class="fa-solid fa-triangle-exclamation" style="color:var(--color-danger);"></i>
            <h3>Excluir Despesa</h3>
            <button class="modal-close" onclick="closeModal('modal-confirmar-exclusao-despesa')">&times;</button>
        </div>
        <div class="modal-body">
            {{-- Lançamento simples --}}
            <div id="exc-d-bloco-simples">
                <p style="font-size:14px;color:var(--color-text);margin:0 0 20px;">
                    Tem certeza que deseja excluir esta despesa? Essa ação não pode ser desfeita.
                </p>
                <div class="modal-footer" style="padding:0;">
                    <button type="button" onclick="closeModal('modal-confirmar-exclusao-despesa')" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="button" onclick="confirmarExclusaoDespesa('apenas_esta')" class="btn" style="background:var(--color-danger);color:#fff;">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                </div>
            </div>
            {{-- Lançamento recorrente --}}
            <div id="exc-d-bloco-recorrente" style="display:none;">
                <p style="font-size:14px;color:var(--color-text);margin:0 0 16px;">
                    Esta é uma <strong>despesa recorrente</strong>. O que você deseja fazer?
                </p>
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:2px solid var(--color-border);border-radius:8px;cursor:pointer;" id="lbl-exc-d-apenas">
                        <input type="radio" name="opcao-excluir-d" value="apenas_esta" checked onchange="excDAtualizarSelecao()" style="margin-top:2px;accent-color:var(--color-danger);">
                        <div>
                            <div style="font-weight:600;font-size:13px;">Somente esta</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">Remove apenas este lançamento</div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:2px solid var(--color-border);border-radius:8px;cursor:pointer;" id="lbl-exc-d-futuras">
                        <input type="radio" name="opcao-excluir-d" value="esta_e_futuras" onchange="excDAtualizarSelecao()" style="margin-top:2px;accent-color:var(--color-danger);">
                        <div>
                            <div style="font-weight:600;font-size:13px;">Esta e todas as próximas</div>
                            <div style="font-size:12px;color:var(--color-text-muted);">Remove este e todos os lançamentos futuros da recorrência</div>
                        </div>
                    </label>
                </div>
                <div class="modal-footer" style="padding:0;">
                    <button type="button" onclick="closeModal('modal-confirmar-exclusao-despesa')" class="btn btn-secondary">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                    <button type="button" onclick="confirmarExclusaoDespesa()" class="btn" style="background:var(--color-danger);color:#fff;">
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
// ── Marcar como pago (nova despesa) ──────────────────────────────────────────
function toggleMarcarPago(cb) {
    const dataField  = document.getElementById('novo-data_pagamento');
    if (cb.checked) {
        dataField.value = document.getElementById('novo-data_compra').value;
        dataField.style.display = '';
    } else {
        dataField.value = '';
        dataField.style.display = 'none';
    }
}

function despNovoSyncPago() {
    const cb = document.getElementById('novo-marcar-pago');
    if (cb && cb.checked) {
        document.getElementById('novo-data_pagamento').value = document.getElementById('novo-data_compra').value;
    }
}

// ── Cartão de Crédito — Modal Nova Despesa ────────────────────────────────────

function despesaCalcVencimento(dataCompraStr, diaFechamento, diaVencimento) {
    if (!dataCompraStr || !diaFechamento || !diaVencimento) return null;
    const compra   = new Date(dataCompraStr + 'T12:00:00');
    const diaComp  = compra.getDate();
    let ano = compra.getFullYear(), mes = compra.getMonth();
    // Se vencimento > fechamento: paga no mesmo mês do fechamento (offset base 0)
    // Se vencimento <= fechamento: paga no mês seguinte ao fechamento (offset base 1)
    const mesesBase = (diaVencimento > diaFechamento) ? 0 : 1;
    mes += (diaComp <= diaFechamento) ? mesesBase : mesesBase + 1;
    if (mes > 11) { ano += Math.floor(mes / 12); mes = mes % 12; }
    return new Date(ano, mes, diaVencimento).toLocaleDateString('pt-BR');
}

function despesaAtualizarInfoCartao() {
    const tipoPag    = document.getElementById('novo-tipo_pagamento');
    const bancoSel   = document.getElementById('novo-forma_pagamento');
    const infoDiv    = document.getElementById('novo-info-credito');
    const textoSpan  = document.getElementById('novo-aviso-fatura-texto');
    const valorInp   = document.querySelector('#modal-nova-despesa input[name="valor"]');
    const parcelasInp= document.getElementById('novo-parcelas');
    const divVP      = document.getElementById('novo-valor-parcela');

    if (!tipoPag || tipoPag.value !== 'credito') {
        if (infoDiv) infoDiv.style.display = 'none';
        return;
    }
    if (infoDiv) infoDiv.style.display = 'block';

    const opt         = bancoSel ? bancoSel.options[bancoSel.selectedIndex] : null;
    const fechamento  = opt ? parseInt(opt.dataset.fechamento) : 0;
    const vencimento  = opt ? parseInt(opt.dataset.vencimento) : 0;
    const limite      = opt ? parseFloat(opt.dataset.limite) || 0 : 0;
    const nRaw        = parcelasInp ? parseInt(parcelasInp.value) : 1;
    const n           = isNaN(nRaw) ? 1 : nRaw; // pode ser 0 (recorrente mensal)
    const isRecMensal = n === 0;
    const valor       = parseFloat(valorInp?.value) || 0;
    const dataCompra  = document.querySelector('#modal-nova-despesa input[name="data_compra"]')?.value
                        || new Date().toISOString().substring(0,10);

    if (textoSpan) {
        if (!fechamento || !vencimento) {
            textoSpan.innerHTML = '⚠️ Configure o dia de fechamento e vencimento do cartão nas configurações do banco.';
        } else {
            const diaComp  = parseInt(dataCompra.split('-')[2]);
            const aviso    = diaComp > fechamento
                ? `⚠️ Compra após o fechamento (dia ${fechamento}) — entra na <strong>próxima fatura</strong>.`
                : `✅ Compra dentro do ciclo atual (fechamento dia ${fechamento}).`;
            const primVenc = despesaCalcVencimento(dataCompra, fechamento, vencimento);
            const parInfo  = isRecMensal
                ? `<br>🔁 <strong>Recorrente mensal</strong> — 1ª cobrança em <strong>${primVenc}</strong>`
                : n > 1
                    ? `<br>📅 <strong>${n}x</strong> — 1ª parcela vence em <strong>${primVenc}</strong>`
                    : `<br>📅 Vencimento da fatura: <strong>${primVenc}</strong>`;
            const limInfo  = limite > 0
                ? `<br>💳 Limite: <strong>R$ ${limite.toFixed(2).replace('.',',')}</strong>` : '';
            textoSpan.innerHTML = aviso + parInfo + limInfo;
        }
    }

    if (divVP && valor > 0) {
        if (isRecMensal) {
            divVP.textContent = `R$ ${valor.toFixed(2).replace('.',',')} / mês (recorrente)`;
        } else {
            const vp = (valor / (n || 1)).toFixed(2).replace('.',',');
            divVP.textContent = n > 1 ? `R$ ${vp} × ${n}` : `R$ ${valor.toFixed(2).replace('.',',')} (à vista)`;
        }
    } else if (divVP) {
        divVP.textContent = '—';
    }

    // Cartão de crédito: força recorrente quando parcelas > 1 ou = 0 (recorrente mensal)
    const recInput = document.getElementById('novo-recorrente');
    if (recInput) recInput.value = (n > 1 || isRecMensal) ? '1' : '';
    const recRow = document.getElementById('novo-parcelas-recorrente-row');
    const freqRow = document.getElementById('novo-frequencia-row');
    if (recRow) recRow.style.display = 'none';
    if (freqRow) freqRow.style.display = 'none';
    // Desabilita o campo de parcelas da recorrência para não sobrescrever o do cartão
    const parcelasRec = document.getElementById('novo-parcelas-rec');
    if (parcelasRec) parcelasRec.disabled = true;
}

function despesaOnBancoChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const ehCartao = opt && opt.dataset.credito === '1';
    const tipoPag  = document.getElementById('novo-tipo_pagamento');

    // Auto-seleciona "crédito" quando o banco selecionado é cartão de crédito
    // e o campo ainda não foi preenchido manualmente
    if (ehCartao && tipoPag && !tipoPag.value) {
        tipoPag.value = 'credito';
        despesaOnTipoPagChange(tipoPag);
    }

    despesaAtualizarInfoCartao();
}

function despesaOnTipoPagChange(sel) {
    const infoDiv = document.getElementById('novo-info-credito');
    const recRow  = document.getElementById('novo-parcelas-recorrente-row');
    const freqRow = document.getElementById('novo-frequencia-row');

    if (sel.value === 'credito') {
        despesaAtualizarInfoCartao();
        if (recRow)  recRow.style.display  = 'none';
        if (freqRow) freqRow.style.display = 'none';
    } else {
        if (infoDiv) infoDiv.style.display = 'none';
        // Permite configurar recorrência manual para outros tipos
        if (recRow)  recRow.style.display  = '';
        if (freqRow) freqRow.style.display = 'none'; // oculto até mudar parcelas
        // Reabilita o campo de parcelas da recorrência
        const parcelasRec = document.getElementById('novo-parcelas-rec');
        if (parcelasRec) parcelasRec.disabled = false;
    }
}

function despesaOnParcelasChange(inp) {
    despesaAtualizarInfoCartao();
}

// Atualiza valor/parcela ao digitar valor — somente se credito ativo
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#modal-nova-despesa form');
    if (!form) return;
    form.querySelector('input[name="valor"]')?.addEventListener('input', despesaAtualizarInfoCartao);
});

// ── Recorrência manual (não cartão) ──────────────────────────────────────────
function onParcelasChange(inputParcelas, idRecorrente, idFreqRow) {
    const val     = parseInt(inputParcelas.value) || 1;
    const recInput= document.getElementById(idRecorrente);
    const freqRow = document.getElementById(idFreqRow);
    if (recInput) recInput.value = (val !== 1) ? '1' : '';
    if (freqRow)  freqRow.style.display = (val !== 1) ? '' : 'none';
}

// ── Modal de edição ───────────────────────────────────────────────────────────

function editarDespesa(id, data) {
    document.getElementById('form-editar-despesa').action = `/despesas/${id}`;
    document.getElementById('edit-valor').value            = data.valor;
    document.getElementById('edit-data_compra').value      = data.data_compra ? data.data_compra.substring(0, 10) : '';
    document.getElementById('edit-data_pagamento').value   = data.data_pagamento ? data.data_pagamento.substring(0, 10) : '';
    document.getElementById('edit-quem_comprou').value     = data.quem_comprou || '';
    document.getElementById('edit-onde_comprou').value     = data.onde_comprou || '';
    document.getElementById('edit-categoria_id').value     = data.categoria_id || '';
    document.getElementById('edit-forma_pagamento').value  = data.forma_pagamento || '';
    document.getElementById('edit-tipo_pagamento').value   = data.tipo_pagamento || '';
    document.getElementById('edit-observacoes').value      = data.observacoes || '';

    const escopoContainer = document.getElementById('edit-escopo-container');
    escopoContainer.style.display = data.grupo_recorrencia_id ? 'block' : 'none';
    if (data.grupo_recorrencia_id) {
        document.getElementById('edit-escopo').value = 'apenas_esta';
    }

    openModal('modal-editar-despesa');
}

// ── Exclusão ──────────────────────────────────────────────────────────────────
let _excDespesaId = null;

function excluirDespesa(id, isRecorrente) {
    _excDespesaId = id;
    document.getElementById('form-excluir-despesa').action = `/despesas/${id}`;

    if (isRecorrente) {
        document.getElementById('exc-d-bloco-simples').style.display   = 'none';
        document.getElementById('exc-d-bloco-recorrente').style.display = '';
        // Reseta seleção
        document.querySelector('[name="opcao-excluir-d"][value="apenas_esta"]').checked = true;
        excDAtualizarSelecao();
    } else {
        document.getElementById('exc-d-bloco-simples').style.display   = '';
        document.getElementById('exc-d-bloco-recorrente').style.display = 'none';
    }
    openModal('modal-confirmar-exclusao-despesa');
}

function excDAtualizarSelecao() {
    const val = document.querySelector('[name="opcao-excluir-d"]:checked').value;
    document.getElementById('lbl-exc-d-apenas').style.borderColor  = val === 'apenas_esta'    ? 'var(--color-danger)' : 'var(--color-border)';
    document.getElementById('lbl-exc-d-futuras').style.borderColor = val === 'esta_e_futuras' ? 'var(--color-danger)' : 'var(--color-border)';
}

function confirmarExclusaoDespesa(escopoFixo) {
    const escopo = escopoFixo || document.querySelector('[name="opcao-excluir-d"]:checked').value;
    document.getElementById('escopo-excluir').value = escopo;
    document.getElementById('form-excluir-despesa').submit();
    closeModal('modal-confirmar-exclusao-despesa');
}

// ── Cadastro rápido (categoria / fornecedor) via mini-modal ─────────────────
const _cr_meta = {
    'categoria-despesa': { titulo: 'Nova Categoria (Despesa)', label: 'Nome da categoria *', icone: 'fa-tags',  cor: 'var(--color-danger)'  },
    'categoria-receita': { titulo: 'Nova Categoria (Receita)', label: 'Nome da categoria *', icone: 'fa-tags',  cor: 'var(--color-success)' },
    'fornecedor':        { titulo: 'Novo Fornecedor',          label: 'Nome do fornecedor *', icone: 'fa-store', cor: 'var(--color-primary)' },
};

let _crCtx = null;

function criarRapido(tipo, selectId) {
    const meta = _cr_meta[tipo];
    if (!meta) return;
    _crCtx = { tipo, selectId };

    document.getElementById('cr-titulo').textContent = meta.titulo;
    document.getElementById('cr-label').textContent  = meta.label;
    const ico = document.getElementById('cr-icone');
    ico.className = `fa-solid ${meta.icone}`;
    ico.style.color = meta.cor;

    const inp = document.getElementById('cr-nome');
    inp.value = '';
    document.getElementById('cr-erro').style.display = 'none';
    document.getElementById('cr-btn-salvar').disabled = false;

    openModal('modal-criar-rapido');
    setTimeout(() => inp.focus(), 50);
}

function confirmarCriarRapido() {
    if (!_crCtx) return;
    const inp   = document.getElementById('cr-nome');
    const erro  = document.getElementById('cr-erro');
    const btn   = document.getElementById('cr-btn-salvar');
    const nome  = (inp.value || '').trim();

    erro.style.display = 'none';
    if (!nome) {
        erro.textContent = 'Informe um nome.';
        erro.style.display = 'block';
        inp.focus();
        return;
    }

    btn.disabled = true;
    let url, body;
    if (_crCtx.tipo === 'fornecedor') {
        url = '/fornecedores/rapido';
        body = JSON.stringify({ nome });
    } else {
        const tipoCategoria = _crCtx.tipo === 'categoria-receita' ? 'RECEITA' : 'DESPESA';
        url = '/categorias/rapido';
        body = JSON.stringify({ nome, tipo: tipoCategoria });
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body,
    })
    .then(async r => {
        if (!r.ok) {
            const j = await r.json().catch(() => ({}));
            throw new Error(j.message || 'Erro ao cadastrar.');
        }
        return r.json();
    })
    .then(data => {
        const seletores = _crCtx.tipo === 'fornecedor'
            ? document.querySelectorAll('select[name="onde_comprou"]')
            : document.querySelectorAll('select[name="categoria_id"]');

        seletores.forEach(sel => sel.appendChild(new Option(data.nome, data.id)));
        const selectOrigem = document.getElementById(_crCtx.selectId);
        if (selectOrigem) selectOrigem.value = data.id;

        closeModal('modal-criar-rapido');
        _crCtx = null;
    })
    .catch(err => {
        erro.textContent = err.message || 'Erro ao cadastrar. Verifique se você tem permissão.';
        erro.style.display = 'block';
        btn.disabled = false;
    });
}
</script>
@endpush
