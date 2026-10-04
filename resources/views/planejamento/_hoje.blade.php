{{-- Painel do dia: quanto tem, o que venceu, o que vence na semana, como o mês fecha --}}
@php
    $hBrl = fn ($v) => $v === null ? '—' : 'R$ ' . number_format($v, 2, ',', '.');
    $hCor = fn ($v) => $v < 0 ? 'var(--color-danger)' : 'var(--color-success)';
    $hData = fn ($d) => \Carbon\Carbon::parse($d)->locale('pt_BR')->isoFormat('ddd, DD/MM');
    $hDias = function ($d) { $n = (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($d), false); return $n === 0 ? 'hoje' : ($n === 1 ? 'amanhã' : ($n < 0 ? abs($n) . ' dias atrás' : "em {$n} dias")); };
    $proximos = collect($hoje['proximos_7_dias']['a_pagar'])->map(fn ($i) => $i + ['sinal' => -1])
        ->concat(collect($hoje['proximos_7_dias']['a_receber'])->map(fn ($i) => $i + ['sinal' => 1]))
        ->sortBy('data')->values();
@endphp

<style>
.hoje-topo { display:grid; grid-template-columns:1.2fr 1fr 1fr; gap:14px; margin-bottom:14px; }
.hoje-meio { display:grid; grid-template-columns:1.4fr 1fr; gap:14px; margin-bottom:20px; }
@media (max-width:1000px) { .hoje-topo, .hoje-meio { grid-template-columns:1fr; } }
.hoje-rotulo { font-size:11px; font-weight:600; color:var(--color-text-subtle); text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
.hoje-item { display:flex; justify-content:space-between; gap:10px; padding:9px 0; border-top:1px solid var(--color-border); font-size:13px; }
.hoje-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
</style>

<div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
    <h2 style="font-size:20px;font-weight:700;margin:0;">Hoje <span style="font-weight:400;color:var(--color-text-muted);font-size:14px;">· {{ ucfirst(now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM')) }}</span></h2>
    <a href="{{ route('alertas.index') }}" style="font-size:12px;font-weight:600;color:var(--color-primary);text-decoration:none;">Tudo o que vence &rarr;</a>
</div>

<div class="hoje-topo">
    {{-- Quanto tenho --}}
    <div class="card" style="padding:16px 18px;">
        <div class="hoje-rotulo">Saldo em contas</div>
        <div class="hoje-num" style="font-size:28px;font-weight:700;color:{{ $hCor($hoje['contas']['total']) }};">{{ $hBrl($hoje['contas']['total']) }}</div>
        @forelse($hoje['contas']['itens'] as $c)
            <div class="hoje-item" style="padding:6px 0;">
                <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $c['cor'] ?? 'var(--color-border-strong)' }};margin-right:6px;"></span>{{ $c['nome'] }}</span>
                <span class="hoje-num" style="color:{{ $c['saldo'] < 0 ? 'var(--color-danger)' : 'var(--color-text)' }};">{{ $hBrl($c['saldo']) }}</span>
            </div>
        @empty
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:6px;"><a href="{{ route('bancos.index') }}">Cadastre suas contas</a> para ver o saldo aqui.</div>
        @endforelse
    </div>

    {{-- Como o mês fecha --}}
    <div class="card" style="padding:16px 18px;">
        <div class="hoje-rotulo">Fim do mês (previsão)</div>
        <div class="hoje-num" style="font-size:28px;font-weight:700;color:{{ $hCor($hoje['ate_fim_do_mes']['projecao_saldo']) }};">{{ $hBrl($hoje['ate_fim_do_mes']['projecao_saldo']) }}</div>
        <div style="font-size:12px;color:var(--color-text-muted);margin-bottom:8px;">saldo em contas + a receber − a pagar até {{ now()->endOfMonth()->format('d/m') }}</div>
        <div class="hoje-item"><span>A receber</span><span class="hoje-num" style="color:var(--color-success);font-weight:600;">+ {{ $hBrl($hoje['ate_fim_do_mes']['a_receber']) }}</span></div>
        <div class="hoje-item"><span>A pagar</span><span class="hoje-num" style="color:var(--color-danger);font-weight:600;">− {{ $hBrl($hoje['ate_fim_do_mes']['a_pagar']) }}</span></div>
    </div>

    {{-- Mês e cartões --}}
    <div class="card" style="padding:16px 18px;">
        <div class="hoje-rotulo">{{ ucfirst(now()->locale('pt_BR')->isoFormat('MMMM')) }} até agora</div>
        <div class="hoje-item" style="border-top:0;"><span>Recebido</span><span class="hoje-num" style="color:var(--color-success);font-weight:600;">{{ $hBrl($hoje['mes']['receitas']) }}</span></div>
        <div class="hoje-item"><span>Gasto</span><span class="hoje-num" style="color:var(--color-danger);font-weight:600;">{{ $hBrl($hoje['mes']['despesas']) }}</span></div>
        <div class="hoje-item"><span>Limite livre nos cartões</span><span class="hoje-num" style="font-weight:600;">{{ $hBrl($hoje['cartoes']['limite_disponivel']) }}</span></div>
        @if($hoje['cartoes']['proxima_fatura'])
            <div class="hoje-item"><span>Próxima fatura <span style="color:var(--color-text-muted);">({{ \Carbon\Carbon::parse($hoje['cartoes']['proxima_fatura']['data'])->format('d/m') }})</span></span><span class="hoje-num" style="font-weight:600;">{{ $hBrl($hoje['cartoes']['proxima_fatura']['valor']) }}</span></div>
        @endif
    </div>
</div>

<div class="hoje-meio">
    {{-- Próximos 7 dias --}}
    <div class="card" style="padding:16px 18px;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;">
            <div class="hoje-rotulo">Próximos 7 dias</div>
            <div style="font-size:12px;color:var(--color-text-muted);">
                <span style="color:var(--color-danger);font-weight:600;">− {{ $hBrl($hoje['proximos_7_dias']['total_pagar']) }}</span>
                @if($hoje['proximos_7_dias']['total_receber'] > 0) · <span style="color:var(--color-success);font-weight:600;">+ {{ $hBrl($hoje['proximos_7_dias']['total_receber']) }}</span>@endif
            </div>
        </div>
        @forelse($proximos as $i)
            <div class="hoje-item">
                <div style="min-width:0;">
                    <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $i['descricao'] }}</div>
                    <div style="font-size:11px;color:var(--color-text-muted);">{{ $hData($i['data']) }} · {{ $hDias($i['data']) }}@if($i['detalhe']) · {{ $i['detalhe'] }}@endif</div>
                </div>
                <div class="hoje-num" style="font-weight:700;color:{{ $i['sinal'] > 0 ? 'var(--color-success)' : 'var(--color-text)' }};">{{ $i['sinal'] > 0 ? '+' : '−' }} {{ $hBrl($i['valor']) }}</div>
            </div>
        @empty
            <div class="hoje-item" style="color:var(--color-text-muted);">Nada vence nos próximos 7 dias.</div>
        @endforelse
    </div>

    {{-- Atrasado --}}
    <div class="card" style="padding:16px 18px;{{ $hoje['atrasado']['itens'] ? 'border-left:4px solid var(--color-danger);' : '' }}">
        <div class="hoje-rotulo" style="{{ $hoje['atrasado']['itens'] ? 'color:var(--color-danger);' : '' }}">Atrasado</div>
        @if($hoje['atrasado']['itens'])
            <div class="hoje-num" style="font-size:22px;font-weight:700;color:var(--color-danger);">{{ $hBrl($hoje['atrasado']['total_pagar']) }}</div>
            @foreach(array_slice($hoje['atrasado']['itens'], 0, 6) as $i)
                <div class="hoje-item">
                    <div style="min-width:0;">
                        <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $i['descricao'] }}</div>
                        <div style="font-size:11px;color:var(--color-text-muted);">venceu {{ \Carbon\Carbon::parse($i['data'])->format('d/m') }} · {{ $hDias($i['data']) }}</div>
                    </div>
                    <div class="hoje-num" style="font-weight:700;">{{ $hBrl($i['valor']) }}</div>
                </div>
            @endforeach
        @else
            <div style="font-size:15px;font-weight:600;color:var(--color-success);margin-top:4px;"><i class="fa-solid fa-circle-check"></i> Nada atrasado</div>
        @endif
    </div>
</div>
