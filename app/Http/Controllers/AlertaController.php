<?php

namespace App\Http\Controllers;

use App\Services\Planejamento\PlanejamentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertaController extends Controller
{
    public function index()
    {
        $tenantId   = Auth::user()->tenant_id;
        $hoje       = Carbon::today();
        $hojeStr    = $hoje->format('Y-m-d');
        $em7dias    = $hoje->copy()->addDays(7)->format('Y-m-d');
        $inicioMes  = $hoje->copy()->startOfMonth()->format('Y-m-d');
        $fimMes     = $hoje->copy()->endOfMonth()->format('Y-m-d');
        $inicioAnt  = $hoje->copy()->subMonth()->startOfMonth()->format('Y-m-d');
        $fimAnt     = $hoje->copy()->subMonth()->endOfMonth()->format('Y-m-d');
        $diasNoMes  = $hoje->daysInMonth;
        $diaAtual   = $hoje->day;

        $alertas = collect();
        $idSeq = 0;

        // ═══════════════════════════════════════════════════════════════════
        // DADOS BASE
        // ═══════════════════════════════════════════════════════════════════

        $totalSaldoBancos = (float) DB::table('bancos')->where('tenant_id', $tenantId)->sum('saldo');

        $receitasPrevistas = (float) DB::table('receitas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereBetween('data_prevista_recebimento', [$inicioMes, $fimMes])
            ->sum('valor');

        $receitasRecebidas = (float) DB::table('receitas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereNotNull('data_recebimento')
            ->whereBetween('data_recebimento', [$inicioMes, $fimMes])
            ->sum('valor');

        $despesasMes = (float) DB::table('despesas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereBetween('data_compra', [$inicioMes, $fimMes])
            ->sum('valor');

        $projecaoMes = $receitasPrevistas - $despesasMes;

        // Categorias mês atual vs anterior
        $catsMesAtual = DB::table('despesas')
            ->join('categorias', 'despesas.categoria_id', '=', 'categorias.id')
            ->selectRaw('categorias.id as cat_id, categorias.nome as cat_nome, SUM(despesas.valor) as total')
            ->where('despesas.tenant_id', $tenantId)->whereNull('despesas.deleted_at')
            ->whereBetween('despesas.data_compra', [$inicioMes, $fimMes])
            ->groupBy('categorias.id', 'categorias.nome')
            ->get()->keyBy('cat_id');

        $catsMesAnt = DB::table('despesas')
            ->join('categorias', 'despesas.categoria_id', '=', 'categorias.id')
            ->selectRaw('categorias.id as cat_id, categorias.nome as cat_nome, SUM(despesas.valor) as total')
            ->where('despesas.tenant_id', $tenantId)->whereNull('despesas.deleted_at')
            ->whereBetween('despesas.data_compra', [$inicioAnt, $fimAnt])
            ->groupBy('categorias.id', 'categorias.nome')
            ->get()->keyBy('cat_id');

        // Média mensal de despesas (3 meses)
        $mediaDesp3meses = (float) DB::table('despesas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->where('data_compra', '>=', $hoje->copy()->subMonths(3)->format('Y-m-d'))
            ->sum('valor') / 3;

        // ═══════════════════════════════════════════════════════════════════
        // SCORE FINANCEIRO (0-100)
        // ═══════════════════════════════════════════════════════════════════

        $score = 100;

        // 1. Contas vencidas
        $vencidas = DB::table('despesas')
            ->leftJoin('categorias', 'despesas.categoria_id', '=', 'categorias.id')
            ->leftJoin('fornecedores', 'despesas.onde_comprou', '=', 'fornecedores.id')
            ->selectRaw("despesas.id, COALESCE(fornecedores.nome, despesas.onde_comprou, 'Despesa') as descricao, despesas.valor, despesas.data_compra, COALESCE(categorias.nome,'Sem categoria') as categoria")
            ->where('despesas.tenant_id', $tenantId)
            ->whereNull('despesas.deleted_at')
            ->whereNull('despesas.data_pagamento')
            ->where('despesas.data_compra', '<', $hojeStr)
            ->orderBy('despesas.data_compra')
            ->get();

        $totalVencido = $vencidas->sum('valor');
        $qtdVencidas  = $vencidas->count();
        if ($qtdVencidas > 0) $score -= min(30, $qtdVencidas * 3);

        // 2. Bancos com saldo negativo
        $bancosNegativos = DB::table('bancos')
            ->where('tenant_id', $tenantId)
            ->where('saldo', '<', 0)
            ->get();
        if ($bancosNegativos->count() > 0) $score -= min(20, $bancosNegativos->count() * 10);

        // 3. Cartões - uso de limite
        $todosCartoes = DB::table('bancos')
            ->where('tenant_id', $tenantId)
            ->where('tem_cartao_credito', 1)
            ->where('limite_cartao', '>', 0)
            ->get()
            ->map(function ($b) use ($tenantId, $inicioMes, $fimMes) {
                $b->gastos = (float) DB::table('despesas')
                    ->where('tenant_id', $tenantId)->whereNull('deleted_at')
                    ->where('forma_pagamento', $b->id)
                    ->whereBetween('data_compra', [$inicioMes, $fimMes])
                    ->sum('valor');
                $b->pct = $b->limite_cartao > 0 ? ($b->gastos / (float) $b->limite_cartao) * 100 : 0;
                return $b;
            });

        $cartoesCriticos = $todosCartoes->filter(fn($b) => $b->pct >= 90);
        $cartoesAtencao  = $todosCartoes->filter(fn($b) => $b->pct >= 70 && $b->pct < 90);

        $maxPctCartao = $todosCartoes->max('pct') ?? 0;
        if ($maxPctCartao >= 90) $score -= 15;
        elseif ($maxPctCartao >= 70) $score -= 8;

        // 4. Excesso de gastos (projeção negativa)
        if ($projecaoMes < 0) $score -= min(20, (int)(abs($projecaoMes) / 500) * 5);

        // 5. Saldo disponível vs média
        if ($mediaDesp3meses > 0 && $totalSaldoBancos < $mediaDesp3meses) $score -= 10;

        $score = max(0, min(100, $score));

        // Tendência
        $despMesAnt = (float) DB::table('despesas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereBetween('data_compra', [$inicioAnt, $fimAnt])
            ->sum('valor');

        $recMesAnt = (float) DB::table('receitas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereBetween('data_prevista_recebimento', [$inicioAnt, $fimAnt])
            ->sum('valor');

        $saldoAnt = $recMesAnt - $despMesAnt;
        if ($projecaoMes > $saldoAnt + 100) $tendencia = 'POSITIVA';
        elseif ($projecaoMes < $saldoAnt - 100) $tendencia = 'NEGATIVA';
        else $tendencia = 'ESTÁVEL';

        // ═══════════════════════════════════════════════════════════════════
        // ALERTAS — CRÍTICOS (resolver agora)
        // ═══════════════════════════════════════════════════════════════════

        // 1. Contas vencidas (agrupado)
        if ($qtdVencidas > 0) {
            $maisDias = (int) $vencidas->map(fn($d) => abs(Carbon::parse($d->data_compra)->diffInDays($hoje)))->max();
            $jurosEstimado = $totalVencido * 0.02 * ($maisDias / 30);

            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'critico',
                'intencao'         => 'resolver',
                'prioridade'       => 1,
                'icone'            => 'fa-circle-exclamation',
                'titulo'           => $qtdVencidas . ' conta(s) vencida(s)',
                'descricao'        => 'Você tem <strong>' . brl($totalVencido) . '</strong> em aberto. A mais antiga venceu há <strong>' . $maisDias . ' dia(s)</strong>.',
                'impacto'          => 'Impacto: -' . brl($totalVencido),
                'previsao'         => $jurosEstimado > 0 ? 'Possíveis encargos estimados: ' . brl($jurosEstimado) : null,
                'acao_label'       => 'Pagar agora',
                'acao_url'         => route('fluxo-caixa.index'),
                'contas_afetadas'  => $vencidas->take(10)->map(fn($d) => Carbon::parse($d->data_compra)->format('d/m') . ' — ' . $d->descricao . ' (' . brl($d->valor) . ')')->toArray(),
            ]);
        }

        // 2. Bancos com saldo negativo (agrupado)
        if ($bancosNegativos->count() > 0) {
            $totalNeg = $bancosNegativos->sum('saldo');
            $qtdBancos = $bancosNegativos->count();

            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'critico',
                'intencao'         => 'resolver',
                'prioridade'       => 2,
                'icone'            => 'fa-piggy-bank',
                'titulo'           => 'Saldo negativo em ' . $qtdBancos . ' conta(s)',
                'descricao'        => 'Total negativo: <strong class="text-danger">' . brl($totalNeg) . '</strong>. Pode haver cobrança de cheque especial/juros.',
                'impacto'          => 'Impacto: ' . brl($totalNeg),
                'previsao'         => 'Taxas de cheque especial podem gerar ' . brl(abs($totalNeg) * 0.08) . '/mês em juros',
                'acao_label'       => 'Ver contas afetadas',
                'acao_url'         => route('bancos.index'),
                'contas_afetadas'  => $bancosNegativos->map(fn($b) => $b->nome . ': ' . brl($b->saldo))->toArray(),
            ]);
        }

        // 3. Cartões acima de 90% (agrupado)
        if ($cartoesCriticos->count() > 0) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'critico',
                'intencao'         => 'resolver',
                'prioridade'       => 3,
                'icone'            => 'fa-credit-card',
                'titulo'           => 'Limite quase esgotado em ' . $cartoesCriticos->count() . ' cartão(ões)',
                'descricao'        => 'Cartões acima de 90% do limite utilizado. Risco de recusa ou juros rotativos.',
                'impacto'          => 'Limite restante: ' . brl($cartoesCriticos->sum(fn($c) => $c->limite_cartao - $c->gastos)),
                'previsao'         => null,
                'acao_label'       => 'Revisar cartões',
                'acao_url'         => route('despesas.index'),
                'contas_afetadas'  => $cartoesCriticos->map(fn($c) => $c->nome . ': ' . number_format($c->pct, 1) . '% usado (' . brl($c->gastos) . ' de ' . brl($c->limite_cartao) . ')')->values()->toArray(),
            ]);
        }

        // 4. Projeção do mês negativa
        if ($projecaoMes < 0) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'critico',
                'intencao'         => 'resolver',
                'prioridade'       => 4,
                'icone'            => 'fa-chart-line',
                'titulo'           => 'Projeção do mês negativa',
                'descricao'        => 'Despesas (<strong>' . brl($despesasMes) . '</strong>) superam receitas (<strong>' . brl($receitasPrevistas) . '</strong>) este mês.',
                'impacto'          => 'Impacto no saldo: ' . brl($projecaoMes),
                'previsao'         => $diaAtual < $diasNoMes ? 'Projeção final: ' . brl($projecaoMes) . ' (faltam ' . ($diasNoMes - $diaAtual) . ' dias)' : null,
                'acao_label'       => 'Ajustar gastos',
                'acao_url'         => route('despesas.index'),
                'contas_afetadas'  => [],
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // ALERTAS — ATENÇÃO (monitorar)
        // ═══════════════════════════════════════════════════════════════════

        // 5. Contas a vencer nos próximos 7 dias
        $aVencer = DB::table('despesas')
            ->leftJoin('categorias', 'despesas.categoria_id', '=', 'categorias.id')
            ->leftJoin('fornecedores', 'despesas.onde_comprou', '=', 'fornecedores.id')
            ->selectRaw("despesas.id, COALESCE(fornecedores.nome, despesas.onde_comprou, 'Despesa') as descricao, despesas.valor, despesas.data_compra, COALESCE(categorias.nome,'Sem categoria') as categoria")
            ->where('despesas.tenant_id', $tenantId)
            ->whereNull('despesas.deleted_at')
            ->whereNull('despesas.data_pagamento')
            ->whereBetween('despesas.data_compra', [$hojeStr, $em7dias])
            ->orderBy('despesas.data_compra')
            ->get();

        if ($aVencer->count() > 0) {
            $totalAVencer = $aVencer->sum('valor');
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'atencao',
                'intencao'         => 'resolver',
                'prioridade'       => 5,
                'icone'            => 'fa-calendar-days',
                'titulo'           => $aVencer->count() . ' conta(s) vencem em 7 dias',
                'descricao'        => 'Separe <strong>' . brl($totalAVencer) . '</strong> para os próximos pagamentos.',
                'impacto'          => 'Impacto: -' . brl($totalAVencer),
                'previsao'         => 'Se não pagas, entrarão na lista de vencidas',
                'acao_label'       => 'Evitar juros',
                'acao_url'         => route('fluxo-caixa.index'),
                'contas_afetadas'  => $aVencer->map(fn($d) => Carbon::parse($d->data_compra)->format('d/m') . ' — ' . $d->descricao . ' (' . brl($d->valor) . ')')->toArray(),
            ]);
        }

        // 6. Receitas a receber nos próximos 7 dias
        $aReceber = DB::table('receitas')
            ->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereNull('data_recebimento')
            ->whereBetween('data_prevista_recebimento', [$hojeStr, $em7dias])
            ->select('id', 'valor', 'data_prevista_recebimento', 'observacoes')
            ->get();

        if ($aReceber->count() > 0) {
            $totalAReceber = $aReceber->sum('valor');
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'atencao',
                'intencao'         => 'resolver',
                'prioridade'       => 6,
                'icone'            => 'fa-hand-holding-dollar',
                'titulo'           => '' . brl($totalAReceber) . ' a receber em 7 dias',
                'descricao'        => $aReceber->count() . ' receita(s) previstas. Confirme os recebimentos para manter o fluxo saudável.',
                'impacto'          => 'Impacto positivo: +' . brl($totalAReceber),
                'previsao'         => null,
                'acao_label'       => 'Confirmar recebimentos',
                'acao_url'         => route('receitas.index'),
                'contas_afetadas'  => $aReceber->map(fn($r) => Carbon::parse($r->data_prevista_recebimento)->format('d/m') . ' — ' . ($r->observacoes ?: 'Receita prevista') . ' (' . brl($r->valor) . ')')->toArray(),
            ]);
        }

        // 7. Cartões entre 70-89% (agrupado)
        if ($cartoesAtencao->count() > 0) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'atencao',
                'intencao'         => 'resolver',
                'prioridade'       => 7,
                'icone'            => 'fa-credit-card',
                'titulo'           => 'Limite elevado em ' . $cartoesAtencao->count() . ' cartão(ões)',
                'descricao'        => 'Cartões entre 70% e 89% do limite. Atenção para não ultrapassar.',
                'impacto'          => 'Limite restante: ' . brl($cartoesAtencao->sum(fn($c) => $c->limite_cartao - $c->gastos)),
                'previsao'         => null,
                'acao_label'       => 'Revisar cartões',
                'acao_url'         => route('despesas.index'),
                'contas_afetadas'  => $cartoesAtencao->map(fn($c) => $c->nome . ': ' . number_format($c->pct, 1) . '% usado')->values()->toArray(),
            ]);
        }

        // 8. Saldo bancário baixo (agrupado)
        $bancosLow = DB::table('bancos')
            ->where('tenant_id', $tenantId)
            ->where('saldo', '>=', 0)
            ->where('saldo', '<', 500)
            ->get();

        if ($bancosLow->count() > 0) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'atencao',
                'intencao'         => 'resolver',
                'prioridade'       => 8,
                'icone'            => 'fa-wallet',
                'titulo'           => 'Saldo baixo em ' . $bancosLow->count() . ' conta(s)',
                'descricao'        => 'Contas com menos de R$ 500. Fique atento para não entrar no negativo.',
                'impacto'          => 'Saldo total nessas contas: ' . brl($bancosLow->sum('saldo')),
                'previsao'         => null,
                'acao_label'       => 'Ver contas afetadas',
                'acao_url'         => route('bancos.index'),
                'contas_afetadas'  => $bancosLow->map(fn($b) => $b->nome . ': ' . brl($b->saldo))->toArray(),
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // ALERTAS — INFORMATIVOS (entender gastos)
        // ═══════════════════════════════════════════════════════════════════

        // 9. Aumento de gastos por categoria (>= 30%, agrupado com previsão)
        $categoriasAumento = [];
        foreach ($catsMesAtual as $catId => $catAtual) {
            if (isset($catsMesAnt[$catId])) {
                $anterior = (float) $catsMesAnt[$catId]->total;
                $atual    = (float) $catAtual->total;
                if ($anterior > 0) {
                    $variacao = (($atual - $anterior) / $anterior) * 100;
                    if ($variacao >= 30) {
                        $excesso = $atual - $anterior;
                        // Projetar valor final do mês
                        $projecaoCat = $diaAtual > 0 ? ($atual / $diaAtual) * $diasNoMes : $atual;
                        $categoriasAumento[] = [
                            'nome'      => $catAtual->cat_nome,
                            'atual'     => $atual,
                            'anterior'  => $anterior,
                            'variacao'  => $variacao,
                            'excesso'   => $excesso,
                            'projecao'  => $projecaoCat,
                        ];
                    }
                }
            }
        }

        // Ordenar por excesso (maior impacto primeiro)
        usort($categoriasAumento, fn($a, $b) => $b['excesso'] <=> $a['excesso']);

        foreach ($categoriasAumento as $cat) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'info',
                'intencao'         => 'entender',
                'prioridade'       => 10,
                'icone'            => 'fa-arrow-trend-up',
                'titulo'           => 'Excesso em ' . $cat['nome'],
                'descricao'        => 'Você gastou <strong>' . brl($cat['atual']) . '</strong> este mês (<strong>+' . number_format($cat['variacao'], 0) . '%</strong> vs mês anterior).',
                'impacto'          => 'Impacto no saldo: -' . brl($cat['excesso']),
                'previsao'         => 'Se continuar neste ritmo, chegará a ' . brl($cat['projecao']) . ' no fim do mês',
                'acao_label'       => 'Ajustar gastos',
                'acao_url'         => route('despesas.index'),
                'contas_afetadas'  => [],
            ]);
        }

        // 10. Receitas: % confirmadas
        if ($receitasPrevistas > 0) {
            $pctRecebido = ($receitasRecebidas / $receitasPrevistas) * 100;
            $faltaReceber = $receitasPrevistas - $receitasRecebidas;
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'info',
                'intencao'         => 'entender',
                'prioridade'       => 11,
                'icone'            => 'fa-chart-pie',
                'titulo'           => number_format($pctRecebido, 0) . '% das receitas confirmadas',
                'descricao'        => 'Recebido <strong>' . brl($receitasRecebidas) . '</strong> de <strong>' . brl($receitasPrevistas) . '</strong> previstos.',
                'impacto'          => 'Pendente: ' . brl($faltaReceber),
                'previsao'         => null,
                'acao_label'       => 'Confirmar recebimentos',
                'acao_url'         => route('receitas.index'),
                'contas_afetadas'  => [],
            ]);
        }

        // ═══════════════════════════════════════════════════════════════════
        // ALERTAS — OPORTUNIDADE (economizar)
        // ═══════════════════════════════════════════════════════════════════

        // 11. Mês no positivo - sugestão de investimento
        if ($projecaoMes > 0) {
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'oportunidade',
                'intencao'         => 'economizar',
                'prioridade'       => 12,
                'icone'            => 'fa-circle-check',
                'titulo'           => 'Sobra de ' . brl($projecaoMes) . ' no mês',
                'descricao'        => 'Parabéns! Receitas superam despesas. Considere investir parte desse valor.',
                'impacto'          => 'Sobra projetada: +' . brl($projecaoMes),
                'previsao'         => null,
                'acao_label'       => 'Ver investimentos',
                'acao_url'         => route('investimentos.index'),
                'contas_afetadas'  => [],
            ]);
        }

        // 12. Categorias onde economizou (agrupado)
        $economiaCats = [];
        foreach ($catsMesAnt as $catId => $catAnt) {
            $atualVal = isset($catsMesAtual[$catId]) ? (float) $catsMesAtual[$catId]->total : 0;
            $antVal   = (float) $catAnt->total;
            if ($antVal > 100 && $atualVal < $antVal) {
                $economiaCats[] = [
                    'nome'     => $catAnt->cat_nome,
                    'economia' => $antVal - $atualVal,
                    'variacao' => (($antVal - $atualVal) / $antVal) * 100,
                ];
            }
        }
        usort($economiaCats, fn($a, $b) => $b['economia'] <=> $a['economia']);
        $economiaCats = array_slice($economiaCats, 0, 3);

        if (!empty($economiaCats)) {
            $totalEconomia = array_sum(array_column($economiaCats, 'economia'));
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'oportunidade',
                'intencao'         => 'economizar',
                'prioridade'       => 13,
                'icone'            => 'fa-piggy-bank',
                'titulo'           => 'Economia de ' . brl($totalEconomia) . ' em ' . count($economiaCats) . ' categoria(s)',
                'descricao'        => 'Seus gastos diminuíram em relação ao mês anterior. Continue assim!',
                'impacto'          => 'Economia total: +' . brl($totalEconomia),
                'previsao'         => null,
                'acao_label'       => 'Revisar categorias',
                'acao_url'         => route('categorias.index'),
                'contas_afetadas'  => array_map(fn($e) => $e['nome'] . ': economizou ' . brl($e['economia']) . ' (' . number_format($e['variacao'], 0) . '% menos)', $economiaCats),
            ]);
        }

        // 13. Top 3 categorias (entender gastos)
        $topCats = $catsMesAtual->sortByDesc('total')->take(3)->values();
        if ($topCats->count() > 0) {
            $totalDespMes = $catsMesAtual->sum('total');
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'info',
                'intencao'         => 'entender',
                'prioridade'       => 14,
                'icone'            => 'fa-magnifying-glass-chart',
                'titulo'           => 'Onde você mais gastou este mês',
                'descricao'        => 'Suas 3 maiores categorias concentram <strong>' . ($totalDespMes > 0 ? number_format(($topCats->sum('total') / $totalDespMes) * 100, 0) : 0) . '%</strong> dos gastos.',
                'impacto'          => 'Total top 3: ' . brl($topCats->sum('total')),
                'previsao'         => null,
                'acao_label'       => 'Revisar categorias',
                'acao_url'         => route('despesas.index'),
                'contas_afetadas'  => $topCats->map(fn($c) => $c->cat_nome . ': ' . brl($c->total) . ' (' . ($totalDespMes > 0 ? number_format(($c->total / $totalDespMes) * 100, 0) : '0') . '%)')->toArray(),
            ]);
        }

        // 14. Reserva de emergência (economizar)
        $reservaIdeal = $mediaDesp3meses * 6;
        if ($totalSaldoBancos < $reservaIdeal && $mediaDesp3meses > 0) {
            $faltaReserva = $reservaIdeal - $totalSaldoBancos;
            $pctReserva = ($totalSaldoBancos / $reservaIdeal) * 100;
            $alertas->push([
                'id'               => ++$idSeq,
                'tipo'             => 'oportunidade',
                'intencao'         => 'economizar',
                'prioridade'       => 15,
                'icone'            => 'fa-shield-halved',
                'titulo'           => 'Reserva de emergência: ' . number_format($pctReserva, 0) . '% do ideal',
                'descricao'        => 'Ideal: <strong>' . brl($reservaIdeal) . '</strong> (6× média mensal). Faltam <strong>' . brl($faltaReserva) . '</strong>.',
                'impacto'          => 'Disponível: ' . brl(max(0, $totalSaldoBancos)),
                'previsao'         => $projecaoMes > 0 ? 'Se investir a sobra mensal, atinge o ideal em ~' . max(1, (int)ceil($faltaReserva / $projecaoMes)) . ' mês(es)' : null,
                'acao_label'       => 'Ver investimentos',
                'acao_url'         => route('investimentos.index'),
                'contas_afetadas'  => [],
            ]);
        }

        // ─── Ordenar por prioridade ──────────────────────────────────────
        $alertas = $alertas->sortBy('prioridade')->values();

        // ─── Contadores ──────────────────────────────────────────────────
        $contadores = [
            'critico'      => $alertas->where('tipo', 'critico')->count(),
            'atencao'      => $alertas->where('tipo', 'atencao')->count(),
            'info'         => $alertas->where('tipo', 'info')->count(),
            'oportunidade' => $alertas->where('tipo', 'oportunidade')->count(),
            'total'        => $alertas->count(),
        ];

        // ─── Resumo Executivo ────────────────────────────────────────────
        $resumo = [
            'saldo_projetado' => $projecaoMes,
            'tendencia'       => $tendencia,
            'alertas_criticos'=> $contadores['critico'],
            'score'           => $score,
        ];

        // ─── Alerta principal (maior impacto/urgência) ───────────────────
        $alertaPrincipal = $alertas->first();

        // Vencimentos vindos da planilha (atrasado, a pagar, a receber).
        $vencimentos = app(PlanejamentoService::class)->vencimentos($tenantId);

        return view('alertas.index', compact('alertas', 'contadores', 'resumo', 'alertaPrincipal', 'vencimentos'));
    }

    private function fmt($valor)
    {
        return number_format((float)$valor, 2, ',', '.');
    }
}
