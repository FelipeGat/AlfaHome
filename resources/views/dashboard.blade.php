@extends('layouts.main')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

@if($hoje['planilha_importada'])
@include('planejamento._hoje')
@endif

{{-- Com a planilha, o painel do dia acima é o Dashboard inteiro: filtros de
     período/pessoa e o resumo do planejamento repetiam números e só valem para
     lançamentos manuais. --}}
@if(! $hoje['planilha_importada'])
{{-- ─── Filtros Dashboard ───────────────────────────────────────────────── --}}
@php
    $dtDB         = \Carbon\Carbon::parse($inicio);
    $dtDBAnt      = $dtDB->copy()->subMonth();
    $dtDBProx     = $dtDB->copy()->addMonth();
    $mesNomeDB    = $dtDB->locale('pt_BR')->isoFormat('MMMM [de] YYYY');
    $ehMesAtualDB = $dtDB->format('Y-m') === now()->format('Y-m');
    $mesAtualIni  = now()->startOfMonth()->format('Y-m-d');
    $mesAtualFim2 = now()->endOfMonth()->format('Y-m-d');
    $filtroAtivo  = $familiarId || $inicio !== $mesAtualIni || $fim !== $mesAtualFim2;

    $urlDBMesAnt  = route('dashboard', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtDBAnt->startOfMonth()->format('Y-m-d'), 'fim' => $dtDBAnt->copy()->endOfMonth()->format('Y-m-d')]));
    $urlDBMesProx = route('dashboard', array_merge(request()->except(['inicio','fim']), ['inicio' => $dtDBProx->startOfMonth()->format('Y-m-d'), 'fim' => $dtDBProx->copy()->endOfMonth()->format('Y-m-d')]));
    $urlDBTodos   = route('dashboard', array_filter(['inicio' => $inicio, 'fim' => $fim]));
@endphp

