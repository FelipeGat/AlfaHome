<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PagarFaturaRequest;
use App\Models\Banco;
use App\Services\Lancamentos\LancamentoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class FaturaApiController extends Controller
{
    /**
     * POST /api/v1/cartoes/{banco}/pagar-fatura
     *
     * Body: vencimento (Y-m-d, qualquer dia do mês da fatura), conta_id, data (padrão hoje).
     * As compras pendentes da fatura ficam pagas; não cria saída a mais.
     */
    public function pagar(PagarFaturaRequest $request, int $banco): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $cartao   = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('tem_cartao_credito', true)->findOrFail($banco);
        $conta    = Banco::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($request->validated('conta_id'));
        $data     = $request->validated('data') ? Carbon::parse($request->validated('data')) : now();

        $count = app(LancamentoService::class)->pagarFatura($cartao, Carbon::parse($request->validated('vencimento')), $conta, $data);
        if ($count === 0) {
            return response()->json(['message' => 'Esta fatura não tem compras pendentes.', 'count' => 0], 422);
        }

        return response()->json(['message' => 'Fatura paga.', 'count' => $count]);
    }
}
