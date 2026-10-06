@extends('layouts.main')
@section('title', 'Lançamento Diário')
@section('page-title', 'Lançamento Diário')

@section('content')

{{-- Cabeçalho com data de hoje --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <div>
        <div style="font-size:13px;color:var(--color-text-muted);">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
        @php $totalHoje = $lancamentosHoje->sum('valor'); @endphp
        @if($lancamentosHoje->count() > 0)
        <div style="font-size:13px;margin-top:2px;" class="text-muted">
            {{ $lancamentosHoje->count() }} lançamento(s) hoje —
            <span class="fw-600" style="color:#dc2626;">{{ brl($totalHoje) }}</span>
        </div>
        @endif
    </div>
</div>

{{-- ─── Botões principais ─────────────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:28px;">

    <button onclick="openModal('modal-manual')"
        class="btn-acao" style="border-color:var(--color-primary);background:#f5f3ff;"
        onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f5f3ff'">
        <div class="btn-acao-icon" style="background:var(--color-primary);">
            <i class="fa-solid fa-pen-to-square" style="color:#fff;font-size:22px;"></i>
        </div>
        <div>
            <div style="font-weight:700;font-size:14px;color:var(--color-text);">Lançamento Manual</div>
            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">Preencher os dados</div>
        </div>
    </button>

    <button onclick="document.getElementById('camera-input').click()"
        class="btn-acao" style="border-color:#16a34a;background:#f0fdf4;"
        onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
        <div class="btn-acao-icon" style="background:#16a34a;">
            <i class="fa-solid fa-camera" style="color:#fff;font-size:22px;"></i>
        </div>
        <div>
            <div style="font-weight:700;font-size:14px;color:var(--color-text);">Escanear Cupom</div>
            <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">Fotografar a notinha</div>
        </div>
    </button>
</div>

<style>
.btn-acao {
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:10px;padding:28px 16px;border-radius:12px;border:2px dashed;
    cursor:pointer;transition:background .15s;
}
.btn-acao-icon {
    width:52px;height:52px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
}
</style>

<input type="file" id="camera-input" accept="image/*" capture="environment" style="display:none;">

{{-- ─── Lançamentos de hoje ────────────────────────────────────────────── --}}
@if($lancamentosHoje->count() > 0)
<div class="card">
    <div class="card-title">
        <i class="fa-solid fa-calendar-day"></i> Lançamentos de Hoje
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Estabelecimento / Descrição</th>
                    <th>Categoria</th>
                    <th style="text-align:right;">Valor</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($lancamentosHoje as $l)
                <tr>
                    <td>
                        <div class="fw-600" style="font-size:13px;">{{ $l->fornecedor?->nome ?? '—' }}</div>
                        @if($l->observacoes)
                        <div style="font-size:11px;color:var(--color-text-muted);">{{ Str::limit($l->observacoes, 60) }}</div>
                        @endif
                    </td>
                    <td>
                        @if($l->categoria)
                        <span class="badge badge-slate">
                            <i class="fa-solid {{ $l->categoria->icone ?? 'fa-tag' }}"></i>
                            {{ $l->categoria->nome }}
                        </span>
                        @else —
                        @endif
                    </td>
                    <td style="text-align:right;" class="fw-600" style="color:#dc2626;">
                        {{ brl($l->valor) }}
                    </td>
                    <td>
                        <form method="POST" action="{{ route('despesas.destroy', $l) }}"
                            onsubmit="return confirm('Excluir este lançamento?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-icon btn-sm text-red" title="Excluir">
                                <i class="fa-solid fa-trash" style="font-size:11px;"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="fw-600" style="font-size:12px;color:var(--color-text-muted);padding-top:8px;">Total do dia</td>
                    <td style="text-align:right;padding-top:8px;" class="fw-700" style="color:#dc2626;">{{ brl($totalHoje) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@else
<div class="card" style="text-align:center;padding:32px;">
    <div class="empty-state">
        <i class="fa-solid fa-receipt" style="font-size:36px;color:var(--color-text-subtle);margin-bottom:10px;display:block;"></i>
        <p style="color:var(--color-text-muted);font-size:13px;">Nenhum lançamento registrado hoje.<br>Use os botões acima para começar.</p>
    </div>
</div>
@endif

{{-- ─── Loading overlay ─────────────────────────────────────────────────── --}}
<div id="scan-loading"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:500;
            align-items:center;justify-content:center;flex-direction:column;gap:14px;">
    <div style="width:56px;height:56px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;
         border-radius:50%;animation:ld-spin 0.7s linear infinite;"></div>
    <div id="scan-loading-msg" style="color:#fff;font-weight:600;font-size:15px;text-align:center;max-width:260px;line-height:1.4;"></div>
</div>
<style>@keyframes ld-spin { to { transform: rotate(360deg); } }</style>

{{-- ─── Toast de aviso ──────────────────────────────────────────────────── --}}
<div id="scan-toast"
     style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:#1e293b;color:#fff;padding:11px 22px;border-radius:8px;font-size:13px;
            z-index:600;box-shadow:0 4px 16px rgba(0,0,0,.35);max-width:340px;text-align:center;"></div>

{{-- ─── Modal Lançamento Manual ─────────────────────────────────────────── --}}
<div class="modal-backdrop" id="modal-manual">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <i class="fa-solid fa-pen-to-square" style="color:var(--color-primary);"></i>
            <h3>Lançamento Manual</h3>
            <button class="modal-close" onclick="closeModal('modal-manual')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('despesas.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor (R$) *</label>
                        <input type="number" name="valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00" autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data *</label>
                        <input type="date" name="data_compra" class="form-control" required value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estabelecimento</label>
                        <select name="onde_comprou" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($fornecedores as $f)
                            <option value="{{ $f->id }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem Comprou</label>
                        <select name="quem_comprou" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($familiares as $f)
                            <option value="{{ $f->id }}" {{ $f->id == $meuFamiliarId ? 'selected' : '' }}>{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="forma_pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                            <option value="{{ $b->id }}" data-tipo="{{ $b->tipo_conta }}" data-nome="{{ strtolower($b->nome) }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Descrição / Itens</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Ex: Coca-Cola, almoço, compras da semana...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-manual')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ─── Modal Confirmar Cupom (OCR / QR) ───────────────────────────────── --}}
<div class="modal-backdrop" id="modal-cupom">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <i class="fa-solid fa-receipt" style="color:#16a34a;"></i>
            <h3>Confirmar Cupom Fiscal</h3>
            <button class="modal-close" onclick="closeModal('modal-cupom')">&times;</button>
        </div>
        <div class="modal-body">

            {{-- Painel de info do cupom lido --}}
            <div id="cupom-info-box"
                 style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;margin-bottom:16px;display:none;">
                <div style="font-size:10px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                    <i class="fa-solid fa-circle-check"></i> Cupom lido com sucesso
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <span id="ci-estabelecimento" class="badge badge-green" style="display:none;font-size:11px;"></span>
                    <span id="ci-cnpj"            class="badge badge-slate" style="display:none;font-size:11px;"></span>
                    <span id="ci-numero"          class="badge badge-slate" style="display:none;font-size:11px;"></span>
                    <span id="ci-pagamento"       class="badge badge-amber" style="display:none;font-size:11px;"></span>
                </div>
                {{-- Itens detectados --}}
                <div id="ci-itens-wrap" style="margin-top:10px;display:none;">
                    <div style="font-size:10px;color:#15803d;font-weight:600;margin-bottom:4px;">ITENS DETECTADOS</div>
                    <ul id="ci-itens-lista" style="margin:0;padding-left:16px;font-size:12px;color:#166534;line-height:1.7;"></ul>
                </div>
            </div>

            <form method="POST" action="{{ route('despesas.store') }}" id="form-cupom">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Valor Total (R$) *</label>
                        <input type="number" name="valor" id="cupom-valor" step="0.01" min="0.01" class="form-control" required placeholder="0,00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data do Cupom *</label>
                        <input type="date" name="data_compra" id="cupom-data" class="form-control" required>
                    </div>
                    <div class="form-group span-2">
                        <label class="form-label">Descrição dos Itens</label>
                        <input type="text" name="observacoes" id="cupom-descricao" class="form-control" placeholder="Coca-Cola, arroz, feijão...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select name="categoria_id" id="cupom-categoria" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quem Comprou</label>
                        <select name="quem_comprou" id="cupom-quem" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($familiares as $f)
                            <option value="{{ $f->id }}" {{ $f->id == $meuFamiliarId ? 'selected' : '' }}>{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pagamento</label>
                        <select name="forma_pagamento" id="cupom-pagamento" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($bancos as $b)
                            <option value="{{ $b->id }}"
                                data-tipo="{{ strtolower($b->tipo_conta) }}"
                                data-nome="{{ strtolower($b->nome) }}">{{ $b->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estabelecimento</label>
                        <select name="onde_comprou" id="cupom-fornecedor" class="form-control">
                            <option value="">— Selecione —</option>
                            @foreach($fornecedores as $f)
                            <option value="{{ $f->id }}"
                                data-nome="{{ strtolower($f->nome) }}"
                                data-cnpj="{{ $f->cnpj ?? '' }}">{{ $f->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('modal-cupom')" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check"></i> Confirmar e Registrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── Captura de imagem ────────────────────────────────────────────────────
document.getElementById('camera-input').addEventListener('change', function (e) {
    const file = e.target.files[0];
    if (!file) return;
    this.value = '';

    const reader = new FileReader();
    reader.onload = function (ev) {
        const dataUrl = ev.target.result;
        const img = new Image();
        img.onload = function () {
            // Redimensiona para QR scan e OCR (max 1600px)
            const maxDim = 1600;
            let w = img.width, h = img.height;
            if (w > maxDim || h > maxDim) {
                if (w > h) { h = Math.round(h * maxDim / w); w = maxDim; }
                else       { w = Math.round(w * maxDim / h); h = maxDim; }
            }
            const canvas = document.createElement('canvas');
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);

            // 1º: tenta QR code (NFC-e / SAT)
            const imageData = ctx.getImageData(0, 0, w, h);
            const qr = jsQR(imageData.data, w, h, { inversionAttempts: 'dontInvert' });

            if (qr && qr.data) {
                enviarParaServidor({ qr_code: qr.data }, 'Lendo QR code...');
            } else {
                // 2º: não encontrou QR → manda imagem para OCR via Claude Vision
                const mime = dataUrl.split(';')[0].split(':')[1] || 'image/jpeg';
                const base64 = canvas.toDataURL(mime, 0.85).split(',')[1];
                enviarParaServidor({ imagem: base64, mime }, 'Analisando cupom com IA...');
            }
        };
        img.src = dataUrl;
    };
    reader.readAsDataURL(file);
});

// ── Envia para /lancamentos-diarios/escanear ─────────────────────────────
function enviarParaServidor(payload, msg) {
    showLoading(true, msg);

    fetch('{{ route('lancamentos-diarios.escanear') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        showLoading(false);
        if (data.erro) { showToast('⚠ ' + data.erro, 5000); return; }
        preencherModalCupom(data);
    })
    .catch(() => {
        showLoading(false);
        showToast('Erro de conexão. Use o lançamento manual.', 4000);
    });
}

// ── Preenche o modal com os dados extraídos ──────────────────────────────
function preencherModalCupom(d) {
    // Campos principais
    document.getElementById('cupom-valor').value    = d.valor ? parseFloat(d.valor).toFixed(2) : '';
    document.getElementById('cupom-data').value     = d.data  || '{{ now()->format('Y-m-d') }}';
    document.getElementById('cupom-descricao').value = d.descricao || '';

    // Painel de informações do cupom
    const box = document.getElementById('cupom-info-box');
    box.style.display = 'none';
    let temInfo = false;

    function setBadge(id, texto, prefixo) {
        const el = document.getElementById(id);
        if (texto) { el.textContent = (prefixo ? prefixo + ' ' : '') + texto; el.style.display = ''; temInfo = true; }
        else        el.style.display = 'none';
    }

    setBadge('ci-estabelecimento', d.estabelecimento, '🏪');
    setBadge('ci-cnpj',            d.cnpj,            'CNPJ:');
    setBadge('ci-numero',          d.numero_cupom,    'Cupom nº');
    setBadge('ci-pagamento',       d.forma_pagamento,  '💳');

    // Lista de itens detectados
    const itensWrap  = document.getElementById('ci-itens-wrap');
    const itensList  = document.getElementById('ci-itens-lista');
    itensList.innerHTML = '';
    if (d.itens && d.itens.length > 0) {
        d.itens.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item;
            itensList.appendChild(li);
        });
        itensWrap.style.display = 'block';
        temInfo = true;
    } else {
        itensWrap.style.display = 'none';
    }

    if (temInfo) box.style.display = 'block';

    // Auto-selecionar forma de pagamento
    autoSelecionarPagamento(d.forma_pagamento);

    // Tentar casar estabelecimento pelo CNPJ ou nome
    autoSelecionarFornecedor(d.estabelecimento, d.cnpj);

    openModal('modal-cupom');
}

// ── Auto-seleciona banco pela forma de pagamento detectada ───────────────
function autoSelecionarPagamento(formaPag) {
    if (!formaPag) return;
    const fp = formaPag.toLowerCase();
    const sel = document.getElementById('cupom-pagamento');

    // Mapeamento: palavra-chave → tipo de conta ou nome do banco
    const mapa = [
        { keys: ['dinheiro', 'espécie', 'especie', 'cash'],      tipo: 'dinheiro',            nome: 'carteira' },
        { keys: ['pix'],                                          tipo: null,                  nome: 'pix'      },
        { keys: ['crédito', 'credito', 'credit'],                 tipo: 'cartão de crédito',   nome: null       },
        { keys: ['débito', 'debito', 'debit'],                    tipo: null,                  nome: 'débito'   },
    ];

    for (const m of mapa) {
        if (m.keys.some(k => fp.includes(k))) {
            // Procura por tipo de conta ou nome
            for (const opt of sel.options) {
                const optTipo = opt.dataset.tipo || '';
                const optNome = opt.dataset.nome || '';
                if ((m.tipo && optTipo.includes(m.tipo)) || (m.nome && optNome.includes(m.nome))) {
                    sel.value = opt.value;
                    return;
                }
            }
            break;
        }
    }
}

// ── Tenta casar o fornecedor pelo nome ou CNPJ ──────────────────────────
function autoSelecionarFornecedor(nome, cnpj) {
    const sel = document.getElementById('cupom-fornecedor');
    if (!nome && !cnpj) return;

    const nomeLower = (nome || '').toLowerCase();
    const cnpjLimpo = (cnpj || '').replace(/\D/g, '');

    for (const opt of sel.options) {
        const optNome = opt.dataset.nome || '';
        const optCnpj = (opt.dataset.cnpj || '').replace(/\D/g, '');

        if (cnpjLimpo && optCnpj && cnpjLimpo === optCnpj) { sel.value = opt.value; return; }
        if (nomeLower && optNome && optNome.includes(nomeLower.substring(0, 5))) { sel.value = opt.value; return; }
    }
}

// ── Utilitários ─────────────────────────────────────────────────────────
function showLoading(visible, msg) {
    const el = document.getElementById('scan-loading');
    el.style.display = visible ? 'flex' : 'none';
    if (msg) document.getElementById('scan-loading-msg').textContent = msg;
}

function showToast(msg, duration) {
    const t = document.getElementById('scan-toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._tid);
    t._tid = setTimeout(() => { t.style.display = 'none'; }, duration || 3500);
}
</script>
@endpush
