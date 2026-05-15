@extends('layouts.main')
@section('title', 'Contas Bancárias')
@section('page-title', 'Contas Bancárias')

@php
$bancosTemplate = [
    ['codigo_banco' => '341',  'nome' => 'Itaú',           'logo' => 'itau.svg',        'cor' => '#FF6600'],
    ['codigo_banco' => '237',  'nome' => 'Bradesco',        'logo' => 'bradesco.svg',    'cor' => '#CC0000'],
    ['codigo_banco' => '001',  'nome' => 'Banco do Brasil', 'logo' => 'bb.svg',          'cor' => '#FFCC00'],
    ['codigo_banco' => '104',  'nome' => 'Caixa',           'logo' => 'caixa.svg',       'cor' => '#0070AF'],
    ['codigo_banco' => '033',  'nome' => 'Santander',       'logo' => 'santander.svg',   'cor' => '#EC0000'],
    ['codigo_banco' => '260',  'nome' => 'Nubank',          'logo' => 'nubank.svg',      'cor' => '#8A05BE'],
    ['codigo_banco' => '077',  'nome' => 'Inter',           'logo' => 'inter.svg',       'cor' => '#FF6600'],
    ['codigo_banco' => '336',  'nome' => 'C6 Bank',         'logo' => 'c6.svg',          'cor' => '#242424'],
    ['codigo_banco' => '208',  'nome' => 'BTG Pactual',     'logo' => 'btg.svg',         'cor' => '#0A2240'],
    ['codigo_banco' => '102',  'nome' => 'XP',              'logo' => 'xp.svg',          'cor' => '#000000'],
    ['codigo_banco' => '380',  'nome' => 'PicPay',          'logo' => 'picpay.svg',      'cor' => '#21C25E'],
    ['codigo_banco' => '323',  'nome' => 'Mercado Pago',    'logo' => 'mercadopago.svg', 'cor' => '#009EE3'],
    ['codigo_banco' => '756',  'nome' => 'Sicoob',          'logo' => 'sicoob.svg',      'cor' => '#008E5A'],
    ['codigo_banco' => '748',  'nome' => 'Sicredi',         'logo' => 'sicredi.svg',     'cor' => '#5DAA31'],
    ['codigo_banco' => '422',  'nome' => 'Safra',           'logo' => 'safra.svg',       'cor' => '#1B3A6B'],
    ['codigo_banco' => null,   'nome' => 'Carteira',        'logo' => 'carteira.svg',    'cor' => '#6B7280'],
];
@endphp

@section('content')

