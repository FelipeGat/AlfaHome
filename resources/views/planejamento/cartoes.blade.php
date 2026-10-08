@extends('layouts.main')
@section('title', 'Cartões e parceladas')
@section('page-title', 'Planejamento — cartões e compras parceladas')

@section('content')
@php
    $brl = fn ($v) => brl($v);
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', '.') . '%';
    $rc = $cartoes['resumo'];
@endphp

<style>
.plan-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media (max-width:900px) { .plan-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:480px) { .plan-grid { grid-template-columns:1fr; } }
.plan-num { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
.cart-linha { cursor:pointer; transition:background .15s; }
.cart-linha:hover { background:var(--color-bg); }
.cart-linha.sel { background:var(--color-primary-soft); }
.cart-linha a { color:inherit; text-decoration:none; }
.cart-marca { display:inline-flex; align-items:center; gap:10px; }
.cart-logo { width:46px; height:25px; border-radius:6px; object-fit:contain; flex-shrink:0; }
.cart-letra { width:30px; height:30px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:700; flex-shrink:0; }
.fat { border:1px solid var(--color-border); border-radius:12px; margin-top:12px; overflow:hidden; }
.fat > summary { list-style:none; cursor:pointer; display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:12px 16px; }
.fat > summary::-webkit-details-marker { display:none; }
.fat > summary .seta { margin-left:auto; transition:transform .2s; color:var(--color-text-muted); }
.fat[open] > summary .seta { transform:rotate(180deg); }
.fat-mes { font-weight:600; min-width:120px; }
.fat-info { font-size:13px; color:var(--color-text-muted); }
.fat table { margin:0; }
.fat-pagar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; padding:10px 16px; background:var(--color-primary-soft); font-size:14px; }
.fat-pagar .form-control { width:auto; min-width:130px; font-size:14px; }
.fat-erro { color:var(--color-danger); font-size:13px; flex-basis:100%; }
.fat-erro:empty { display:none; }
.fat-editavel { cursor:pointer; }
.fat-editavel:hover { background:var(--color-bg); }
</style>

@include('planejamento._abas', ['ultima' => $ultima])

<div class="plan-grid">
    @include('planejamento._kpi', ['rotulo' => 'Limite disponível', 'valor' => $brl($rc['limite_disponivel']), 'cor' => 'var(--color-success)'])
    @include('planejamento._kpi', ['rotulo' => 'Limite utilizado', 'valor' => $pct($rc['utilizado_pct']), 'cor' => $rc['utilizado_pct'] >= 80 ? 'var(--color-danger)' : 'var(--color-text)', 'nota' => $brl($rc['limite_utilizado']) . ' de ' . $brl($rc['limite_total'])])
    @include('planejamento._kpi', ['rotulo' => 'Faturas em aberto', 'valor' => $brl($rc['faturas_abertas'])])
    @include('planejamento._kpi', ['rotulo' => 'Saldo das parceladas', 'valor' => $brl($parceladas['resumo']['saldo_total']), 'nota' => $brl($parceladas['resumo']['parcela_mensal_total']) . ' em parcelas por mês'])
</div>

