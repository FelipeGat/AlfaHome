<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Receita;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FluxoCaixaController extends Controller
{
    public function index(Request $request)
    {
        // ── Período ──────────────────────────────────────────────────────────
        $periodo = $request->get('periodo', 'semana');

        if ($periodo === 'mes') {
            $inicio = now()->startOfMonth()->format('Y-m-d');
            $fim    = now()->endOfMonth()->format('Y-m-d');
        } elseif ($periodo === 'custom') {
            $inicio = $request->get('inicio', now()->startOfWeek()->format('Y-m-d'));
            $fim    = $request->get('fim',    now()->endOfWeek()->format('Y-m-d'));
        } else {
            // semana (default): segunda → domingo
            $inicio = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            $fim    = now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $periodo = 'semana';
        }

        // ── Despesas no período ───────────────────────────────────────────────
        $despesas = Despesa::with(['categoria', 'fornecedor', 'banco', 'familiar'])
            ->whereBetween('data_compra', [$inicio, $fim])
            ->orderBy('data_compra')
            ->orderBy('id')
            ->get();

        // ── Receitas no período ───────────────────────────────────────────────
        $receitas = Receita::with(['categoria', 'banco', 'familiar'])
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
            ->orderBy('data_prevista_recebimento')
            ->orderBy('id')
            ->get();

        // ── Totais ────────────────────────────────────────────────────────────
        $totalAPagar     = $despesas->whereNull('data_pagamento')->sum('valor');
        $totalAReceber   = $receitas->whereNull('data_recebimento')->sum('valor');
        $totalPago       = $despesas->whereNotNull('data_pagamento')->sum('valor');
        $totalRecebido   = $receitas->whereNotNull('data_recebimento')->sum('valor');
        $saldoProjetado  = $totalAReceber - $totalAPagar;

        return view('fluxo-caixa.index', compact(
            'despesas', 'receitas',
            'totalAPagar', 'totalAReceber', 'totalPago', 'totalRecebido', 'saldoProjetado',
            'inicio', 'fim', 'periodo'
        ));
    }

    // ── Baixar Despesa (marcar como paga) ─────────────────────────────────────
    public function baixarDespesa(Request $request, Despesa $despesa)
    {
        $this->authorize('update', $despesa);

        $request->validate([
            'data_pagamento' => 'required|date',
            'valor'          => 'nullable|numeric|min:0.01',
        ]);

        // O DespesaObserver atualiza o saldo do banco automaticamente, usando
        // o $despesa->valor já atualizado quando a data_pagamento muda.
        $dados = ['data_pagamento' => $request->data_pagamento];
        if ($request->filled('valor')) {
            $dados += $this->valorRealizado($despesa, (float) $request->valor);
        }
        $despesa->update($dados);

        return back()->with('success', 'Despesa baixada com sucesso!');
    }

    // ── Estornar Despesa (desfazer baixa) ─────────────────────────────────────
    public function estornarDespesa(Despesa $despesa)
    {
        $this->authorize('update', $despesa);

        // O DespesaObserver estorna o saldo do banco automaticamente
        $despesa->update(['data_pagamento' => null]);
        $this->restaurarPrevisto($despesa);

        return back()->with('success', 'Baixa da despesa estornada.');
    }

    // ── Baixar Receita (marcar como recebida) ─────────────────────────────────
    public function baixarReceita(Request $request, Receita $receita)
    {
        $this->authorize('update', $receita);

        $request->validate([
            'data_recebimento' => 'required|date',
            'valor'            => 'nullable|numeric|min:0.01',
        ]);

        // O ReceitaObserver atualiza o saldo do banco automaticamente, usando
        // o $receita->valor já atualizado quando a data_recebimento muda.
        $dados = ['data_recebimento' => $request->data_recebimento];
        if ($request->filled('valor')) {
            $dados += $this->valorRealizado($receita, (float) $request->valor);
        }
        $receita->update($dados);

        return back()->with('success', 'Receita baixada com sucesso!');
    }

    // ── Estornar Receita (desfazer baixa) ─────────────────────────────────────
    public function estornarReceita(Receita $receita)
    {
        $this->authorize('update', $receita);

        // O ReceitaObserver estorna o saldo do banco automaticamente
        $receita->update(['data_recebimento' => null]);
        $this->restaurarPrevisto($receita);

        return back()->with('success', 'Baixa da receita estornada.');
    }

    // ── Previsto x realizado ──────────────────────────────────────────────────

    /**
     * Baixa com valor diferente do lançado: o valor passa a ser o realizado e
     * o que estava lançado fica guardado em valor_previsto.
     */
    private function valorRealizado(Despesa|Receita $lancamento, float $valor): array
    {
        $dados = ['valor' => $valor];
        if (abs($valor - (float) $lancamento->valor) >= 0.005 && $lancamento->valor_previsto === null) {
            $dados['valor_previsto'] = $lancamento->valor;
        }

        return $dados;
    }

    /**
     * Estorno devolve o lançamento ao valor previsto. Roda depois de limpar a
     * data da baixa, para o observer estornar o saldo pelo valor que foi pago.
     */
    private function restaurarPrevisto(Despesa|Receita $lancamento): void
    {
        if ($lancamento->valor_previsto !== null) {
            $lancamento->update(['valor' => $lancamento->valor_previsto, 'valor_previsto' => null]);
        }
    }
}