<div class="card filtros-bar mb-5">
    <div class="filtros-lanc">

        {{-- Mês --}}
        <div class="filtro-grupo filtro-grupo-centro">
            <div style="display:flex;align-items:center;border:1px solid var(--color-border);border-radius:8px;overflow:hidden;">
                <a href="{{ $urlDBMesAnt }}" class="nav-mes-btn" title="Mês anterior"><i class="fa-solid fa-chevron-left" style="font-size:12px;"></i></a>
                <span class="mes-label-btn">{{ ucfirst($mesNomeDB) }}</span>
                <a href="{{ $urlDBMesProx }}" class="nav-mes-btn" title="Próximo mês"><i class="fa-solid fa-chevron-right" style="font-size:12px;"></i></a>
            </div>
            <div style="display:flex;gap:6px;">
                @if(!$ehMesAtualDB)
                <a href="{{ route('dashboard') }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:6px;text-decoration:none;white-space:nowrap;border:1px solid var(--color-border);background:var(--color-bg-card);color:var(--color-text-muted);">
                    <i class="fa-solid fa-rotate-left"></i> Mês Atual
                </a>
                @endif
                @if($filtroAtivo)
                <a href="{{ route('dashboard') }}"
                   style="padding:6px 11px;font-size:12px;font-weight:600;border-radius:9999px;text-decoration:none;white-space:nowrap;border:1px solid var(--color-danger);background:var(--color-danger-soft);color:var(--color-danger);">
                    <i class="fa-solid fa-xmark"></i> Limpar
                </a>
                @endif
            </div>
        </div>

        {{-- Membros --}}
        @if($familiares->isNotEmpty())
        <div class="filtro-grupo filtro-grupo-members">
            <div class="av-grupo">
                <a href="{{ $urlDBTodos }}" class="av-item" title="Todos da Casa">
                    <div class="av-circulo" style="border:3px solid {{ !$familiarId ? 'var(--color-primary)' : 'transparent' }};outline:{{ !$familiarId ? 'none' : '2px solid var(--color-border)' }};background:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-bg)' }};box-shadow:{{ !$familiarId ? '0 0 0 2px var(--color-primary)44' : 'none' }};">
                        <i class="fa-solid fa-house" style="font-size:13px;color:{{ !$familiarId ? '#fff' : 'var(--color-text-muted)' }};"></i>
                    </div>
                    <span class="av-nome" style="color:{{ !$familiarId ? 'var(--color-primary)' : 'var(--color-text-subtle)' }};font-weight:{{ !$familiarId ? '700' : '400' }};">Todos</span>
                </a>
                @foreach($familiares as $fam)
                @php
                    $dbSel  = $familiarId === $fam->id;
                    $dbIni  = implode('', array_map(fn($p) => strtoupper(substr($p,0,1)), array_slice(explode(' ',$fam->nome),0,2)));
                    $dbCors = ['#6366f1','#0ea5e9','#16a34a','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
                    $dbCor  = $dbCors[$fam->id % count($dbCors)];
                    $dbUrl  = $dbSel
                        ? route('dashboard', array_filter(['inicio'=>$inicio,'fim'=>$fim]))
                        : route('dashboard', array_filter(['inicio'=>$inicio,'fim'=>$fim,'familiar_id'=>$fam->id]));
                @endphp
                <a href="{{ $dbUrl }}" class="av-item" title="{{ $fam->nome }}">
                    <div class="av-circulo" style="border:3px solid {{ $dbSel ? $dbCor : 'transparent' }};outline:{{ $dbSel ? 'none' : '2px solid var(--color-border)' }};box-shadow:{{ $dbSel ? '0 0 0 2px '.$dbCor.'44' : 'none' }};">
                        @if($fam->foto)
                            <img src="{{ Storage::url($fam->foto) }}" alt="{{ $fam->nome }}" style="width:100%;height:100%;object-fit:cover;">
                        @else
                            <div style="width:100%;height:100%;background:{{ $dbCor }};color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;border-radius:50%;">{{ $dbIni }}</div>
                        @endif
                    </div>
                    <span class="av-nome" style="color:{{ $dbSel ? $dbCor : 'var(--color-text-subtle)' }};font-weight:{{ $dbSel ? '700' : '400' }};">{{ explode(' ',$fam->nome)[0] }}</span>
                </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>


@endif

{{-- Blocos que leem só lançamentos manuais: com a planilha, o painel do dia os substitui. --}}
@if(! $hoje['planilha_importada'])
{{-- ─── Posição Atual (Saldos) ──────────────────────────────────────────── --}}
@php
    // Saldo em Contas: apenas contas correntes (exclui cartão de crédito, poupança exclusiva, dinheiro)
    $contasCorrentes   = $bancos->where('tem_conta_corrente', true);
    $saldoTotalContas  = $contasCorrentes->sum('saldo');
    $creditoDisponivel = max(0, $totalLimiteCartoes - $totalFaturaCartoes);
@endphp

<style>
.db-kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:14px; }
@media (max-width:640px) { .db-kpi-row { grid-template-columns:1fr; } }

.db-saldo-section { margin-bottom:20px; }
.db-section-label {
    font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
    color:var(--color-text-subtle); margin-bottom:10px; display:flex; align-items:center; gap:6px;
}
.db-section-label::after { content:''; flex:1; height:1px; background:var(--color-border); }