<div class="card" style="padding:18px 20px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div class="card-title">Cartões</div>
        <span style="font-size:12.5px;color:var(--color-text-muted);">
            @if($selecionado)
                Mostrando {{ $selecionado->nome }} · <a href="{{ route('planejamento.cartoes') }}" style="color:var(--color-primary);font-weight:600;">ver todos</a>
            @else
                Clique num cartão para ver as faturas
            @endif
        </span>
    </div>
    @if($cartoes['itens']->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-credit-card"></i><p>Nenhum cartão cadastrado.</p><a class="btn btn-primary btn-sm" href="{{ route('bancos.index', ['nova' => 1]) }}" style="margin-top:8px;">Adicionar cartão</a></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cartão</th><th>Banco</th>
                        <th class="plan-num">Limite total</th><th class="plan-num">Utilizado</th><th class="plan-num">Disponível</th>
                        <th style="min-width:120px;">Uso</th>
                        <th class="plan-num">Fecha dia</th><th class="plan-num">Vence dia</th><th class="plan-num">Fatura atual</th><th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cartoes['itens'] as $c)
                        @php
                            $marca = $marcas[$c->chave] ?? ['logo' => null, 'cor' => null];
                            $sel = $selecionado && $selecionado->chave === $c->chave;
                            $url = $sel ? route('planejamento.cartoes') : route('planejamento.cartoes', ['cartao' => $c->chave]) . '#faturas';
                        @endphp
                        <tr class="cart-linha {{ $sel ? 'sel' : '' }}" onclick="location.href='{{ $url }}'">
                            <td>
                                <a href="{{ $url }}" class="cart-marca" aria-current="{{ $sel ? 'true' : 'false' }}" title="{{ $sel ? 'Ver todos os cartões' : 'Ver faturas deste cartão' }}">
                                    @if($marca['logo'])
                                        <img class="cart-logo" src="{{ asset($marca['logo']) }}" alt="">
                                    @else
                                        <span class="cart-letra" style="background:{{ $marca['cor'] ?: '#64748B' }};">{{ mb_strtoupper(mb_substr(\Illuminate\Support\Str::of($c->nome)->replaceFirst('Cartão ', ''), 0, 1)) }}</span>
                                    @endif
                                    <span><strong>{{ $c->nome }}</strong>@if($c->observacao)<div style="font-size:11px;color:var(--color-text-muted);font-weight:400;">{{ $c->observacao }}</div>@endif</span>
                                </a>
                            </td>
                            <td>{{ $c->banco ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($c->limite_total) }}</td>
                            <td class="plan-num">{{ $brl($c->limite_utilizado) }}</td>
                            <td class="plan-num">{{ $brl($c->limite_disponivel) }}</td>
                            <td>
                                @if($c->utilizado_pct !== null)
                                    <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ min($c->utilizado_pct, 100) }}%;background:{{ $c->utilizado_pct >= 80 ? 'var(--color-danger)' : 'var(--color-primary)' }};"></div></div>
                                    <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">{{ $pct($c->utilizado_pct) }}</div>
                                @else
                                    <span style="color:var(--color-text-faint);">—</span>
                                @endif
                            </td>
                            <td class="plan-num">{{ $c->dia_fechamento ?? '—' }}</td>
                            <td class="plan-num">{{ $c->dia_vencimento ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($c->fatura_atual) }}</td>
                            <td>@if($c->status_fatura)<span class="badge {{ mb_strtolower($c->status_fatura) === 'aberta' ? 'badge-warning' : 'badge-success' }}">{{ $c->status_fatura }}</span>@else<span style="color:var(--color-text-faint);">—</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if($selecionado)
<div class="card" id="faturas" style="padding:18px 20px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
        <div class="card-title">Faturas — {{ $selecionado->nome }}</div>
        <span style="font-size:12.5px;color:var(--color-text-muted);">{{ $selecionado->banco_id ? 'Compras lançadas' : 'Compras da planilha' }} neste cartão, por mês da fatura</span>
    </div>
    @forelse($faturas as $i => $f)
        @php $mesNome = ucfirst(\Carbon\Carbon::createFromFormat('!Y-m', $f['mes'])->locale('pt_BR')->isoFormat('MMMM [de] YYYY')); @endphp
        @php
            // Abre a próxima fatura a vencer; sem nenhuma futura, a mais recente.
            $abrirMes ??= collect($faturas)->filter(fn ($x) => $x['vencimento'] && $x['vencimento'] >= now()->format('Y-m-d'))->sortBy('vencimento')->first()['mes'] ?? ($faturas[0]['mes'] ?? null);
            $abrir = $f['mes'] === $abrirMes;
        @endphp
        <details class="fat" {{ $abrir ? 'open' : '' }}>
            <summary>
                <span class="fat-mes">{{ $mesNome }}</span>
                <span class="fat-info">{{ count($f['itens']) }} {{ count($f['itens']) === 1 ? 'compra' : 'compras' }}@if($f['vencimento']) · vence {{ \Carbon\Carbon::parse($f['vencimento'])->format('d/m') }}@endif</span>
                <strong class="plan-num">{{ $brl($f['total']) }}</strong>
                @if($f['paga'])
                    <span class="badge badge-success">Paga</span>
                @elseif($f['pago'] > 0)
                    <span class="badge badge-warning">Falta {{ $brl($f['pendente']) }}</span>
                @else
                    <span class="badge badge-warning">Aberta</span>
                @endif
                <i class="fa-solid fa-chevron-down seta"></i>
            </summary>
            @if($selecionado->banco_id && $f['pendente'] > 0)
                {{-- Pagar a fatura: as compras pendentes ficam pagas e o valor sai da conta escolhida. --}}
                <form class="fat-pagar" data-url="{{ route('lancar.fatura', $selecionado->banco_id) }}" data-vencimento="{{ $f['vencimento'] }}">
                    <span>Pagar <strong class="plan-num">{{ $brl($f['pendente']) }}</strong> com</span>
                    <label class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);" for="fat-conta-{{ $i }}">Conta que pagou</label>
                    <select id="fat-conta-{{ $i }}" name="conta_id" class="form-control" required>
                        @foreach($contasPagamento as $b)<option value="{{ $b->id }}" @selected($b->id === $selecionado->banco_id)>{{ $b->nome }}</option>@endforeach
                    </select>
                    <span>em</span>
                    <label class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);" for="fat-data-{{ $i }}">Data do pagamento</label>
                    <input id="fat-data-{{ $i }}" type="date" name="data" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Pagar fatura</button>
                    <span class="fat-erro" role="alert"></span>
                </form>
            @elseif($selecionado->banco_id && $f['paga'] && ($f['paga_com'] ?? null))
                <p style="font-size:13px;color:var(--color-text-muted);margin:8px 0 4px;">Paga com {{ $f['paga_com'] }}{{ $f['paga_em'] ? ' em ' . \Carbon\Carbon::parse($f['paga_em'])->format('d/m') : '' }}.</p>
            @endif
            <div class="table-wrapper">
                <table class="table">
                    <thead><tr><th>Data</th><th>Compra</th><th>Categoria</th><th class="plan-num">Valor</th><th>Situação</th></tr></thead>
                    <tbody>
                        @foreach($f['itens'] as $it)
                            <tr @isset($it['ref']) class="fat-editavel" data-editar="saida:{{ \Illuminate\Support\Str::after($it['ref'], ':') }}" title="Abrir para editar" @endisset>
                                <td style="white-space:nowrap;">{{ \Carbon\Carbon::parse($it['data'])->format('d/m') }}</td>
                                <td>{{ $it['descricao'] }}</td>
                                <td>{{ $it['categoria'] ?? '—' }}</td>
                                <td class="plan-num">{{ $brl($it['valor']) }}</td>
                                <td>@if($it['pago'])<span class="badge badge-success">Pago</span>@else<span class="badge badge-warning">Pendente</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @empty
        <p style="font-size:13px;color:var(--color-text-muted);margin-top:10px;">Nenhuma compra deste cartão entre {{ now()->subMonths(4)->locale('pt_BR')->isoFormat('MMMM') }} e {{ now()->addMonths(2)->locale('pt_BR')->isoFormat('MMMM') }}.</p>
    @endforelse
