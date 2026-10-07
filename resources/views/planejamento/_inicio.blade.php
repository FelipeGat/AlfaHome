{{-- Início: quanto tenho, como foi o mês, onde gastei, o que aconteceu e o que pagar.
     Todos os números vêm do FinanceiroService (os mesmos do app e dos avisos). --}}
@php
    $semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $hojeDia = now()->startOfDay();
    $dias = fn (?string $d) => $d ? (int) $hojeDia->diffInDays(\Carbon\Carbon::parse($d), false) : null;
    $quando = function (?string $d) use ($semana, $dias) {
        if (! $d) return 'sem data';
        $n = $dias($d); $data = \Carbon\Carbon::parse($d);
        return match (true) { $n === 0 => 'Hoje', $n === -1 => 'Ontem', $n === 1 => 'Amanhã', default => $semana[$data->dayOfWeek] . ', ' . $data->format('d/m') };
    };
    $hora = (int) now()->format('G');
    $saudacao = $hora < 5 ? 'Boa noite' : ($hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite'));
    $primeiroNome = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
    $mes = $visao['mes'];
    $nomeMes = fn ($c) => \Carbon\Carbon::parse($c)->locale('pt_BR')->isoFormat('MMMM');
    $mesAtual = $mesVisao->isSameMonth(now());
    $negativo = fn ($v) => $v < 0;

    // Donut: as 4 maiores categorias + "Outras", em pontos percentuais do que saiu.
    $cores = ['#2563EB', '#14B8A6', '#F59E0B', '#8B5CF6', '#EC4899', '#0EA5E9', '#94A3B8'];
    $cats = array_slice($mes['categorias'], 0, 6);
    $resto = array_slice($mes['categorias'], 6);
    $maiorPct = max(array_column($mes['categorias'], 'pct') ?: [1]);
    if ($resto) {
        $cats[] = ['categoria' => 'Outras', 'qtd' => count($resto), 'valor' => round(array_sum(array_column($resto, 'valor')), 2), 'pct' => round(array_sum(array_column($resto, 'pct')), 2)];
    }
    $acumulado = 0;
@endphp

<style>
.ini { max-width:1440px; margin:0 auto; }
.ini-cab { display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:14px; margin-bottom:20px; }
.ini-cab h1 { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:28px; font-weight:800; letter-spacing:-.02em; color:var(--color-text); margin:0; line-height:1.15; }
.ini-cab p { color:var(--color-text-muted); font-size:15px; margin-top:4px; }
.ini-acoes { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.ini-mes-sel { height:42px; border-radius:10px; border:1px solid var(--color-border); background:var(--color-bg-card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3E%3Cpath d='M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4z'/%3E%3C/svg%3E") no-repeat right 12px center / 16px; -webkit-appearance:none; appearance:none; color:var(--color-text); padding:0 38px 0 14px; font-weight:600; font-size:14px; font-family:inherit; cursor:pointer; }
.ini-mes-sel:focus-visible, .ini-olho:focus-visible, .ini-link:focus-visible { outline:2px solid var(--color-primary); outline-offset:2px; }

.ini-grade { display:grid; gap:16px; grid-template-columns:minmax(0,1fr) 340px; align-items:start; }
.ini-principal, .ini-lado { display:flex; flex-direction:column; gap:16px; min-width:0; }
.ini-duo { display:grid; gap:16px; grid-template-columns:minmax(0,1fr) minmax(0,1fr); align-items:start; }

.ini-card { background:var(--color-bg-card); border:1px solid var(--color-border); border-radius:14px; box-shadow:var(--shadow-card); padding:18px 20px; }
.ini-card h2 { font-size:16px; font-weight:700; color:var(--color-text); margin:0; }
.ini-card-cab { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; }
.ini-link { font-size:13px; font-weight:600; color:var(--color-primary); text-decoration:none; white-space:nowrap; border-radius:6px; }
.ini-link:hover { text-decoration:underline; }
.ini-rotulo { font-size:13px; font-weight:500; color:var(--color-text-muted); }
.ini-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
.ini-vazio { font-size:14px; color:var(--color-text-muted); padding:10px 0; }

.ini-topo { display:grid; gap:16px; grid-template-columns:repeat(3,minmax(0,1fr)); }
.ini-topo .a-saldo { grid-column:1 / -1; }
.ini-saldo-valor { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:34px; font-weight:800; letter-spacing:-.02em; line-height:1.15; margin:6px 0 14px; }
.ini-olho { width:32px; height:32px; border-radius:8px; border:0; background:transparent; color:var(--color-text-muted); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:background .15s; }
.ini-olho:hover { background:var(--color-bg); }
.ini-contas { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); border-top:1px solid var(--color-border); margin:0 -20px -18px; }
.ini-conta { padding:12px 20px; display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit; transition:background .15s; min-width:0; }
.ini-conta { box-shadow:-1px 0 0 var(--color-border), 0 -1px 0 var(--color-border); }
.ini-conta:hover { background:var(--color-bg); }
.ini-conta-ic { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:700; flex-shrink:0; }
.ini-conta small { display:block; font-size:12px; color:var(--color-text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ini-conta strong { font-size:14px; }

.ini-kpi .ini-ic { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; }
.ini-kpi { min-width:0; }
.ini-kpi .ini-valor-kpi { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:clamp(17px,1.5vw,22px); font-weight:800; letter-spacing:-.01em; margin-top:2px; }

.ini-linha { display:flex; align-items:center; gap:12px; padding:11px 0; border-top:1px solid var(--color-border); }
.ini-linha:first-child { border-top:0; }
.ini-linha-ic { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.ini-linha-txt { flex:1; min-width:0; }
.ini-linha-txt div:first-child { font-weight:600; font-size:15px; color:var(--color-text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.a-pagar .ini-linha-txt div:first-child { white-space:normal; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; font-size:14px; line-height:1.3; }
.ini-linha-txt div:last-child { font-size:13px; color:var(--color-text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ini-badge { display:inline-block; font-size:11.5px; font-weight:600; padding:1px 7px; border-radius:6px; margin-right:4px; }

.ini-duo { align-items:stretch; }
.ini-donut-graf { position:relative; width:220px; height:220px; margin:6px auto 18px; }
.ini-donut-graf svg { width:100%; height:100%; transform:rotate(-90deg); }
.ini-donut-graf circle.fatia { transition:stroke-width .15s; }
.ini-donut-centro { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; pointer-events:none; }
.ini-donut-centro small { font-size:12px; color:var(--color-text-muted); }
.ini-donut-centro strong { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-weight:800; letter-spacing:-.02em; color:var(--color-text); line-height:1.15; }
.ini-cats { list-style:none; margin:0; padding:0; }
.ini-cats li { padding:8px 0; border-top:1px solid var(--color-border); }
.ini-cats li:first-child { border-top:0; }
.ini-cat-linha { display:grid; grid-template-columns:10px minmax(0,1fr) auto auto; gap:10px; align-items:center; font-size:14px; }
.ini-cat-linha i { width:10px; height:10px; border-radius:3px; }
.ini-cat-linha span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--color-text); }
.ini-cat-linha .pct { color:var(--color-text-muted); font-size:12.5px; font-variant-numeric:tabular-nums; min-width:44px; text-align:right; }
.ini-cat-linha strong { font-size:14px; font-weight:700; color:var(--color-text); min-width:92px; text-align:right; }
.ini-cat-barra { height:5px; border-radius:5px; background:var(--color-bg); margin:6px 0 0 20px; overflow:hidden; }
.ini-cat-barra b { display:block; height:100%; border-radius:5px; }

.ini-cart-total { display:flex; justify-content:space-between; gap:10px; margin-top:4px; }
.ini-cart-total small { display:block; font-size:12px; color:var(--color-text-muted); }
.ini-cart-total strong { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:19px; font-weight:800; color:var(--color-text); }
.ini-uso { height:8px; border-radius:8px; background:var(--color-bg); overflow:hidden; margin-top:8px; }
.ini-uso.fino { height:5px; margin-top:6px; }
.ini-uso b { display:block; height:100%; border-radius:8px; transition:width .3s; }
.ini-cart { padding:12px 0 2px; border-top:1px solid var(--color-border); margin-top:12px; }
.ini-cart + .ini-cart { margin-top:10px; }
.ini-cart-linha { display:flex; justify-content:space-between; gap:8px; font-size:14px; }
.ini-cart-linha strong { font-weight:600; color:var(--color-text); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ini-cart-det { display:flex; justify-content:space-between; gap:8px; flex-wrap:wrap; font-size:12.5px; color:var(--color-text-muted); margin-top:5px; }
.ini-cart-det b { color:var(--color-text); font-weight:600; }
.ini-atalho { display:flex; align-items:center; gap:12px; padding:12px; border-radius:10px; text-decoration:none; color:inherit; transition:background .15s; }
.ini-atalho:hover { background:var(--color-bg); }
.ini-atalho .ini-ic { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ini-atalho div { flex:1; min-width:0; }
.ini-atalho strong { display:block; font-size:14px; font-weight:600; color:var(--color-text); }
.ini-atalho small { font-size:12.5px; color:var(--color-text-muted); }

/* Ocultar valores (só nesta tela, guardado neste navegador) */
.ini.oculto .valor { filter:blur(9px); user-select:none; }

@media (max-width:1279px) {
    .ini-principal, .ini-duo, .ini-lado { display:contents; }
    .a-topo { grid-area:topo; } .a-mov { grid-area:mov; } .a-gasto { grid-area:gasto; } .a-pagar { grid-area:pagar; } .a-cart { grid-area:cart; } .a-resumo { grid-area:resumo; }
    .ini-grade { grid-template-columns:minmax(0,1fr) minmax(0,1fr); grid-template-areas: "topo topo" "cart gasto" "pagar mov" "resumo mov"; }
}
@media (max-width:767px) {
    .ini-grade { grid-template-columns:minmax(0,1fr); grid-template-areas: "topo" "mov" "pagar" "cart" "gasto" "resumo"; }
    .ini-cab h1 { font-size:23px; }
    .ini-cab { align-items:flex-start; }
    .ini-acoes { width:100%; }
    .ini-mes-sel { flex:1; }
    .ini-saldo-valor { font-size:30px; }
    .ini-topo { gap:10px; }
    .ini-kpi { padding:12px 12px; }
    .ini-kpi .ini-ic { width:28px; height:28px; margin-bottom:8px; font-size:12px; }
    .ini-kpi .ini-valor-kpi { font-size:15px; }
    .ini-kpi .ini-rotulo { font-size:12px; }
    .ini-contas { grid-template-columns:1fr 1fr; }
}
@media (max-width:400px) { .ini-kpi .ini-valor-kpi { font-size:13.5px; letter-spacing:-.02em; } .ini-kpi { padding:10px; } }
@media (prefers-reduced-motion: reduce) { .ini * { transition:none !important; } }
</style>

<div class="ini" id="inicio">
    <header class="ini-cab">
        <div>
            <h1>{{ $saudacao }}, {{ $primeiroNome }}</h1>
            <p>Aqui está o resumo das suas finanças em {{ $nomeMes($mesVisao) }}.</p>
        </div>
        <div class="ini-acoes">
            <form method="GET" action="{{ route('dashboard') }}">
                <label for="ini-mes" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">Mês do resumo</label>
                <select id="ini-mes" name="mes" class="ini-mes-sel" onchange="this.form.submit()">
                    @foreach($mesesVisao as $m)
                        <option value="{{ $m->format('Y-m') }}" @selected($m->isSameMonth($mesVisao))>{{ ucfirst($m->locale('pt_BR')->isoFormat('MMMM YYYY')) }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </header>

    <div class="ini-grade">
        <div class="ini-principal">
        {{-- Saldo + mês --}}
        <section class="a-topo ini-topo" aria-label="Saldo e resumo do mês">
            <div class="ini-card a-saldo">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="ini-rotulo">Saldo total</span>
                    <button type="button" class="ini-olho" id="ini-olho" aria-pressed="false" aria-label="Ocultar valores" title="Ocultar valores">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
                <div class="ini-saldo-valor ini-num valor" style="color:{{ $negativo($visao['contas']['total']) ? 'var(--color-danger)' : 'var(--color-text)' }};">{{ brl($visao['contas']['total']) }}</div>
                @if($visao['contas']['itens'])
                    <div class="ini-contas">
                        @foreach($visao['contas']['itens'] as $c)
                            <a href="{{ route('bancos.index') }}" class="ini-conta" title="{{ $c['nome'] }}">
                                <span class="ini-conta-ic" style="background:{{ $c['cor'] ?: '#64748B' }};">{{ mb_strtoupper(mb_substr($c['nome'], 0, 1)) }}</span>
                                <span style="min-width:0;">
                                    <small>{{ $c['nome'] }}</small>
                                    <strong class="ini-num valor" style="color:{{ $negativo($c['saldo']) ? 'var(--color-danger)' : 'var(--color-text)' }};">{{ brl($c['saldo']) }}</strong>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="ini-vazio">Nenhuma conta cadastrada. <a class="ini-link" href="{{ route('bancos.index') }}">Adicionar conta</a></p>
                @endif
            </div>

            <div class="ini-card ini-kpi">
                <div class="ini-ic" style="background:var(--color-success-soft);color:var(--color-success);"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="ini-rotulo">Entradas</div>
                <div class="ini-valor-kpi ini-num valor" style="color:var(--color-success);">{{ $mes['entrou'] > 0 ? '+' : '' }}{{ brl($mes['entrou']) }}</div>
            </div>
            <div class="ini-card ini-kpi">
                <div class="ini-ic" style="background:var(--color-danger-soft);color:var(--color-danger);"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="ini-rotulo">Saídas</div>
                <div class="ini-valor-kpi ini-num valor" style="color:var(--color-danger);">{{ $mes['saiu'] > 0 ? '-' : '' }}{{ brl($mes['saiu']) }}</div>
            </div>
            <div class="ini-card ini-kpi">
                <div class="ini-ic" style="background:var(--color-primary-soft);color:var(--color-primary);"><i class="fa-solid fa-scale-balanced"></i></div>
                <div class="ini-rotulo">Resultado do mês</div>
                <div class="ini-valor-kpi ini-num valor" style="color:{{ $negativo($mes['resultado']) ? 'var(--color-danger)' : 'var(--color-text)' }};">{{ brl($mes['resultado']) }}</div>
            </div>
        </section>

        <div class="ini-duo">
        {{-- Cartões: limite usado e livre de cada um (limite nunca entra no saldo) --}}
        @if($cartoesInfo['quantidade'])
        @php $corUso = fn ($p) => $p === null ? 'var(--color-text-muted)' : ($p > 80 ? 'var(--color-danger)' : ($p > 60 ? 'var(--color-warning)' : 'var(--color-primary)')); @endphp
        <section class="ini-card a-cart" aria-labelledby="t-cart">
            <div class="ini-card-cab"><h2 id="t-cart">Cartões <span style="font-weight:500;color:var(--color-text-muted);font-size:14px;">({{ $cartoesInfo['quantidade'] }})</span></h2><a class="ini-link" href="{{ route('planejamento.cartoes') }}">Ver cartões →</a></div>
            <div class="ini-cart-total">
                <div><small>Livre</small><strong class="ini-num valor">{{ brl($cartoesInfo['limite_disponivel']) }}</strong></div>
                <div style="text-align:right;"><small>Usado</small><strong class="ini-num valor" style="color:{{ $corUso($cartoesInfo['utilizado_pct']) }};">{{ brl($cartoesInfo['limite_utilizado']) }}</strong></div>
            </div>
            <div class="ini-uso" role="img" aria-label="{{ number_format($cartoesInfo['utilizado_pct'], 0, ',', '.') }}% do limite total usado"><b style="width:{{ min(100, $cartoesInfo['utilizado_pct']) }}%;background:{{ $corUso($cartoesInfo['utilizado_pct']) }};"></b></div>
            <small style="display:block;color:var(--color-text-muted);font-size:12px;margin-top:4px;">{{ number_format($cartoesInfo['utilizado_pct'], 0, ',', '.') }}% de <span class="valor">{{ brl($cartoesInfo['limite_total']) }}</span> de limite · não entra no saldo</small>

            @foreach($cartoesInfo['itens'] as $c)
                <div class="ini-cart">
                    <div class="ini-cart-linha">
                        <strong title="{{ $c['nome'] }}">{{ \Illuminate\Support\Str::of($c['nome'])->replaceFirst('Cartão ', '') }}</strong>
                        <span class="ini-num valor" style="color:{{ $corUso($c['pct']) }};font-weight:700;">{{ $c['pct'] !== null ? number_format($c['pct'], 0, ',', '.') . '%' : '—' }}</span>
                    </div>
                    @if($c['pct'] !== null)
                        <div class="ini-uso fino"><b style="width:{{ min(100, $c['pct']) }}%;background:{{ $corUso($c['pct']) }};"></b></div>
                    @endif
                    <div class="ini-cart-det">
                        <span>Usado <b class="valor">{{ brl($c['utilizado']) }}</b></span>
                        <span>Livre <b class="valor">{{ brl($c['disponivel']) }}</b></span>
                    </div>
                    @if($c['fatura'])
                        <div class="ini-cart-det">
                            <span>Fatura <b class="valor">{{ brl($c['fatura']) }}</b>{{ $c['vencimento'] ? ' · vence ' . $quando($c['vencimento']) : '' }}</span>
                            @if($c['fatura_paga'])<span class="ini-badge" style="background:var(--color-success-soft);color:var(--color-success);margin:0;">Paga</span>@endif
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
        @endif

        {{-- Onde você gastou --}}
        <section class="ini-card a-gasto" aria-labelledby="t-gasto">
            <div class="ini-card-cab"><h2 id="t-gasto">Onde você gastou</h2><a class="ini-link" href="{{ route('planejamento.index', ['mes' => $mesVisao->format('Y-m')]) }}">Ver detalhes →</a></div>
            @if($cats)
                @php $textoCentro = brl($mes['saiu']); @endphp
                <div class="ini-donut-graf">
                    <svg viewBox="0 0 42 42" role="img" aria-label="Saídas de {{ $nomeMes($mesVisao) }} por categoria: {{ collect($cats)->map(fn ($c) => $c['categoria'] . ' ' . number_format($c['pct'], 1, ',', '.') . '%')->join(', ') }}">
                        <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--color-bg)" stroke-width="4.6"/>
                        @foreach($cats as $i => $c)
                            @php $fatia = max(0, $c['pct'] - ($c['pct'] > 1.5 ? 0.8 : 0)); @endphp
                            <circle class="fatia" cx="21" cy="21" r="15.915" fill="none" stroke="{{ $cores[$i] }}" stroke-width="4.6" stroke-linecap="butt"
                                stroke-dasharray="{{ $fatia }} {{ 100 - $fatia }}" stroke-dashoffset="{{ -$acumulado }}"><title>{{ $c['categoria'] }}: {{ brl($c['valor']) }}</title></circle>
                            @php $acumulado += $c['pct']; @endphp
                        @endforeach
                    </svg>
                    <div class="ini-donut-centro">
                        <small>Saiu em {{ $nomeMes($mesVisao) }}</small>
                        <strong class="valor" style="font-size:{{ mb_strlen($textoCentro) > 12 ? 18 : 21 }}px;">{{ $textoCentro }}</strong>
                        <small>{{ count($mes['categorias']) }} {{ count($mes['categorias']) === 1 ? 'categoria' : 'categorias' }}</small>
                    </div>
                </div>
                <ul class="ini-cats">
                    @foreach($cats as $i => $c)
                        <li>
                            <div class="ini-cat-linha">
                                <i style="background:{{ $cores[$i] }};"></i>
                                <span title="{{ $c['categoria'] }}">{{ $c['categoria'] }}@isset($c['qtd']) <small style="color:var(--color-text-muted);">({{ $c['qtd'] }})</small>@endisset</span>
                                <span class="pct">{{ number_format($c['pct'], 1, ',', '.') }}%</span>
                                <strong class="ini-num valor">{{ brl($c['valor']) }}</strong>
                            </div>
                            <div class="ini-cat-barra"><b style="width:{{ min(100, round($c['pct'] / $maiorPct * 100, 1)) }}%;background:{{ $cores[$i] }};"></b></div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="ini-vazio">Nenhuma saída paga em {{ $nomeMes($mesVisao) }}.</p>
            @endif
        </section>

        </div>
        </div>

        <aside class="ini-lado">
        {{-- Próximos pagamentos --}}
        <section class="ini-card a-pagar" aria-labelledby="t-pagar">
            <div class="ini-card-cab"><h2 id="t-pagar">Próximos pagamentos</h2><a class="ini-link" href="{{ route('alertas.index') }}">Ver todos →</a></div>
            @forelse($visao['proximos'] as $p)
                @php $n = $dias($p['data']); $pago = $p['pago'] ?? false; @endphp
                <div class="ini-linha">
                    @if($pago)
                        <div class="ini-linha-ic" style="background:var(--color-success-soft);color:var(--color-success);border-radius:9px;" aria-hidden="true"><i class="fa-solid fa-check"></i></div>
                    @else
                        <div class="ini-linha-ic" style="background:var(--color-warning-soft);color:var(--color-warning);border-radius:9px;" aria-hidden="true"><i class="fa-regular fa-calendar"></i></div>
                    @endif
                    <div class="ini-linha-txt">
                        <div title="{{ $p['descricao'] }}">{{ $p['descricao'] }}</div>
                        <div>
                            @if($pago)
                                <span class="ini-badge" style="background:var(--color-success-soft);color:var(--color-success);">Pago</span>
                            @elseif($p['atrasado'])
                                <span class="ini-badge" style="background:var(--color-danger-soft);color:var(--color-danger);">Atrasado</span>
                            @elseif($n === 0)
                                <span class="ini-badge" style="background:var(--color-warning-soft);color:var(--color-warning);">Vence hoje</span>
                            @endif
                            {{ $quando($p['data']) }}
                        </div>
                    </div>
                    <div class="ini-num valor" style="font-weight:700;color:{{ $pago ? 'var(--color-success)' : 'var(--color-text)' }};">{{ brl($p['valor']) }}</div>
                </div>
            @empty
                <p class="ini-vazio">Nada para pagar nos próximos dias.</p>
            @endforelse
        </section>

        {{-- Últimas movimentações --}}
        <section class="ini-card a-mov" aria-labelledby="t-mov">
            <div class="ini-card-cab"><h2 id="t-mov">Últimas movimentações</h2><a class="ini-link" href="{{ route('planejamento.lancamentos') }}">Ver extrato →</a></div>
            @forelse($visao['ultimas'] as $m)
                @php $entrada = $m['tipo'] === 'receita'; @endphp
                <div class="ini-linha">
                    <div class="ini-linha-ic" style="background:{{ $entrada ? 'var(--color-success-soft)' : 'var(--color-danger-soft)' }};color:{{ $entrada ? 'var(--color-success)' : 'var(--color-danger)' }};" aria-hidden="true">
                        <i class="fa-solid {{ $entrada ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                    </div>
                    <div class="ini-linha-txt">
                        <div>{{ $m['descricao'] }}</div>
                        <div>{{ $quando($m['data']) }}{{ $m['conta'] ? ' · ' . $m['conta'] : '' }}</div>
                    </div>
                    <div class="ini-num valor" style="font-weight:700;color:{{ $entrada ? 'var(--color-success)' : 'var(--color-danger)' }};">
                        <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);">{{ $entrada ? 'Entrada de' : 'Saída de' }}</span>{{ $entrada ? '+' : '-' }}{{ brl($m['valor']) }}
                    </div>
                </div>
            @empty
                <p class="ini-vazio">Nenhuma movimentação nos últimos 60 dias.</p>
            @endforelse
        </section>

        {{-- Resumo rápido: só o que tem número real --}}
        <section class="ini-card a-resumo" aria-labelledby="t-resumo" style="padding:14px 10px;">
            <h2 id="t-resumo" style="padding:0 10px 6px;">Resumo rápido</h2>
            <a class="ini-atalho" href="{{ route('alertas.index') }}">
                <span class="ini-ic" style="background:{{ $vencidas['quantidade'] ? 'var(--color-danger-soft)' : 'var(--color-success-soft)' }};color:{{ $vencidas['quantidade'] ? 'var(--color-danger)' : 'var(--color-success)' }};"><i class="fa-regular fa-calendar-xmark"></i></span>
                <div>
                    @if($vencidas['quantidade'])
                        <strong>{{ $vencidas['quantidade'] }} {{ $vencidas['quantidade'] === 1 ? 'conta vencida' : 'contas vencidas' }}</strong>
                        <small>Total de <span class="valor">{{ brl($vencidas['total']) }}</span></small>
                    @else
                        <strong>Nenhuma conta vencida</strong><small>Tudo em dia</small>
                    @endif
                </div>
                <i class="fa-solid fa-chevron-right" style="color:var(--color-text-muted);font-size:12px;"></i>
            </a>
        </section>
        </aside>
    </div>
</div>

<script>
(function () {
    var raiz = document.getElementById('inicio'), btn = document.getElementById('ini-olho');
    if (!raiz || !btn) return;
    function aplicar(oculto) {
        raiz.classList.toggle('oculto', oculto);
        btn.setAttribute('aria-pressed', oculto ? 'true' : 'false');
        btn.setAttribute('aria-label', oculto ? 'Mostrar valores' : 'Ocultar valores');
        btn.title = btn.getAttribute('aria-label');
        btn.innerHTML = oculto ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
    }
    var salvo = false;
    try { salvo = localStorage.getItem('alfahome-ocultar-valores') === '1'; } catch (e) {}
    aplicar(salvo);
    btn.addEventListener('click', function () {
        var novo = !raiz.classList.contains('oculto');
        aplicar(novo);
        try { localStorage.setItem('alfahome-ocultar-valores', novo ? '1' : '0'); } catch (e) {}
    });
})();
</script>
