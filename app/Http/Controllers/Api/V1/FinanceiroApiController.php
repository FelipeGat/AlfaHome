<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Services\Financeiro\FinanceiroService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Números do dinheiro da família para o app — todos do FinanceiroService, os
 * mesmos do site e dos avisos.
 */
class FinanceiroApiController extends Controller
{
    public function __construct(private FinanceiroService $financeiro)
    {
    }

    /** GET /api/v1/financeiro/inicio — saldo, mês, últimas movimentações e próximos pagamentos. */
    public function inicio(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->financeiro->inicio($request->user()->tenant_id)]);
    }

    /** GET /api/v1/financeiro/resumo?mes=YYYY-MM — entrou, saiu, resultado e categorias. */
    public function resumo(Request $request): JsonResponse
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']], ['mes.date_format' => 'Informe o mês no formato AAAA-MM.']);
        $mes = $request->filled('mes') ? Carbon::createFromFormat('!Y-m', $request->query('mes')) : now();

        return response()->json(['data' => $this->financeiro->mes($request->user()->tenant_id, $mes)]);
    }

    /** GET /api/v1/financeiro/contas — contas com o saldo calculado. */
    public function contas(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->financeiro->contas($request->user()->tenant_id)]);
    }

    /** GET /api/v1/financeiro/contas/{banco} — saldo, movimentações recentes e ajustes. */
    public function conta(Request $request, Banco $banco): JsonResponse
    {
        abort_unless($banco->tenant_id === $request->user()->tenant_id, 404);
        $saldo = collect($this->financeiro->contas($banco->tenant_id)['itens'])->firstWhere('id', $banco->id);

        return response()->json(['data' => [
            'id'          => $banco->id,
            'nome'        => $banco->nome,
            'cor'         => $banco->cor,
            'saldo'       => $saldo['saldo'] ?? null,
            'movimentos'  => $this->financeiro->movimentosDaConta($banco),
            'ajustes'     => $banco->ajustes()->withoutGlobalScopes()->latest('id')->limit(5)->get(['data', 'saldo', 'diferenca', 'observacao'])
                ->map(fn ($a) => ['data' => $a->data->format('Y-m-d'), 'saldo' => (float) $a->saldo, 'diferenca' => (float) $a->diferenca, 'observacao' => $a->observacao]),
        ]]);
    }

    /** POST /api/v1/financeiro/contas/{banco}/ajustar — registra o saldo real de hoje. */
    public function ajustar(Request $request, Banco $banco): JsonResponse
    {
        abort_unless($banco->tenant_id === $request->user()->tenant_id, 404);
        abort_unless(in_array($request->user()->role, ['master', 'membro'], true), 403);
        $dados = $request->validate(
            ['saldo' => ['required', 'numeric', 'between:-99999999,99999999']],
            ['saldo.required' => 'Informe o saldo que aparece no banco.', 'saldo.numeric' => 'Informe o saldo em reais, ex.: -189,02.']
        );

        $ajuste = $this->financeiro->ajustar($banco, (float) $dados['saldo'], userId: $request->user()->id, observacao: 'Ajuste pelo app');

        return response()->json(['data' => [
            'saldo'     => (float) $ajuste->saldo,
            'diferenca' => (float) $ajuste->diferenca,
        ], 'message' => 'Saldo ajustado.']);
    }
}