</div>
@endif

@unless($selecionado?->banco_id)
<div class="card" style="padding:18px 20px;">
    <div class="card-title" style="margin-bottom:12px;">Compras parceladas{{ $selecionado ? ' — ' . $selecionado->nome : '' }}</div>
    @if($parceladas['itens']->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-layer-group"></i><p>Nenhuma compra parcelada na planilha.</p></div>
    @else
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Compra</th><th>Cartão</th><th>Data</th>
                        <th class="plan-num">Valor total</th><th class="plan-num">Parcela</th>
                        <th style="min-width:130px;">Pagas</th>
                        <th class="plan-num">Restantes</th><th class="plan-num">Saldo</th><th>Próx. vencimento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($parceladas['itens'] as $p)
                        @php $andamento = $p->parcelas ? min((int) $p->parcelas_pagas / $p->parcelas * 100, 100) : 0; @endphp
                        <tr>
                            <td><strong>{{ $p->compra }}</strong>@if($p->observacao)<div style="font-size:11px;color:var(--color-text-muted);">{{ $p->observacao }}</div>@endif</td>
                            <td>
                                {{ $p->cartao ?? '—' }}
                                @if($p->cartao && ! $p->plan_cartao_id)
                                    <i class="fa-solid fa-circle-exclamation" style="color:var(--color-warning);" title="Este cartão não está na aba Cartões da planilha"></i>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">{{ $p->data?->format('d/m/Y') ?? '—' }}</td>
                            <td class="plan-num">{{ $brl($p->valor_total) }}</td>
                            <td class="plan-num">{{ $p->parcelas ? $p->parcelas . ' x ' . number_format($p->valor_parcela, 2, ',', '.') : '—' }}</td>
                            <td>
                                <div class="progress-bar"><div class="progress-bar-fill" style="width:{{ $andamento }}%;background:var(--color-primary);"></div></div>
                                <div style="font-size:11px;color:var(--color-text-muted);margin-top:2px;">{{ $p->parcelas_pagas ?? 0 }} de {{ $p->parcelas ?? '—' }}</div>
                            </td>
                            <td class="plan-num">{{ $p->parcelas_restantes ?? '—' }}</td>
                            <td class="plan-num"><strong>{{ $brl($p->saldo) }}</strong></td>
                            <td style="white-space:nowrap;">{{ $p->proximo_vencimento?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endunless
@endsection

@push('scripts')
<script>
document.querySelectorAll('.fat-pagar').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        e.preventDefault();
        var botao = f.querySelector('button'), erro = f.querySelector('.fat-erro');
        botao.disabled = true; erro.textContent = '';
        fetch(f.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ vencimento: f.dataset.vencimento, conta_id: f.conta_id.value, data: f.data.value }),
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (r.ok) { location.reload(); return; }
                botao.disabled = false;
                erro.textContent = (j.errors && Object.values(j.errors)[0][0]) || j.message || 'Não foi possível pagar. Tente de novo.';
            });
        }).catch(function () { botao.disabled = false; erro.textContent = 'Sem conexão. Tente de novo.'; });
    });
});
</script>
@endpush
