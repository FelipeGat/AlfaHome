<?php

namespace App\Http\Controllers;

use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos do sininho: o que pede atenção agora — contas vencidas, fatura
 * vencendo, conta fixa não lançada (mês atual e próximo) e dízimo atrasado.
 * Só leitura; os números vêm do PlanejamentoService.
 */
class AvisosController extends Controller
{
    public function __construct(private PlanejamentoService $planejamento) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $hoje     = now()->startOfDay();
        $brl      = fn ($v) => brl($v);
        $itens    = [];

        $v = $this->planejamento->vencimentos($tenantId, $hoje);
        $vencidas = array_values(array_filter($v['atrasado'], fn ($i) => $i['tipo'] !== 'receita'));
        if ($vencidas) {
            $total = array_sum(array_map(fn ($i) => (float) $i['valor'], $vencidas));
            $itens[] = [
                'tipo'    => 'vencida',
                'titulo'  => count($vencidas) === 1 ? '1 conta vencida' : count($vencidas) . ' contas vencidas',
                'detalhe' => $brl($total) . ' · ' . implode(', ', array_slice(array_column($vencidas, 'descricao'), 0, 3)),
                'link'    => route('alertas.index'),
            ];
        }

        foreach ($v['a_pagar'] as $f) {
            if ($f['origem'] === 'fatura' && ! $f['pago'] && $f['data'] <= $hoje->copy()->addDays(5)->toDateString()) {
                $dias = (int) $hoje->diffInDays(Carbon::parse($f['data']), false);
                $itens[] = [
                    'tipo'    => 'fatura',
                    'titulo'  => $f['descricao'] . ($dias === 0 ? ' vence hoje' : ' vence em ' . Carbon::parse($f['data'])->format('d/m')),
                    'detalhe' => $brl($f['valor']),
                    'link'    => route('planejamento.cartoes'),
                ];
            }
        }

        $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        foreach ([$hoje->copy()->startOfMonth(), $hoje->copy()->startOfMonth()->addMonth()] as $mes) {
            $faltando = $this->planejamento->contasFixasSemLancamento($tenantId, $mes);
            if ($faltando) {
                $itens[] = [
                    'tipo'    => 'fixa',
                    'titulo'  => (count($faltando) === 1 ? '1 conta fixa não lançada' : count($faltando) . ' contas fixas não lançadas') . ' em ' . $meses[$mes->month - 1],
                    'detalhe' => implode(', ', array_map(fn ($f) => $f['conta'], $faltando)),
                    'link'    => route('planejamento.index', ['mes' => $mes->format('Y-m')]),
                ];
            }
        }

        $dizimo = $this->planejamento->dizimo($tenantId, $hoje);
        $atrasados = array_filter($dizimo['domingos'], fn ($d) => $d['situacao'] === 'atrasado');
        if ($atrasados && $dizimo['falta'] > 0) {
            $itens[] = [
                'tipo'    => 'dizimo',
                'titulo'  => 'Dízimo: falta devolver ' . $brl($dizimo['falta']),
                'detalhe' => 'Domingo ' . implode(', ', array_map(fn ($d) => Carbon::parse($d['data'])->format('d/m'), $atrasados)),
                'link'    => route('planejamento.index', ['mes' => $hoje->format('Y-m')]),
            ];
        }

        return response()->json(['total' => count($itens), 'itens' => $itens]);
    }
}
