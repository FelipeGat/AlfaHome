{{-- "+ Lançar": saída, entrada ou transferência, e a edição de um lançamento do
     sistema. Valida e grava pelas mesmas regras da API do app (LancamentoManualController).
     Abre com [data-lancar] (opcional: "saida" | "entrada" | "transferencia") ou
     [data-editar="saida:12"]. --}}
@php
    $lancarContas = $lancarBancos->filter(fn ($b) => $b->tem_conta_corrente || $b->tem_poupanca || $b->eh_dinheiro)->values();
    $lancarCartoes = $lancarBancos->filter(fn ($b) => $b->tem_cartao_credito)->values();
@endphp
<style>
.lm [hidden] { display:none !important; }
.lm-tipos { display:grid; grid-template-columns:repeat(3,1fr); gap:4px; padding:4px; background:var(--color-bg); border-radius:10px; margin-bottom:16px; }
.lm-tipos button { border:0; background:transparent; padding:9px 6px; border-radius:7px; font-size:14px; font-weight:500; color:var(--color-text-muted); cursor:pointer; }
.lm-tipos button[aria-pressed="true"] { background:var(--color-bg-card); color:var(--color-text); box-shadow:0 1px 3px rgba(15,23,42,.12); }
.lm-tipos button[data-tipo="saida"][aria-pressed="true"] { color:var(--color-danger); }
.lm-tipos button[data-tipo="entrada"][aria-pressed="true"] { color:var(--color-success); }
.lm-valor { font-size:28px !important; font-weight:600; padding:10px 12px !important; letter-spacing:-.01em; font-variant-numeric:tabular-nums; }
.lm-grade { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.lm-grade .lm-todo { grid-column:1 / -1; }
.lm form .form-control { font-size:15px; padding:10px 12px; }
.lm .form-label { text-transform:none; letter-spacing:0; font-size:13px; font-weight:500; }
.lm-erro { color:var(--color-danger); font-size:12.5px; min-height:0; }
.lm-erro:empty { display:none; }
.lm-mais { margin-top:14px; border-top:1px solid var(--color-border); padding-top:10px; }
.lm-mais summary { cursor:pointer; font-size:14px; font-weight:500; color:var(--color-primary); list-style:none; padding:4px 0; }
.lm-mais summary::-webkit-details-marker { display:none; }
.lm-mais[open] summary { margin-bottom:10px; }
.lm-sit { display:flex; gap:8px; }
.lm-sit label { flex:1; display:flex; align-items:center; justify-content:center; gap:6px; border:1px solid var(--color-border); border-radius:8px; padding:9px; font-size:14px; cursor:pointer; }
.lm-sit input { accent-color:var(--color-primary); }
.lm-sit label:has(input:checked) { border-color:var(--color-primary); background:var(--color-primary-soft); color:var(--color-primary); }
.lm-dica { font-size:12.5px; color:var(--color-text-muted); margin-top:4px; }
.lm-aviso { background:var(--color-danger-soft); color:var(--color-danger); border-radius:8px; padding:9px 12px; font-size:13.5px; margin-bottom:12px; }
.lm .modal-footer { display:flex; gap:8px; align-items:center; padding:14px 20px; border-top:1px solid var(--color-border); flex-wrap:wrap; }
.lm .modal-footer .lm-esp { flex:1; }
.lm .btn { font-size:14px; padding:10px 16px; }
.lm-fab { display:none; }
@media (max-width:767px) {
    .lm-fab { display:flex; position:fixed; right:16px; bottom:calc(var(--bottom-nav-h, 60px) + 16px + env(safe-area-inset-bottom, 0px)); z-index:250; width:56px; height:56px; border-radius:50%; border:0; background:var(--color-primary); color:#fff; font-size:22px; align-items:center; justify-content:center; box-shadow:0 8px 20px rgba(37,99,235,.35); cursor:pointer; }
    .lm-grade { grid-template-columns:1fr; }
    .lm .modal-footer { padding:12px 16px calc(12px + env(safe-area-inset-bottom, 0px)); }
}
</style>

<button type="button" class="lm-fab" data-lancar aria-label="Lançar"><i class="fa-solid fa-plus"></i></button>

<div class="modal-backdrop lm" id="lm" role="dialog" aria-modal="true" aria-labelledby="lm-titulo">
    <div class="modal">
        <div class="modal-header">
            <h3 id="lm-titulo">Lançar</h3>
            <button type="button" class="modal-close" data-lm-fechar aria-label="Fechar" style="background:none;border:0;font-size:18px;cursor:pointer;color:var(--color-text-muted);padding:4px 8px;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="lm-form" novalidate>
            <div class="modal-body" style="padding:18px 20px;">
                <div class="lm-tipos" role="group" aria-label="Tipo de lançamento">
                    <button type="button" data-tipo="saida" aria-pressed="true">Saída</button>
                    <button type="button" data-tipo="entrada" aria-pressed="false">Entrada</button>
                    <button type="button" data-tipo="transferencia" aria-pressed="false">Transferência</button>
                </div>
                <div class="lm-aviso" id="lm-aviso" hidden></div>
                @if($lancarBancos->isEmpty())
                    <p class="lm-dica" style="background:var(--color-primary-soft);color:var(--color-text);border-radius:8px;padding:10px 12px;margin:0 0 12px;font-size:13.5px;">
                        Para lançar, cadastre antes uma conta ou cartão. <a href="{{ route('bancos.index', ['nova' => 1]) }}" style="color:var(--color-primary);font-weight:600;">Adicionar conta</a>
                    </p>
                @endif

                <div class="lm-grade">
                    <div class="form-group lm-todo">
                        <label class="form-label" for="lm-valor">Valor</label>
                        <input class="form-control lm-valor" id="lm-valor" name="valor" inputmode="decimal" autocomplete="off" placeholder="0,00" required>
                        <span class="lm-erro" data-erro="valor"></span>
                    </div>
                    <div class="form-group lm-todo">
                        <label class="form-label" for="lm-descricao">Descrição</label>
                        <input class="form-control" id="lm-descricao" name="descricao" maxlength="200" autocomplete="off" placeholder="Ex.: Supermercado">
                        <span class="lm-erro" data-erro="descricao"></span>
                    </div>

                    <div class="form-group" data-so="saida entrada">
                        <label class="form-label" for="lm-categoria">Categoria</label>
                        <select class="form-control" id="lm-categoria" name="categoria_id">
                            <option value="">Sem categoria</option>
                            @foreach($lancarCategorias as $c)
                                <option value="{{ $c->id }}" data-tipo="{{ strtolower($c->tipo) === 'receita' ? 'entrada' : 'saida' }}">{{ $c->nome }}</option>
                            @endforeach
                        </select>
                        <span class="lm-erro" data-erro="categoria_id"></span>
                    </div>
                    <div class="form-group" data-so="saida entrada">
                        <label class="form-label" for="lm-conta">Conta</label>
                        <select class="form-control" id="lm-conta" name="conta" required>
                            <option value="">Escolha…</option>
                            @if($lancarContas->isNotEmpty())
                                <optgroup label="Contas">
                                    @foreach($lancarContas as $b)<option value="conta:{{ $b->id }}">{{ $b->nome }}</option>@endforeach
                                </optgroup>
                            @endif
                            @if($lancarCartoes->isNotEmpty())
                                <optgroup label="Cartões de crédito" data-so="saida">
                                    @foreach($lancarCartoes as $b)<option value="cartao:{{ $b->id }}">{{ $b->nome }} · cartão</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                        <span class="lm-erro" data-erro="conta"></span>
                        <span class="lm-dica" id="lm-dica-cartao" hidden>Compra no cartão entra na fatura e só sai da conta quando a fatura for paga.</span>
                    </div>

                    <div class="form-group" data-so="transferencia">
                        <label class="form-label" for="lm-origem">De</label>
                        <select class="form-control" id="lm-origem" name="origem_id">
                            <option value="">Escolha…</option>
                            @foreach($lancarContas as $b)<option value="{{ $b->id }}">{{ $b->nome }}</option>@endforeach
                        </select>
                        <span class="lm-erro" data-erro="origem_id"></span>
                    </div>
                    <div class="form-group" data-so="transferencia">
                        <label class="form-label" for="lm-destino">Para</label>
                        <select class="form-control" id="lm-destino" name="destino_id">
                            <option value="">Escolha…</option>
                            @foreach($lancarContas as $b)<option value="{{ $b->id }}">{{ $b->nome }}</option>@endforeach
                        </select>
                        <span class="lm-erro" data-erro="destino_id"></span>
                    </div>

                    <div class="form-group lm-todo">
                        <label class="form-label" for="lm-data">Data</label>
                        <input class="form-control" type="date" id="lm-data" name="data" required>
                        <span class="lm-erro" data-erro="data"></span>
                    </div>
                </div>

                <details class="lm-mais" id="lm-mais" data-so="saida entrada">
                    <summary>Mais opções</summary>
                    <div class="lm-grade">
                        <div class="form-group lm-todo" id="lm-sit-grupo">
                            <span class="form-label">Situação</span>
                            <div class="lm-sit">
                                <label><input type="radio" name="situacao" value="pago" checked> <span data-rotulo-pago>Pago</span></label>
                                <label><input type="radio" name="situacao" value="pendente"> <span data-rotulo-pendente>A pagar</span></label>
                            </div>
                        </div>
                        <div class="form-group" data-novo>
                            <label class="form-label" for="lm-repete">Repetição</label>
                            <select class="form-control" id="lm-repete" name="repete">
                                <option value="nao">Não repete</option>
                                <option value="parcelas">Em parcelas</option>
                                <option value="mensal">Todo mês</option>
                            </select>
                        </div>
                        <div class="form-group" data-novo id="lm-parcelas-grupo" hidden>
                            <label class="form-label" for="lm-parcelas">Quantas vezes</label>
                            <input class="form-control" type="number" id="lm-parcelas" name="parcelas" min="2" max="360" value="2">
                            <span class="lm-erro" data-erro="parcelas"></span>
                            <span class="lm-dica" id="lm-dica-parcelas">No cartão, o valor é dividido entre as parcelas.</span>
                        </div>
                        <div class="form-group" id="lm-forma-grupo">
                            <label class="form-label" for="lm-forma">Forma</label>
                            <select class="form-control" id="lm-forma" name="forma">
                                <option value="pix">Pix</option>
                                <option value="debito">Débito</option>
                                <option value="dinheiro">Dinheiro</option>
                                <option value="boleto">Boleto</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                        </div>
                        <div class="form-group" id="lm-escopo-grupo" hidden>
                            <label class="form-label" for="lm-escopo">Aplicar em</label>
                            <select class="form-control" id="lm-escopo" name="escopo">
                                <option value="apenas_esta">Só neste</option>
                                <option value="esta_e_futuras">Neste e nos próximos</option>
                            </select>
                        </div>
                    </div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="lm-excluir" hidden style="color:var(--color-danger);"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                <button type="button" class="btn btn-secondary" id="lm-pagar" hidden><i class="fa-solid fa-check"></i> <span>Marcar pago</span></button>
                <span class="lm-esp"></span>
                <button type="button" class="btn btn-secondary" data-lm-fechar>Cancelar</button>
                <button type="submit" class="btn btn-primary" id="lm-salvar">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('lm'), form = document.getElementById('lm-form');
    if (!modal) return;
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    var el = function (id) { return document.getElementById(id); };
    var estado = { tipo: 'saida', id: null, serie: false, pagoEm: null };
    var hoje = function () { var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
    var titulos = { saida: 'Nova saída', entrada: 'Nova entrada', transferencia: 'Nova transferência' };
    var nomes = { saida: 'saída', entrada: 'entrada', transferencia: 'transferência' };

    function cartao() { return el('lm-conta').value.indexOf('cartao:') === 0; }

    function mostrarTipo(tipo) {
        estado.tipo = tipo;
        modal.querySelectorAll('.lm-tipos button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.tipo === tipo ? 'true' : 'false'); });
        // `disabled` junto com `hidden`: o Safari não esconde opção de select.
        modal.querySelectorAll('[data-so]').forEach(function (n) { n.hidden = n.dataset.so.split(' ').indexOf(tipo) < 0; if (n.tagName === 'OPTGROUP') n.disabled = n.hidden; });
        // Categorias do tipo; cartão só em saída.
        el('lm-categoria').querySelectorAll('option[data-tipo]').forEach(function (o) { o.hidden = o.disabled = o.dataset.tipo !== tipo; });
        var cat = el('lm-categoria').selectedOptions[0];
        if (cat && cat.dataset.tipo && cat.dataset.tipo !== tipo) el('lm-categoria').value = '';
        if (tipo !== 'saida' && cartao()) el('lm-conta').value = '';
        modal.querySelector('[data-rotulo-pago]').textContent = tipo === 'entrada' ? 'Recebido' : 'Pago';
        modal.querySelector('[data-rotulo-pendente]').textContent = tipo === 'entrada' ? 'A receber' : 'A pagar';
        el('lm-titulo').textContent = estado.id ? 'Editar ' + nomes[tipo] : titulos[tipo];
        ajustar();
    }

    function ajustar() {
        var noCartao = estado.tipo === 'saida' && cartao();
        el('lm-dica-cartao').hidden = !noCartao;
        el('lm-sit-grupo').hidden = noCartao;
        el('lm-forma-grupo').hidden = noCartao;
        el('lm-dica-parcelas').hidden = !noCartao;
        el('lm-parcelas-grupo').hidden = estado.id || el('lm-repete').value !== 'parcelas';
        // Data futura começa como pendente; hoje ou antes, como pago.
        if (!estado.id && !estado.situacaoTocada) {
            var futura = el('lm-data').value > hoje();
            form.querySelector('input[name="situacao"][value="' + (futura ? 'pendente' : 'pago') + '"]').checked = true;
        }
    }

    function limparErros() {
        modal.querySelectorAll('[data-erro]').forEach(function (s) { s.textContent = ''; });
        el('lm-aviso').hidden = true;
    }

    var campoDoErro = {
        valor: 'valor', data_compra: 'data', data_prevista_recebimento: 'data', data: 'data',
        forma_pagamento: 'conta', forma_recebimento: 'conta', categoria_id: 'categoria_id',
        origem_id: 'origem_id', destino_id: 'destino_id', observacoes: 'descricao', observacao: 'descricao', parcelas: 'parcelas',
    };
    function mostrarErros(json) {
        var resto = [];
        Object.keys(json.errors || {}).forEach(function (k) {
            var alvo = modal.querySelector('[data-erro="' + (campoDoErro[k] || k) + '"]');
            if (alvo && !alvo.closest('[hidden]')) alvo.textContent = json.errors[k][0];
            else resto.push(json.errors[k][0]);
        });
        if (resto.length || !json.errors) { el('lm-aviso').textContent = resto.join(' ') || json.message || 'Não foi possível salvar. Tente de novo.'; el('lm-aviso').hidden = false; }
    }

    function abrir(tipo) {
        form.reset(); limparErros();
        estado = { tipo: tipo || 'saida', id: null, serie: false, pagoEm: null, situacaoTocada: false };
        el('lm-data').value = hoje();
        modal.querySelector('.lm-tipos').hidden = false;
        modal.querySelectorAll('[data-novo]').forEach(function (n) { n.hidden = false; });
        el('lm-escopo-grupo').hidden = true;
        el('lm-excluir').hidden = true; el('lm-pagar').hidden = true;
        el('lm-mais').open = false;
        mostrarTipo(estado.tipo);
        modal.classList.add('active');
        setTimeout(function () { el('lm-valor').focus(); }, 50);
    }

    function editar(tipo, id) {
        fetch('{{ url('lancar') }}/' + tipo + '/' + id, { headers: { Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) throw r; return r.json(); })
            .then(function (d) {
                abrir(tipo);
                estado.id = d.id; estado.serie = d.serie; estado.pagoEm = d.pago_em || null; estado.situacaoTocada = true;
                modal.querySelector('.lm-tipos').hidden = true;
                modal.querySelectorAll('[data-novo]').forEach(function (n) { n.hidden = true; });
                el('lm-valor').value = String(d.valor.toFixed(2)).replace('.', ',');
                el('lm-descricao').value = d.descricao || '';
                el('lm-data').value = d.data;
                if (tipo === 'transferencia') {
                    el('lm-origem').value = d.origem_id; el('lm-destino').value = d.destino_id;
                } else {
                    el('lm-categoria').value = d.categoria_id || '';
                    el('lm-conta').value = (d.forma === 'credito' ? 'cartao:' : 'conta:') + (d.conta_id || '');
                    if (d.forma && d.forma !== 'credito') el('lm-forma').value = d.forma;
                    form.querySelector('input[name="situacao"][value="' + (d.pago_em ? 'pago' : 'pendente') + '"]').checked = true;
                    el('lm-escopo-grupo').hidden = !d.serie;
                    el('lm-mais').open = d.serie;
                    el('lm-pagar').hidden = !!d.pago_em || d.forma === 'credito';
                    el('lm-pagar').querySelector('span').textContent = tipo === 'entrada' ? 'Marcar recebido' : 'Marcar pago';
                }
                el('lm-excluir').hidden = false;
                mostrarTipo(tipo);
            })
            .catch(function () { alert('Não foi possível abrir este lançamento.'); });
    }

    function fechar() { modal.classList.remove('active'); }

    function enviar(metodo, url, corpo) {
        el('lm-salvar').disabled = true;
        return fetch(url, {
            method: metodo,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: corpo ? JSON.stringify(corpo) : undefined,
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (r.ok) { location.reload(); return; }
                el('lm-salvar').disabled = false;
                if (r.status === 419) { j = { message: 'Sua sessão expirou. Recarregue a página.' }; }
                mostrarErros(j);
            });
        }).catch(function () { el('lm-salvar').disabled = false; mostrarErros({ message: 'Sem conexão. Tente de novo.' }); });
    }

    function corpo() {
        var t = estado.tipo, valor = el('lm-valor').value.trim(), data = el('lm-data').value, desc = el('lm-descricao').value.trim() || null;
        if (t === 'transferencia') {
            return { valor: valor, data: data, origem_id: el('lm-origem').value || null, destino_id: el('lm-destino').value || null, observacao: desc };
        }
        var conta = el('lm-conta').value, contaId = conta ? conta.split(':')[1] : null, noCartao = conta.indexOf('cartao:') === 0;
        // Pago em: na edição mantém a data em que foi pago; compra no cartão só
        // é paga pela fatura (na edição o campo nem vai, para não desfazer).
        var pago = !noCartao && form.querySelector('input[name="situacao"]:checked').value === 'pago';
        var pagoEm = pago ? (estado.pagoEm || data) : null;
        var repete = el('lm-repete').value, extra = {};
        if (!estado.id && repete === 'parcelas') extra = { parcelas: parseInt(el('lm-parcelas').value, 10) || 2 };
        if (!estado.id && repete === 'mensal') extra = { parcelas: 0, recorrente: true, frequencia: 'mensal' };
        if (estado.id && estado.serie) extra.escopo = el('lm-escopo').value;
        var forma = noCartao ? 'credito' : el('lm-forma').value;
        if (t === 'saida') {
            var saida = { valor: valor, data_compra: data, categoria_id: el('lm-categoria').value || null, forma_pagamento: contaId, tipo_pagamento: forma, observacoes: desc };
            if (!(noCartao && estado.id)) saida.data_pagamento = pagoEm;
            return Object.assign(saida, extra);
        }
        return Object.assign({ valor: valor, data_prevista_recebimento: data, data_recebimento: pagoEm, categoria_id: el('lm-categoria').value || null, forma_recebimento: contaId, tipo_pagamento: forma, observacoes: desc }, extra);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault(); limparErros();
        var t = estado.tipo;
        if (!el('lm-valor').value.trim()) { modal.querySelector('[data-erro="valor"]').textContent = 'Informe o valor.'; el('lm-valor').focus(); return; }
        if (t !== 'transferencia' && !el('lm-conta').value) { modal.querySelector('[data-erro="conta"]').textContent = 'Escolha a conta.'; return; }
        var url = '{{ url('lancar') }}/' + t + (estado.id ? '/' + estado.id : '');
        enviar(estado.id ? 'PUT' : 'POST', url, corpo());
    });

    el('lm-excluir').addEventListener('click', function () {
        var escopo = estado.serie ? el('lm-escopo').value : 'apenas_esta';
        var msg = escopo === 'esta_e_futuras' ? 'Excluir este lançamento e os próximos da série?' : 'Excluir este lançamento?';
        if (!confirm(msg)) return;
        enviar('DELETE', '{{ url('lancar') }}/' + estado.tipo + '/' + estado.id + '?escopo=' + escopo);
    });
    el('lm-pagar').addEventListener('click', function () {
        enviar('POST', '{{ url('lancar') }}/' + estado.tipo + '/' + estado.id + '/pago', { data: hoje() });
    });

    modal.querySelectorAll('.lm-tipos button').forEach(function (b) { b.addEventListener('click', function () { mostrarTipo(b.dataset.tipo); }); });
    el('lm-conta').addEventListener('change', ajustar);
    el('lm-repete').addEventListener('change', ajustar);
    el('lm-data').addEventListener('change', ajustar);
    form.querySelectorAll('input[name="situacao"]').forEach(function (r) { r.addEventListener('change', function () { estado.situacaoTocada = true; }); });
    modal.querySelectorAll('[data-lm-fechar]').forEach(function (b) { b.addEventListener('click', fechar); });
    modal.addEventListener('click', function (e) { if (e.target === modal) fechar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('active')) fechar(); });

    document.addEventListener('click', function (e) {
        var abre = e.target.closest('[data-lancar]');
        if (abre) { e.preventDefault(); abrir(abre.dataset.lancar || 'saida'); return; }
        var ed = e.target.closest('[data-editar]');
        if (ed) { e.preventDefault(); var p = ed.dataset.editar.split(':'); editar(p[0], p[1]); }
    });
    window.Lancar = { abrir: abrir, editar: editar };
})();
</script>