<div class="section-header mb-4">
    <span></span>
    <button class="btn btn-primary" onclick="openModal('modal-novo-banco')">
        <i class="fa-solid fa-plus"></i> Nova Conta
    </button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(280px,100%),1fr));gap:14px;margin-bottom:24px;">
    @forelse($bancos as $banco)
        @php $cor = $banco->cor ?: 'var(--color-primary)'; @endphp
        <div class="card" style="border-top:3px solid {{ $cor }};box-shadow:0 -1px 0 var(--color-border-strong);">
            {{-- Header: Logo + Nome + Badges --}}
            <div class="d-flex justify-between align-center mb-3">
                <div class="d-flex align-center gap-2">
                    @if($banco->logo)
                        <div style="width:36px;height:36px;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--color-bg-container);flex-shrink:0;">
                            <img src="{{ asset('img/bancos/' . $banco->logo) }}" alt="{{ $banco->nome }}" style="width:32px;height:32px;object-fit:contain;">
                        </div>
                    @else
                        <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:{{ $cor }};flex-shrink:0;border:1px solid var(--color-border);">
                            <i class="fa-solid fa-building-columns" style="color:#fff;font-size:16px;"></i>
                        </div>
                    @endif
                    <div>
                        <div class="fw-600" style="font-size:15px;">{{ $banco->nome }}</div>
                        <div class="d-flex flex-wrap gap-1" style="margin-top:3px;">
                            @if($banco->eh_dinheiro)
                                <span class="badge" style="background:var(--color-bg-inset);color:var(--color-text-muted);font-size:10px;">Dinheiro</span>
                            @endif
                            @if($banco->tem_conta_corrente)
                                <span class="badge badge-blue" style="font-size:10px;">Conta Corrente</span>
                            @endif
                            @if($banco->tem_poupanca)
                                <span class="badge badge-green" style="font-size:10px;">Poupança</span>
                            @endif
                            @if($banco->tem_cartao_credito)
                                <span class="badge badge-amber" style="font-size:10px;">Cartão de Crédito</span>
                            @endif
                        </div>
                        @if($banco->titular)
                            <div style="font-size:11px;margin-top:2px;" class="text-subtle">
                                <i class="fa-solid fa-user"></i> {{ $banco->titular->nome }}
                            </div>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="editarBanco({{ $banco->id }}, {{ $banco->toJson() }})" class="btn btn-ghost btn-icon btn-sm" title="Editar">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" action="{{ route('bancos.destroy', $banco) }}" onsubmit="return confirm('Excluir esta conta?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-icon btn-sm text-red" title="Excluir">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Saldo Conta Corrente / Dinheiro --}}
            @if($banco->tem_conta_corrente || $banco->eh_dinheiro)
                <div style="background:var(--color-bg);border-radius:6px;padding:8px 12px;margin-bottom:8px;">
                    <div style="font-size:11px;color:var(--color-text-muted);">{{ $banco->eh_dinheiro ? 'Dinheiro' : 'Conta Corrente' }}</div>
                    <div class="fw-700 {{ $banco->saldo >= 0 ? 'text-green' : 'text-red' }}" style="font-size:1.2rem;">
                        R$ {{ number_format($banco->saldo, 2, ',', '.') }}
                    </div>
                </div>
            @endif

            {{-- Saldo Poupança --}}
            @if($banco->tem_poupanca)
                <div style="background:var(--color-success-soft);border-radius:6px;padding:8px 12px;margin-bottom:8px;">
                    <div style="font-size:11px;color:var(--color-success);">Poupança</div>
                    <div class="fw-700 text-green" style="font-size:1.2rem;">
                        R$ {{ number_format($banco->saldo_poupanca, 2, ',', '.') }}
                    </div>
                </div>
            @endif

            {{-- Cheque Especial --}}
            @if($banco->tem_conta_corrente && $banco->cheque_especial > 0)
                <div style="font-size:12px;margin-bottom:8px;" class="text-muted">
                    Cheque especial: R$ {{ number_format($banco->cheque_especial, 2, ',', '.') }}
                </div>
            @endif

            {{-- Cartão de Crédito --}}
            @if($banco->tem_cartao_credito && $banco->limite_cartao > 0)
                @php $perc = $banco->limite_cartao > 0 ? ($banco->saldo_cartao / $banco->limite_cartao) * 100 : 0; @endphp
                <div style="background:var(--color-warning-soft);border-radius:6px;padding:8px 12px;margin-bottom:8px;">
                    <div style="font-size:11px;color:var(--color-warning);margin-bottom:4px;">Cartão de Crédito</div>
                    <div class="d-flex justify-between" style="font-size:12px;margin-bottom:4px;">
                        <span class="text-muted">Utilizado</span>
                        <span class="fw-600">R$ {{ number_format($banco->saldo_cartao, 2, ',', '.') }} / {{ number_format($banco->limite_cartao, 2, ',', '.') }}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width:{{ min($perc,100) }}%;background:{{ $perc > 80 ? 'var(--color-danger)' : ($perc > 50 ? 'var(--color-warning)' : 'var(--color-success)') }};"></div>
                    </div>
                    @if($banco->dia_vencimento_cartao || $banco->dia_fechamento_cartao)
                        <div class="d-flex justify-between" style="font-size:11px;margin-top:6px;color:var(--color-text-muted);">
                            @if($banco->dia_fechamento_cartao)
                                <span>Fecha dia <strong>{{ $banco->dia_fechamento_cartao }}</strong></span>
                            @endif
                            @if($banco->dia_vencimento_cartao)
                                <span>Vence dia <strong>{{ $banco->dia_vencimento_cartao }}</strong></span>
                            @endif
                        </div>
                        @if($banco->dia_fechamento_cartao)
                            @php $melhorDia = $banco->dia_fechamento_cartao >= 28 ? 1 : $banco->dia_fechamento_cartao + 1; @endphp
                            <div style="font-size:11px;margin-top:4px;padding:4px 8px;background:var(--color-violet-soft);border-radius:4px;color:var(--color-violet);text-align:center;">
                                <i class="fa-solid fa-lightbulb"></i> Melhor dia de compra: <strong>{{ $melhorDia }}</strong>
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            {{-- Botões de Ação --}}
            <div class="d-flex gap-2 flex-wrap">
                @if($banco->tem_conta_corrente || $banco->eh_dinheiro)
                    <button onclick="ajustarSaldo({{ $banco->id }}, {{ $banco->saldo }})" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-sliders"></i> Saldo
                    </button>
                @endif
                @if($banco->tem_poupanca)
                    <button onclick="ajustarPoupanca({{ $banco->id }}, {{ $banco->saldo_poupanca }})" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-piggy-bank"></i> Poupança
                    </button>
                @endif
                @if($banco->tem_cartao_credito && $banco->limite_cartao > 0)
                    <button onclick="ajustarSaldoCartao({{ $banco->id }}, {{ $banco->saldo_cartao }})" class="btn btn-secondary btn-sm" style="flex:1;justify-content:center;">
                        <i class="fa-solid fa-credit-card"></i> Cartão
                    </button>
                @endif
            </div>
        </div>
    @empty
        <div class="card">
            <div class="empty-state">
                <i class="fa-solid fa-building-columns"></i>
                <p>Nenhuma conta cadastrada.<br>Clique em "Nova Conta" para começar.</p>
            </div>
        </div>
    @endforelse
</div>

{{-- Modal Nova Conta --}}
<div class="modal-backdrop" id="modal-novo-banco">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <i class="fa-solid fa-building-columns" style="color:var(--color-primary);"></i>
            <h3>Nova Conta</h3>
            <button class="modal-close" onclick="closeModal('modal-novo-banco')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('bancos.store') }}" id="form-novo-banco">
                @csrf
                <input type="hidden" name="logo" id="novo-logo">
                <input type="hidden" name="cor" id="novo-cor">
                <input type="hidden" name="codigo_banco" id="novo-codigo">

                {{-- Banco Picker --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:8px;">Banco</label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:8px;max-height:220px;overflow-y:auto;">
                        @foreach($bancosTemplate as $bt)
                        <button type="button"
                            class="banco-picker-btn"
                            data-nome="{{ $bt['nome'] }}"
                            data-logo="{{ $bt['logo'] }}"
                            data-cor="{{ $bt['cor'] }}"
                            data-codigo="{{ $bt['codigo_banco'] ?? '' }}"
                            onclick="selecionarBanco(this)"
                            style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:8px 4px;border:2px solid var(--color-border);border-radius:8px;background:var(--color-bg-card);cursor:pointer;transition:all .15s;">
                            <img src="{{ asset('img/bancos/' . $bt['logo']) }}" alt="{{ $bt['nome'] }}" style="width:32px;height:32px;object-fit:contain;">
                            <span style="font-size:10px;font-weight:600;color:var(--color-text);text-align:center;line-height:1.2;">{{ $bt['nome'] }}</span>
                        </button>
                        @endforeach
                    </div>
                    <div style="margin-top:6px;font-size:11px;" class="text-subtle">Ou preencha o nome manualmente abaixo</div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="novo-nome" class="form-control" required placeholder="Ex: Nubank, Carteira...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Titular</label>
                        <select name="titular_id" class="form-control">
                            <option value="">— Nenhum —</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Produtos ativos --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:8px;">Produtos ativos</label>
                    <div class="d-flex flex-wrap gap-3">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_conta_corrente" value="1" checked onchange="toggleCamposNovo()"> Conta Corrente
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_poupanca" value="1" onchange="toggleCamposNovo()"> Poupança
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_cartao_credito" value="1" onchange="toggleCamposNovo()"> Cartão de Crédito
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="eh_dinheiro" value="1" onchange="toggleCamposNovo()"> Dinheiro (Carteira)
                        </label>
                    </div>
                </div>

                <div class="form-grid">
                    {{-- Campos Conta Corrente / Dinheiro --}}
                    <div class="form-group" id="novo-grupo-saldo">
                        <label class="form-label">Saldo Inicial</label>
                        <input type="number" name="saldo" step="0.01" value="0" class="form-control">
                    </div>
                    <div class="form-group" id="novo-grupo-cheque">
                        <label class="form-label">Cheque Especial</label>
                        <input type="number" name="cheque_especial" step="0.01" value="0" min="0" class="form-control">
                    </div>

                    {{-- Campos Poupança --}}
                    <div class="form-group" id="novo-grupo-poupanca" style="display:none;">
                        <label class="form-label">Saldo Poupança</label>
                        <input type="number" name="saldo_poupanca" step="0.01" value="0" class="form-control">
                    </div>

                    {{-- Campos Cartão --}}
                    <div class="form-group" id="novo-grupo-limite" style="display:none;">
                        <label class="form-label">Limite do Cartão</label>
                        <input type="number" name="limite_cartao" step="0.01" value="0" min="0" class="form-control">
                    </div>
                    <div class="form-group" id="novo-grupo-vencimento" style="display:none;">
                        <label class="form-label">Dia Vencimento</label>
                        <input type="number" name="dia_vencimento_cartao" min="1" max="31" class="form-control" placeholder="Ex: 15">
                    </div>
                    <div class="form-group" id="novo-grupo-fechamento" style="display:none;">
                        <label class="form-label">Dia Fechamento</label>
                        <input type="number" name="dia_fechamento_cartao" min="1" max="31" class="form-control" placeholder="Ex: 5">
                    </div>

                    {{-- Agência e Conta --}}
                    <div class="form-group">
                        <label class="form-label">Agência</label>
                        <input type="text" name="agencia" class="form-control" placeholder="0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <input type="text" name="conta" class="form-control" placeholder="00000-0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-novo-banco')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar --}}
<div class="modal-backdrop" id="modal-editar-banco">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <i class="fa-solid fa-pen" style="color:var(--color-primary);"></i>
            <h3>Editar Conta</h3>
            <button class="modal-close" onclick="closeModal('modal-editar-banco')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-editar-banco">
                @csrf @method('PUT')
                <input type="hidden" name="logo" id="b-edit-logo">
                <input type="hidden" name="cor" id="b-edit-cor">
                <input type="hidden" name="codigo_banco" id="b-edit-codigo">

                {{-- Banco Picker (trocar logo) --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:8px;">Banco / Logo</label>
                    <div id="edit-banco-atual" style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <div id="edit-logo-preview" style="width:36px;height:36px;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:var(--color-bg-container);flex-shrink:0;">
                            <i class="fa-solid fa-building-columns" style="color:var(--color-text-muted);font-size:16px;"></i>
                        </div>
                        <span id="edit-logo-nome" style="font-size:13px;font-weight:600;color:var(--color-text);">—</span>
                        <button type="button" onclick="toggleEditBancoPicker()" class="btn btn-secondary btn-sm" style="margin-left:auto;">
                            <i class="fa-solid fa-arrows-rotate"></i> Trocar
                        </button>
                    </div>
                    <div id="edit-banco-picker" style="display:none;">
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(70px,1fr));gap:8px;max-height:220px;overflow-y:auto;">
                            @foreach($bancosTemplate as $bt)
                            <button type="button"
                                class="banco-picker-btn edit-picker-btn"
                                data-nome="{{ $bt['nome'] }}"
                                data-logo="{{ $bt['logo'] }}"
                                data-cor="{{ $bt['cor'] }}"
                                data-codigo="{{ $bt['codigo_banco'] ?? '' }}"
                                onclick="selecionarBancoEditar(this)"
                                style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:8px 4px;border:2px solid var(--color-border);border-radius:8px;background:var(--color-bg-card);cursor:pointer;transition:all .15s;">
                                <img src="{{ asset('img/bancos/' . $bt['logo']) }}" alt="{{ $bt['nome'] }}" style="width:32px;height:32px;object-fit:contain;">
                                <span style="font-size:10px;font-weight:600;color:var(--color-text);text-align:center;line-height:1.2;">{{ $bt['nome'] }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nome *</label>
                        <input type="text" name="nome" id="b-edit-nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Titular</label>
                        <select name="titular_id" id="b-edit-titular" class="form-control">
                            <option value="">— Nenhum —</option>
                            @foreach($familiares as $f)
                                <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Produtos ativos --}}
                <div style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:8px;">Produtos ativos</label>
                    <div class="d-flex flex-wrap gap-3">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_conta_corrente" value="1" id="b-edit-cc" onchange="toggleCamposEditar()"> Conta Corrente
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_poupanca" value="1" id="b-edit-poup" onchange="toggleCamposEditar()"> Poupança
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="tem_cartao_credito" value="1" id="b-edit-cartao" onchange="toggleCamposEditar()"> Cartão de Crédito
                        </label>
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" name="eh_dinheiro" value="1" id="b-edit-dinheiro" onchange="toggleCamposEditar()"> Dinheiro (Carteira)
                        </label>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group" id="edit-grupo-cheque">
                        <label class="form-label">Cheque Especial</label>
                        <input type="number" name="cheque_especial" id="b-edit-cheque" step="0.01" min="0" class="form-control">
                    </div>
                    <div class="form-group" id="edit-grupo-limite" style="display:none;">
                        <label class="form-label">Limite do Cartão</label>
                        <input type="number" name="limite_cartao" id="b-edit-limite" step="0.01" min="0" class="form-control">
                    </div>
                    <div class="form-group" id="edit-grupo-vencimento" style="display:none;">
                        <label class="form-label">Dia Vencimento</label>
                        <input type="number" name="dia_vencimento_cartao" id="b-edit-vencimento" min="1" max="31" class="form-control">
                    </div>
                    <div class="form-group" id="edit-grupo-fechamento" style="display:none;">
                        <label class="form-label">Dia Fechamento</label>
                        <input type="number" name="dia_fechamento_cartao" id="b-edit-fechamento" min="1" max="31" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Agência</label>
                        <input type="text" name="agencia" id="b-edit-agencia" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Conta</label>
                        <input type="text" name="conta" id="b-edit-conta" class="form-control">
                    </div>
                </div>
                <div style="margin-top:12px;padding:10px 12px;background:var(--color-bg-card);border-left:3px solid var(--color-primary);border-radius:6px;font-size:12px;color:var(--color-text-subtle);">
                    <i class="fa-solid fa-circle-info" style="margin-right:6px;color:var(--color-primary);"></i>
                    Os saldos são atualizados automaticamente. Para um ajuste manual, use o botão <strong>"Ajustar Saldo"</strong> em cada conta.
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-editar-banco')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Ajustar Saldo --}}
<div class="modal-backdrop" id="modal-ajustar-saldo">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <i class="fa-solid fa-sliders" style="color:var(--color-primary);"></i>
            <h3>Ajustar Saldo</h3>
            <button class="modal-close" onclick="closeModal('modal-ajustar-saldo')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-ajustar-saldo">
                @csrf
                <div class="form-group">
                    <label class="form-label">Novo Saldo</label>
                    <input type="number" name="saldo" id="ajuste-saldo-val" step="0.01" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-ajustar-saldo')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Ajustar Poupança --}}
<div class="modal-backdrop" id="modal-ajustar-poupanca">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <i class="fa-solid fa-piggy-bank" style="color:var(--color-success);"></i>
            <h3>Ajustar Saldo Poupança</h3>
            <button class="modal-close" onclick="closeModal('modal-ajustar-poupanca')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-ajustar-poupanca">
                @csrf
                <div class="form-group">
                    <label class="form-label">Novo Saldo</label>
                    <input type="number" name="saldo_poupanca" id="ajuste-poupanca-val" step="0.01" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-ajustar-poupanca')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Ajustar Cartão --}}
<div class="modal-backdrop" id="modal-ajustar-cartao">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <i class="fa-solid fa-credit-card" style="color:var(--color-warning);"></i>
            <h3>Ajustar Saldo do Cartão</h3>
            <button class="modal-close" onclick="closeModal('modal-ajustar-cartao')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="form-ajustar-cartao">
                @csrf
                <div class="form-group">
                    <label class="form-label">Valor Utilizado no Cartão</label>
                    <input type="number" name="saldo_cartao" id="ajuste-cartao-val" step="0.01" min="0" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-ajustar-cartao')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function selecionarBanco(btn) {
    document.querySelectorAll('.banco-picker-btn').forEach(b => {
        b.style.borderColor = 'var(--color-border)';
        b.style.background = 'var(--color-bg-card)';
    });
    btn.style.borderColor = btn.dataset.cor || 'var(--color-primary)';
    btn.style.background = 'var(--color-bg-container)';

    document.getElementById('novo-nome').value   = btn.dataset.nome;
    document.getElementById('novo-logo').value   = btn.dataset.logo;
    document.getElementById('novo-cor').value    = btn.dataset.cor;
    document.getElementById('novo-codigo').value = btn.dataset.codigo;
}

// Toggle campos no modal NOVO
function toggleCamposNovo() {
    const form = document.getElementById('form-novo-banco');
    const cc       = form.querySelector('[name="tem_conta_corrente"]').checked;
    const poup     = form.querySelector('[name="tem_poupanca"]').checked;
    const cartao   = form.querySelector('[name="tem_cartao_credito"]').checked;
    const dinheiro = form.querySelector('[name="eh_dinheiro"]').checked;

    document.getElementById('novo-grupo-saldo').style.display   = (cc || dinheiro) ? '' : 'none';
    document.getElementById('novo-grupo-cheque').style.display   = cc ? '' : 'none';
    document.getElementById('novo-grupo-poupanca').style.display = poup ? '' : 'none';
    document.getElementById('novo-grupo-limite').style.display   = cartao ? '' : 'none';
    document.getElementById('novo-grupo-vencimento').style.display = cartao ? '' : 'none';
    document.getElementById('novo-grupo-fechamento').style.display = cartao ? '' : 'none';
}

// Toggle campos no modal EDITAR
function toggleCamposEditar() {
    const cc       = document.getElementById('b-edit-cc').checked;
    const poup     = document.getElementById('b-edit-poup').checked;
    const cartao   = document.getElementById('b-edit-cartao').checked;
    const dinheiro = document.getElementById('b-edit-dinheiro').checked;

    document.getElementById('edit-grupo-cheque').style.display   = cc ? '' : 'none';
    document.getElementById('edit-grupo-limite').style.display   = cartao ? '' : 'none';
    document.getElementById('edit-grupo-vencimento').style.display = cartao ? '' : 'none';
    document.getElementById('edit-grupo-fechamento').style.display = cartao ? '' : 'none';
}

function editarBanco(id, data) {
    document.getElementById('form-editar-banco').action = `/bancos/${id}`;
    document.getElementById('b-edit-nome').value      = data.nome;
    document.getElementById('b-edit-titular').value   = data.titular_id || '';
    document.getElementById('b-edit-cheque').value    = data.cheque_especial;
    document.getElementById('b-edit-limite').value    = data.limite_cartao;
    document.getElementById('b-edit-vencimento').value = data.dia_vencimento_cartao || '';
    document.getElementById('b-edit-fechamento').value = data.dia_fechamento_cartao || '';
    document.getElementById('b-edit-agencia').value   = data.agencia || '';
    document.getElementById('b-edit-conta').value     = data.conta || '';
    document.getElementById('b-edit-logo').value      = data.logo || '';
    document.getElementById('b-edit-cor').value       = data.cor || '';
    document.getElementById('b-edit-codigo').value    = data.codigo_banco || '';

    // Checkboxes
    document.getElementById('b-edit-cc').checked       = !!data.tem_conta_corrente;
    document.getElementById('b-edit-poup').checked     = !!data.tem_poupanca;
    document.getElementById('b-edit-cartao').checked   = !!data.tem_cartao_credito;
    document.getElementById('b-edit-dinheiro').checked = !!data.eh_dinheiro;

    // Atualiza preview da logo atual
    atualizarEditLogoPreview(data.logo, data.nome);
    document.getElementById('edit-banco-picker').style.display = 'none';

    // Destaca o banco selecionado no picker
    document.querySelectorAll('.edit-picker-btn').forEach(b => {
        b.style.borderColor = (b.dataset.logo === data.logo) ? (b.dataset.cor || 'var(--color-primary)') : 'var(--color-border)';
        b.style.background = (b.dataset.logo === data.logo) ? 'var(--color-bg-container)' : 'var(--color-bg-card)';
    });

    toggleCamposEditar();
    openModal('modal-editar-banco');
}

function atualizarEditLogoPreview(logo, nome) {
    const container = document.getElementById('edit-logo-preview');
    const nomeEl = document.getElementById('edit-logo-nome');
    if (logo) {
        container.innerHTML = `<img src="/img/bancos/${logo}" alt="${nome}" style="width:32px;height:32px;object-fit:contain;">`;
    } else {
        container.innerHTML = '<i class="fa-solid fa-building-columns" style="color:var(--color-text-muted);font-size:16px;"></i>';
    }
    nomeEl.textContent = nome || '—';
}

function toggleEditBancoPicker() {
    const picker = document.getElementById('edit-banco-picker');
    picker.style.display = picker.style.display === 'none' ? '' : 'none';
}

function selecionarBancoEditar(btn) {
    document.querySelectorAll('.edit-picker-btn').forEach(b => {
        b.style.borderColor = 'var(--color-border)';
        b.style.background = 'var(--color-bg-card)';
    });
    btn.style.borderColor = btn.dataset.cor || 'var(--color-primary)';
    btn.style.background = 'var(--color-bg-container)';

    document.getElementById('b-edit-nome').value   = btn.dataset.nome;
    document.getElementById('b-edit-logo').value   = btn.dataset.logo;
    document.getElementById('b-edit-cor').value    = btn.dataset.cor;
    document.getElementById('b-edit-codigo').value = btn.dataset.codigo;

    atualizarEditLogoPreview(btn.dataset.logo, btn.dataset.nome);
    document.getElementById('edit-banco-picker').style.display = 'none';
}

function ajustarSaldo(id, saldo) {
    document.getElementById('form-ajustar-saldo').action = `/bancos/${id}/ajustar-saldo`;
    document.getElementById('ajuste-saldo-val').value = saldo;
    openModal('modal-ajustar-saldo');
}

function ajustarPoupanca(id, saldo) {
    document.getElementById('form-ajustar-poupanca').action = `/bancos/${id}/ajustar-saldo-poupanca`;
    document.getElementById('ajuste-poupanca-val').value = saldo;
    openModal('modal-ajustar-poupanca');
}

function ajustarSaldoCartao(id, saldo) {
    document.getElementById('form-ajustar-cartao').action = `/bancos/${id}/ajustar-saldo-cartao`;
    document.getElementById('ajuste-cartao-val').value = saldo;
    openModal('modal-ajustar-cartao');
}
</script>
@endpush
