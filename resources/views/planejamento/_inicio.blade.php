{{-- Início: quanto tenho, quanto entrou, quanto saiu e para onde foi. Números do FinanceiroService. --}}
@php
    $semana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $quando = function (string $d) use ($semana) {
        $data = \Carbon\Carbon::parse($d);
        $dias = (int) now()->startOfDay()->diffInDays($data, false);
        return match (true) { $dias === 0 => 'Hoje', $dias === -1 => 'Ontem', $dias === 1 => 'Amanhã', default => $semana[$data->dayOfWeek] . ', ' . $data->format('d/m') };
    };
    $saldoCor = fn ($v) => $v < 0 ? 'var(--color-danger)' : 'var(--color-text)';
    $mes = $visao['mes'];
    $nomeMes = ucfirst(now()->locale('pt_BR')->isoFormat('MMMM'));
@endphp

<style>
.ini { max-width:880px; }
.ini-rotulo { font-size:12px; font-weight:600; color:var(--color-text-subtle); text-transform:uppercase; letter-spacing:.05em; }
.ini-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
.ini-mes { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.ini-linha { display:flex; align-items:center; gap:12px; padding:10px 0; border-top:1px solid var(--color-border); }
.ini-linha:first-of-type { border-top:none; }
.ini-icone { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:13px; }
.ini-texto { flex:1; min-width:0; }
.ini-texto div:first-child { font-weight:600; font-size:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ini-texto div:last-child { font-size:13px; color:var(--color-text-muted); }
.ini-titulo { display:flex; justify-content:space-between; align-items:baseline; margin:22px 0 8px; }
.ini-titulo h3 { font-size:18px; font-weight:600; margin:0; }
.ini-titulo a { font-size:13px; font-weight:600; color:var(--color-primary); text-decoration:none; }
@media (max-width:520px) { .ini-mes { grid-template-columns:1fr 1fr; } .ini-mes > div:last-child { grid-column:1 / -1; } }
</style>

<div class="ini">
    <div style="font-size:15px;color:var(--color-text-muted);margin-bottom:6px;">Olá, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}</div>

    {{-- Saldo total --}}
    <div class="card" style="padding:16px 18px;">
        <div class="ini-rotulo">Saldo total</div>
        <div class="ini-num" style="font-size:32px;font-weight:700;line-height:1.2;color:{{ $saldoCor($visao['contas']['total']) }};">{{ brl($visao['contas']['total']) }}</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px 16px;margin-top:6px;font-size:13px;color:var(--color-text-muted);">
            @foreach($visao['contas']['itens'] as $c)
                <span>{{ $c['nome'] }} <strong class="ini-num" style="color:{{ $saldoCor($c['saldo']) }};">{{ brl($c['saldo']) }}</strong></span>
            @endforeach
            <a href="{{ route('bancos.index') }}" style="color:var(--color-primary);text-decoration:none;font-weight:600;">Contas &rarr;</a>
        </div>
    </div>

    {{-- Este mês --}}
    <div class="ini-titulo"><h3>{{ $nomeMes }}</h3><a href="{{ route('planejamento.index') }}">Onde gastei &rarr;</a></div>
    @if($mes['tem_movimentacao'])
        <div class="ini-mes">
            <div class="card" style="padding:12px 14px;"><div class="ini-rotulo">Entrou</div><div class="ini-num" style="font-size:20px;font-weight:700;color:var(--color-success);">+{{ brl($mes['entrou']) }}</div></div>
            <div class="card" style="padding:12px 14px;"><div class="ini-rotulo">Saiu</div><div class="ini-num" style="font-size:20px;font-weight:700;color:var(--color-danger);">-{{ brl($mes['saiu']) }}</div></div>
            <div class="card" style="padding:12px 14px;"><div class="ini-rotulo">Resultado</div><div class="ini-num" style="font-size:20px;font-weight:700;color:{{ $saldoCor($mes['resultado']) }};">{{ brl($mes['resultado']) }}</div></div>
        </div>
        @if($mes['categorias'])
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:8px;">
                Mais gasto em:
                @foreach(array_slice($mes['categorias'], 0, 3) as $cat)
                    <strong style="color:var(--color-text);">{{ $cat['categoria'] }}</strong> {{ brl($cat['valor']) }}@if(! $loop->last) · @endif
                @endforeach
            </div>
        @endif
    @else
        <div class="card" style="padding:14px 16px;font-size:14px;color:var(--color-text-muted);">Nenhuma movimentação neste mês até agora.</div>
    @endif

    {{-- Últimas movimentações --}}
    <div class="ini-titulo"><h3>Últimas movimentações</h3><a href="{{ route('planejamento.lancamentos') }}">Ver extrato &rarr;</a></div>
    <div class="card" style="padding:4px 16px;">
        @forelse($visao['ultimas'] as $m)
            @php $entrada = $m['tipo'] === 'receita'; @endphp
            <div class="ini-linha">
                <div class="ini-icone" style="background:{{ $entrada ? 'var(--color-success-soft)' : 'var(--color-danger-soft)' }};color:{{ $entrada ? 'var(--color-success)' : 'var(--color-danger)' }};"><i class="fa-solid {{ $entrada ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i></div>
                <div class="ini-texto">
                    <div>{{ $m['descricao'] }}</div>
                    <div>{{ $quando($m['data']) }}{{ $m['conta'] ? ' · ' . $m['conta'] : '' }}</div>
                </div>
                <div class="ini-num" style="font-weight:700;color:{{ $entrada ? 'var(--color-success)' : 'var(--color-danger)' }};">{{ $entrada ? '+' : '-' }}{{ brl($m['valor']) }}</div>
            </div>
        @empty
            <p style="padding:12px 0;font-size:14px;color:var(--color-text-muted);">Nenhuma movimentação nos últimos 60 dias.</p>
        @endforelse
    </div>

    {{-- Próximos pagamentos --}}
    @if($visao['proximos'])
        <div class="ini-titulo"><h3>Próximos pagamentos</h3><a href="{{ route('alertas.index') }}">Tudo o que vence &rarr;</a></div>
        <div class="card" style="padding:4px 16px;">
            @foreach($visao['proximos'] as $p)
                <div class="ini-linha">
                    <div class="ini-icone" style="background:var(--color-warning-soft);color:var(--color-warning);"><i class="fa-regular fa-calendar"></i></div>
                    <div class="ini-texto">
                        <div>{{ $p['descricao'] }}</div>
                        <div>
                            @if($p['atrasado'])<span style="color:var(--color-danger);font-weight:600;">Atrasado</span> · @endif
                            {{ $p['data'] ? $quando($p['data']) : 'sem data' }}
                        </div>
                    </div>
                    <div class="ini-num" style="font-weight:700;">{{ brl($p['valor']) }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