.db-banco-item { display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--color-border); gap:8px; }
.db-banco-item:last-child { border-bottom:none; }
.db-banco-nome { font-size:12px; font-weight:600; color:var(--color-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.db-banco-tipo { font-size:10px; color:var(--color-text-subtle); }
.db-banco-val  { font-size:13px; font-weight:700; white-space:nowrap; flex-shrink:0; }
</style>

<div class="db-saldo-section">
    <div class="db-section-label"><i class="fa-solid fa-circle-dot" style="font-size:8px;color:var(--color-indigo);"></i> Posição Atual</div>

    <div class="db-kpi-row">

        {{-- Saldo em Contas --}}
        <div class="card" style="border-top:3px solid {{ $saldoTotalContas >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};padding:14px 16px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;">
                <div>
                    <div class="kpi-label" style="margin-bottom:2px;"><i class="fa-solid fa-building-columns" style="color:var(--color-success);"></i> Saldo em Contas</div>
                    <div style="font-size:clamp(16px,3vw,22px);font-weight:700;color:{{ $saldoTotalContas >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                        R$ {{ number_format($saldoTotalContas, 2, ',', '.') }}
                    </div>
                </div>
                <div style="background:var(--color-success-soft);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-building-columns" style="color:var(--color-success);font-size:14px;"></i>
                </div>
            </div>
            <div style="border-top:1px solid var(--color-border);padding-top:8px;">
                @forelse($contasCorrentes->take(4) as $banco)
                <div class="db-banco-item">
                    <div style="min-width:0;">
                        <div class="db-banco-nome">{{ $banco->nome }}</div>
                        <div class="db-banco-tipo">{{ $banco->eh_dinheiro ? 'Dinheiro' : 'Banco' }}</div>
                    </div>
                    <div class="db-banco-val {{ $banco->saldo >= 0 ? 'text-green' : 'text-red' }}">
                        R$ {{ number_format($banco->saldo, 2, ',', '.') }}
                    </div>
                </div>
                @empty
                <div style="font-size:12px;color:var(--color-text-subtle);text-align:center;padding:8px 0;">Nenhuma conta corrente cadastrada</div>
                @endforelse
                @if($contasCorrentes->count() > 4)
                <div style="font-size:11px;color:var(--color-text-subtle);text-align:center;padding-top:4px;">+{{ $contasCorrentes->count() - 4 }} conta(s)</div>
                @endif
            </div>
        </div>

        {{-- Fatura dos Cartões --}}
        <div class="card" style="border-top:3px solid var(--color-violet);padding:14px 16px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;">
                <div>
                    <div class="kpi-label" style="margin-bottom:2px;"><i class="fa-solid fa-credit-card" style="color:var(--color-violet);"></i> Fatura dos Cartões</div>
                    <div style="font-size:clamp(16px,3vw,22px);font-weight:700;color:var(--color-danger);">
                        R$ {{ number_format($totalFaturaCartoes, 2, ',', '.') }}
                    </div>
                </div>
                <div style="background:var(--color-violet-soft);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-credit-card" style="color:var(--color-violet);font-size:14px;"></i>
                </div>
            </div>
            <div style="border-top:1px solid var(--color-border);padding-top:8px;">
                @forelse($cartoes->take(4) as $cartao)
                <div class="db-banco-item">
                    <div style="min-width:0;">
                        <div class="db-banco-nome">{{ $cartao->nome }}</div>
                        <div style="height:4px;border-radius:2px;background:var(--color-border);margin-top:3px;overflow:hidden;">
                            <div style="height:100%;border-radius:2px;width:{{ min($cartao->percentual_uso,100) }}%;background:{{ $cartao->percentual_uso > 80 ? 'var(--color-danger)' : ($cartao->percentual_uso > 50 ? 'var(--color-warning)' : 'var(--color-violet)') }};"></div>
                        </div>
                        <div class="db-banco-tipo" style="margin-top:2px;">{{ $cartao->percentual_uso }}% usado</div>
                    </div>
                    <div class="db-banco-val text-red">
                        R$ {{ number_format($cartao->gastos_periodo, 2, ',', '.') }}
                    </div>
                </div>
                @empty
                <div style="font-size:12px;color:var(--color-text-subtle);text-align:center;padding:8px 0;">Nenhum cartão cadastrado</div>
                @endforelse
                @if($cartoes->count() > 4)
                <div style="font-size:11px;color:var(--color-text-subtle);text-align:center;padding-top:4px;">+{{ $cartoes->count() - 4 }} cartão(ões)</div>
                @endif
            </div>
        </div>

        {{-- Crédito Disponível --}}
        <div class="card" style="border-top:3px solid var(--color-info);padding:14px 16px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:10px;">
                <div>
                    <div class="kpi-label" style="margin-bottom:2px;"><i class="fa-solid fa-circle-check" style="color:var(--color-info);"></i> Crédito Disponível</div>
                    <div style="font-size:clamp(16px,3vw,22px);font-weight:700;color:var(--color-info);">
                        R$ {{ number_format($creditoDisponivel, 2, ',', '.') }}
                    </div>
                </div>
                <div style="background:var(--color-info-soft);width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fa-solid fa-circle-check" style="color:var(--color-info);font-size:14px;"></i>
                </div>
            </div>
            <div style="border-top:1px solid var(--color-border);padding-top:8px;">
                @if($totalLimiteCartoes > 0)
                @php $percUsado = round(($totalFaturaCartoes / $totalLimiteCartoes) * 100); @endphp
                <div style="margin-bottom:6px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:4px;">
                        <span style="color:var(--color-text-subtle);">Limite total utilizado</span>
                        <span style="font-weight:700;color:{{ $percUsado > 80 ? 'var(--color-danger)' : ($percUsado > 50 ? 'var(--color-warning)' : 'var(--color-info)') }};">{{ $percUsado }}%</span>
                    </div>
                    <div style="height:6px;border-radius:3px;background:var(--color-border);overflow:hidden;">
                        <div style="height:100%;border-radius:3px;width:{{ min($percUsado,100) }}%;background:{{ $percUsado > 80 ? 'var(--color-danger)' : ($percUsado > 50 ? 'var(--color-warning)' : 'var(--color-info)') }};transition:width .3s;"></div>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;">
                    <span style="color:var(--color-text-subtle);">Limite total</span>
                    <span style="font-weight:600;color:var(--color-text);">R$ {{ number_format($totalLimiteCartoes, 2, ',', '.') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:3px;">
                    <span style="color:var(--color-text-subtle);">Fatura aberta</span>
                    <span style="font-weight:600;color:var(--color-danger);">R$ {{ number_format($totalFaturaCartoes, 2, ',', '.') }}</span>
                </div>
                @else
                <div style="font-size:12px;color:var(--color-text-subtle);text-align:center;padding:8px 0;">Sem limite cadastrado</div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- ─── KPIs do Período ────────────────────────────────────────────────────── --}}
<div class="db-section-label"><i class="fa-solid fa-circle-dot" style="font-size:8px;color:var(--color-success);"></i> Período Selecionado</div>

<div class="db-kpi-row" style="margin-bottom:20px;">
    {{-- ── Receitas: destaque no Realizado, previsto em menor ── --}}
    <div class="card" style="border-top: 3px solid var(--color-success);">
        <div class="d-flex justify-between align-center">
            <div>
                <div class="kpi-label">Receitas Realizadas</div>
                <div class="kpi-value text-green">R$ {{ number_format($receitasRealizadas, 2, ',', '.') }}</div>
                <div class="mt-1">
                    @if($variacaoReceitas >= 0)
                        <span class="badge badge-green"><i class="fa-solid fa-arrow-up"></i> {{ number_format($variacaoReceitas, 1) }}%</span>
                    @else
                        <span class="badge badge-red"><i class="fa-solid fa-arrow-down"></i> {{ number_format(abs($variacaoReceitas), 1) }}%</span>
                    @endif
                    <span class="text-subtle" style="font-size:11px;"> vs mês anterior</span>
                </div>
            </div>
            <div class="kpi-icon" style="background:var(--color-success-soft); color:var(--color-success);">
                <i class="fa-solid fa-arrow-trend-up"></i>
            </div>
        </div>
        <div class="kpi-sub">
            Previsto: <strong style="color:var(--color-text-muted)">R$ {{ number_format($totalReceitas, 2, ',', '.') }}</strong>
        </div>
    </div>

    {{-- ── Despesas: destaque no Realizado, previsto em menor ── --}}
    <div class="card" style="border-top: 3px solid var(--color-danger);">
        <div class="d-flex justify-between align-center">
            <div>
                <div class="kpi-label">Despesas Realizadas</div>
                <div class="kpi-value text-red">R$ {{ number_format($despesasRealizadas, 2, ',', '.') }}</div>
                <div class="mt-1">
                    @if($variacaoDespesas <= 0)
                        <span class="badge badge-green"><i class="fa-solid fa-arrow-down"></i> {{ number_format(abs($variacaoDespesas), 1) }}%</span>
                    @else
                        <span class="badge badge-red"><i class="fa-solid fa-arrow-up"></i> {{ number_format($variacaoDespesas, 1) }}%</span>
                    @endif
                    <span class="text-subtle" style="font-size:11px;"> vs mês anterior</span>
                </div>
            </div>
            <div class="kpi-icon" style="background:var(--color-danger-soft); color:var(--color-danger);">
                <i class="fa-solid fa-arrow-trend-down"></i>
            </div>
        </div>
        <div class="kpi-sub">
            Previsto: <strong style="color:var(--color-text-muted)">R$ {{ number_format($totalDespesas, 2, ',', '.') }}</strong>
        </div>
    </div>

    {{-- ── Saldo: destaque no saldo previsto, sub = saldo realizado ── --}}
    @php $saldoRealizado = $receitasRealizadas - $despesasRealizadas; @endphp
    <div class="card" style="border-top: 3px solid {{ $saldo >= 0 ? 'var(--color-indigo)' : 'var(--color-danger)' }};">
        <div class="d-flex justify-between align-center">
            <div>
                <div class="kpi-label">Saldo do Período</div>
                <div class="kpi-value" style="color: {{ $saldo >= 0 ? 'var(--color-indigo)' : 'var(--color-danger)' }}">
                    R$ {{ number_format($saldo, 2, ',', '.') }}
                </div>
                <div class="mt-1">
                    @if($variacaoSaldo >= 0)
                        <span class="badge badge-green"><i class="fa-solid fa-arrow-up"></i> {{ number_format($variacaoSaldo, 1) }}%</span>
                    @else
                        <span class="badge badge-red"><i class="fa-solid fa-arrow-down"></i> {{ number_format(abs($variacaoSaldo), 1) }}%</span>
                    @endif
                    <span class="text-subtle" style="font-size:11px;"> vs mês anterior</span>
                </div>
            </div>
            <div class="kpi-icon" style="background:var(--color-violet-soft); color:var(--color-violet);">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>
        </div>
        <div class="kpi-sub">
            Saldo real:
            <strong style="color:{{ $saldoRealizado >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }}">
                {{ $saldoRealizado >= 0 ? '+' : '' }}R$ {{ number_format($saldoRealizado, 2, ',', '.') }}
            </strong>
        </div>
    </div>
</div>

{{-- Info cards --}}
<div class="grid-4 mb-5">

    {{-- Pago — Último Mês · Em aberto em menor destaque --}}
    <div class="card">
        <div class="kpi-label">Pago — Último Mês</div>
        <div class="kpi-value text-red mt-1">R$ {{ number_format($pagamentoUltimoMes, 2, ',', '.') }}</div>
        @if($apagarUltimoMes > 0)
        <div class="kpi-sub" style="margin-top:6px;">
            Em aberto: <strong style="color:var(--color-amber)">R$ {{ number_format($apagarUltimoMes, 2, ',', '.') }}</strong>
        </div>
        @else
        <div class="kpi-sub" style="margin-top:6px;color:var(--color-success);">
            <i class="fa-solid fa-check" style="font-size:9px;"></i> Tudo pago
        </div>
        @endif
    </div>

    {{-- Recebido — Último Mês · Em aberto em menor destaque --}}
    <div class="card">
        <div class="kpi-label">Recebido — Último Mês</div>
        <div class="kpi-value text-green mt-1">R$ {{ number_format($recebidoUltimoMes, 2, ',', '.') }}</div>
        @if($aReceberUltimoMes > 0)
        <div class="kpi-sub" style="margin-top:6px;">
            A receber: <strong style="color:var(--color-amber)">R$ {{ number_format($aReceberUltimoMes, 2, ',', '.') }}</strong>
        </div>
        @else
        <div class="kpi-sub" style="margin-top:6px;color:var(--color-success);">
            <i class="fa-solid fa-check" style="font-size:9px;"></i> Tudo recebido
        </div>
        @endif
    </div>

    {{-- À Pagar — Próx. Mês · Já pago em menor destaque --}}
    <div class="card">
        <div class="kpi-label">À Pagar — Próx. Mês</div>
        <div class="kpi-value text-amber mt-1">R$ {{ number_format($previsaoDespesasProxMes, 2, ',', '.') }}</div>
        @if($pagoProximoMes > 0)
        <div class="kpi-sub" style="margin-top:6px;">
            Já pago: <strong style="color:var(--color-success)">R$ {{ number_format($pagoProximoMes, 2, ',', '.') }}</strong>
        </div>
        @else
        <div class="kpi-sub" style="margin-top:6px;color:var(--color-text-subtle);">
            Nenhum pago ainda
        </div>
        @endif
    </div>

    {{-- À Receber — Próx. Mês · Já recebido em menor destaque --}}
    <div class="card">
        <div class="kpi-label">À Receber — Próx. Mês</div>
        <div class="kpi-value mt-1" style="color:var(--color-indigo)">R$ {{ number_format($previsaoReceitasProxMes, 2, ',', '.') }}</div>
        @if($recebidoProximoMes > 0)
        <div class="kpi-sub" style="margin-top:6px;">
            Já recebido: <strong style="color:var(--color-success)">R$ {{ number_format($recebidoProximoMes, 2, ',', '.') }}</strong>
        </div>
        @else
        <div class="kpi-sub" style="margin-top:6px;color:var(--color-text-subtle);">
            Nenhum recebido ainda
        </div>
        @endif
    </div>

</div>

{{-- Gráfico: Fluxo de caixa (100%) --}}
<div class="mb-5">
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-chart-column" style="color:var(--color-indigo);"></i>
            Fluxo de Caixa {{ $ano }}
        </div>
        <div class="chart-box">
            <canvas id="annualChart"></canvas>
        </div>
    </div>
</div>

{{-- Gastos com Cartões + Patrimônio Acumulado --}}
<div class="grid-2 mb-5">
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-credit-card" style="color:var(--color-violet);"></i>
            Gastos com Cartões
        </div>

        {{-- Resumo geral --}}
        <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--color-bg);border-radius:8px;margin-bottom:12px;gap:8px;">
            <div style="text-align:center;flex:1;min-width:80px;">
                <div style="font-size:11px;color:var(--color-text-muted);">Fatura Total</div>
                <div class="fw-600 text-red" style="font-size:14px;">R$ {{ number_format($totalFaturaCartoes, 2, ',', '.') }}</div>
            </div>
            <div style="width:1px;height:30px;background:var(--color-border);"></div>
            <div style="text-align:center;flex:1;min-width:80px;">
                <div style="font-size:11px;color:var(--color-text-muted);">Gastos no Período</div>
                <div class="fw-600" style="font-size:14px;color:var(--color-warning);">R$ {{ number_format($totalGastosCartoes, 2, ',', '.') }}</div>
            </div>
            <div style="width:1px;height:30px;background:var(--color-border);"></div>
            <div style="text-align:center;flex:1;min-width:80px;">
                <div style="font-size:11px;color:var(--color-text-muted);">Limite Total</div>
                <div class="fw-600" style="font-size:14px;color:var(--color-indigo);">R$ {{ number_format($totalLimiteCartoes, 2, ',', '.') }}</div>
            </div>
        </div>

        {{-- Lista de cartões --}}
        @forelse($cartoes as $cartao)
            <div style="padding:10px 0;{{ !$loop->last ? 'border-bottom:1px solid var(--color-border);' : '' }}">
                <div class="d-flex justify-between align-center mb-1">
                    <div class="d-flex align-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:{{ $cartao->cor ?? 'var(--color-violet)' }}20;">
                            <i class="fa-solid fa-credit-card" style="font-size:13px;color:{{ $cartao->cor ?? 'var(--color-violet)' }};"></i>
                        </div>
                        <div>
                            <div class="fw-600" style="font-size:13px;">{{ $cartao->nome }}</div>
                            <div style="font-size:11px;color:var(--color-text-subtle);">Limite: R$ {{ number_format($cartao->limite_cartao, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="fw-600 text-red" style="font-size:13px;">R$ {{ number_format($cartao->saldo_fatura, 2, ',', '.') }}</div>
                    </div>
                </div>
                {{-- Barra de uso do limite --}}
                <div style="background:var(--color-border);border-radius:4px;height:6px;overflow:hidden;margin-top:6px;">
                    <div style="height:100%;border-radius:4px;width:{{ min($cartao->percentual_uso, 100) }}%;background:{{ $cartao->percentual_uso > 80 ? 'var(--color-danger)' : ($cartao->percentual_uso > 50 ? 'var(--color-warning)' : 'var(--color-success)') }};"></div>
                </div>
                <div class="d-flex justify-between" style="margin-top:3px;">
                    <span style="font-size:10px;color:var(--color-text-subtle);">{{ $cartao->percentual_uso }}% usado</span>
                    <span style="font-size:10px;color:var(--color-success);">Disponível: R$ {{ number_format($cartao->limite_disponivel, 2, ',', '.') }}</span>
                </div>
                @if($cartao->dia_fechamento_cartao)
                    @php $melhorDia = $cartao->dia_fechamento_cartao >= 28 ? 1 : $cartao->dia_fechamento_cartao + 1; @endphp
                    <div style="font-size:10px;margin-top:4px;padding:3px 6px;background:var(--color-violet-soft);border-radius:3px;color:var(--color-violet);text-align:center;">
                        <i class="fa-solid fa-lightbulb" style="font-size:9px;"></i> Melhor compra: dia <strong>{{ $melhorDia }}</strong>
                    </div>
                @endif
            </div>
        @empty
            <div class="empty-state"><i class="fa-solid fa-credit-card"></i><p>Nenhum cartão cadastrado</p></div>
        @endforelse
    </div>

    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-chart-line" style="color:var(--color-warning);"></i>
            Aportes acumulados {{ $ano }}
        </div>
        <div class="chart-box">
            <canvas id="investChart"></canvas>
        </div>
    </div>
</div>

{{-- Gráficos: Categorias --}}
<div class="grid-2 mb-5">
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-chart-pie" style="color:var(--color-danger);"></i>
            Despesas por Categoria
        </div>
        @if(count($despesasPorCategoria) > 0)
            <div class="chart-box-sm"><canvas id="expCatChart"></canvas></div>
        @else
            <div class="empty-state"><i class="fa-regular fa-circle-xmark"></i><p>Sem despesas no período</p></div>
        @endif
    </div>
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-chart-pie" style="color:var(--color-success);"></i>
            Receitas por Categoria
        </div>
        @if(count($receitasPorCategoria) > 0)
            <div class="chart-box-sm"><canvas id="incCatChart"></canvas></div>
        @else
            <div class="empty-state"><i class="fa-regular fa-circle-xmark"></i><p>Sem receitas no período</p></div>
        @endif
    </div>
</div>

{{-- Contas + Últimos Lançamentos --}}
<div class="grid-2">
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-building-columns"></i> Contas Bancárias
        </div>
        @forelse($bancos as $banco)
            <div class="d-flex justify-between align-center" style="padding:9px 0; border-bottom:1px solid var(--color-border);">
                <div>
                    <div class="fw-600" style="font-size:13px;">{{ $banco->nome }}</div>
                    <div style="font-size:11px;" class="text-subtle">{{ implode(', ', array_filter([$banco->eh_dinheiro ? 'Dinheiro' : '', $banco->tem_conta_corrente ? 'Conta Corrente' : '', $banco->tem_poupanca ? 'Poupança' : '', $banco->tem_cartao_credito ? 'Cartão de Crédito' : ''])) ?: 'Nenhum' }}</div>
                </div>
                <div class="text-right">
                    <div class="fw-600 {{ $banco->saldo >= 0 ? 'text-green' : 'text-red' }}" style="font-size:14px;">
                        R$ {{ number_format($banco->saldo, 2, ',', '.') }}
                    </div>
                    @if($banco->limite_cartao > 0)
                        <div style="font-size:11px;" class="text-subtle">
                            Cartão: R$ {{ number_format($banco->saldo_cartao, 2, ',', '.') }}
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state"><i class="fa-solid fa-building-columns"></i><p>Nenhuma conta cadastrada</p></div>
        @endforelse
        <div class="mt-3">
            <a href="{{ route('bancos.index') }}" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center;">
                Ver todas
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left"></i> Últimos Lançamentos
        </div>
        @forelse($ultimosLancamentos as $lancamento)
            <div class="d-flex justify-between align-center" style="padding:8px 0; border-bottom:1px solid var(--color-border);">
                <div class="d-flex align-center gap-2">
                    <div style="width:30px;height:30px;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:{{ $lancamento->tipo === 'receita' ? 'var(--color-success-soft)' : 'var(--color-danger-soft)' }};">
                        <i class="fa-solid {{ $lancamento->tipo === 'receita' ? 'fa-arrow-down' : 'fa-arrow-up' }}" style="font-size:11px;color:{{ $lancamento->tipo === 'receita' ? 'var(--color-success)' : 'var(--color-danger)' }};"></i>
                    </div>
                    <div>
                        <div class="fw-600" style="font-size:13px;">{{ $lancamento->categoria_nome }}</div>
                        <div style="font-size:11px;" class="text-subtle">{{ \Carbon\Carbon::parse($lancamento->data)->format('d/m/Y') }}</div>
                    </div>
                </div>
                <div class="fw-600 {{ $lancamento->tipo === 'receita' ? 'text-green' : 'text-red' }}" style="font-size:13px;">
                    {{ $lancamento->tipo === 'receita' ? '+' : '−' }} R$ {{ number_format($lancamento->valor, 2, ',', '.') }}
                </div>
            </div>
        @empty
            <div class="empty-state"><i class="fa-regular fa-folder-open"></i><p>Nenhum lançamento encontrado</p></div>
        @endforelse
    </div>
</div>

@endif
@endsection

@push('scripts')
@if(! $hoje['planilha_importada'])
<script>
const _meses = {!! json_encode($mesesLabels) !!};
const _colors = ['#4f46e5','#16a34a','#d97706','#dc2626','#7c3aed','#db2777','#0891b2','#ea580c','#0d9488','#65a30d','#9333ea','#2563eb'];

const _annualData   = { receitas: {!! json_encode($receitasMes) !!}, despesas: {!! json_encode($despesasMes) !!} };
const _investData   = {!! json_encode($patrimonioAcumulado) !!};
@if(count($despesasPorCategoria) > 0)
const _expCatData   = { labels: {!! json_encode($despesasPorCategoria->pluck('nome')) !!}, data: {!! json_encode($despesasPorCategoria->pluck('total')) !!} };
@else
const _expCatData   = null;
@endif
@if(count($receitasPorCategoria) > 0)
const _incCatData   = { labels: {!! json_encode($receitasPorCategoria->pluck('nome')) !!}, data: {!! json_encode($receitasPorCategoria->pluck('total')) !!} };
@else
const _incCatData   = null;
@endif

const _cssVar = (name) => getComputedStyle(document.body).getPropertyValue(name).trim();
const _themeColors = () => ({
    grid:  _cssVar('--color-border')      || '#f1f5f9',
    tick:  _cssVar('--color-text-muted')  || '#6B7280',
    label: _cssVar('--color-text')        || '#1F2937',
});

let _dashCharts = [];
function _buildDashCharts() {
    _dashCharts.forEach(c => { try { c.destroy(); } catch(e) {} });
    _dashCharts = [];
    const t = _themeColors();
    const defaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 11 }, boxWidth: 12, padding: 10, color: t.label }
            }
        }
    };
    const axisOpts = {
        y: { beginAtZero: true, ticks: { font: { size: 10 }, color: t.tick }, grid: { color: t.grid } },
        x: { ticks: { font: { size: 10 }, color: t.tick }, grid: { display: false } }
    };

    _dashCharts.push(new Chart(document.getElementById('annualChart'), {
        type: 'bar',
        data: {
            labels: _meses,
            datasets: [
                { label: 'Receitas', data: _annualData.receitas, backgroundColor: 'rgba(22,163,74,.7)', borderRadius: 4, borderSkipped: false },
                { label: 'Despesas', data: _annualData.despesas, backgroundColor: 'rgba(220,38,38,.65)', borderRadius: 4, borderSkipped: false }
            ]
        },
        options: { ...defaults, scales: axisOpts }
    }));

    _dashCharts.push(new Chart(document.getElementById('investChart'), {
        type: 'line',
        data: {
            labels: _meses,
            datasets: [{ label: 'Aportado (R$)', data: _investData, borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,.08)', fill: true, tension: .35, pointRadius: 3 }]
        },
        options: { ...defaults, scales: axisOpts }
    }));

    if (_expCatData) {
        _dashCharts.push(new Chart(document.getElementById('expCatChart'), {
            type: 'doughnut',
            data: { labels: _expCatData.labels, datasets: [{ data: _expCatData.data, backgroundColor: _colors, borderWidth: 1, borderColor: _cssVar('--color-bg-card') || '#fff' }] },
            options: { ...defaults, cutout: '60%' }
        }));
    }

    if (_incCatData) {
        _dashCharts.push(new Chart(document.getElementById('incCatChart'), {
            type: 'doughnut',
            data: { labels: _incCatData.labels, datasets: [{ data: _incCatData.data, backgroundColor: _colors, borderWidth: 1, borderColor: _cssVar('--color-bg-card') || '#fff' }] },
            options: { ...defaults, cutout: '60%' }
        }));
    }
}

_buildDashCharts();

document.getElementById('theme-toggle')?.addEventListener('click', () => {
    setTimeout(_buildDashCharts, 50);
});
</script>
@endif
@endpush
