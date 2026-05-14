<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvestimentoRequest;
use App\Http\Requests\Api\V1\StoreRendimentoRequest;
use App\Http\Requests\Api\V1\UpdateInvestimentoRequest;
use App\Http\Requests\Api\V1\UpdateRendimentoRequest;
use App\Http\Resources\Api\V1\InvestimentoRendimentoResource;
use App\Http\Resources\Api\V1\InvestimentoResource;
use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InvestimentoApiController extends Controller
{
    /**
     * GET /api/v1/investimentos
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $investimentos = Investimento::with(['banco', 'rendimentos'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderByDesc('data_aporte')
            ->get();

        return InvestimentoResource::collection($investimentos);
    }

    /**
     * GET /api/v1/investimentos/{investimento}
     */
    public function show(Request $request, Investimento $investimento): InvestimentoResource
    {
        $this->ensureOwnership($request, $investimento);
        $investimento->load(['banco', 'rendimentos']);
        return new InvestimentoResource($investimento);
    }

    /**
     * POST /api/v1/investimentos
     */
    public function store(StoreInvestimentoRequest $request): JsonResponse
    {
        $investimento = Investimento::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        $investimento->load(['banco', 'rendimentos']);

        return (new InvestimentoResource($investimento))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/investimentos/{investimento}
     */
    public function update(UpdateInvestimentoRequest $request, Investimento $investimento): InvestimentoResource
    {
        $this->ensureOwnership($request, $investimento);
        $investimento->update($request->validated());
        $investimento->load(['banco', 'rendimentos']);
        return new InvestimentoResource($investimento);
    }

    /**
     * DELETE /api/v1/investimentos/{investimento}
     */
    public function destroy(Request $request, Investimento $investimento): JsonResponse
    {
        $this->ensureOwnership($request, $investimento);

        if (! $request->user()->temPermissao('investimentos', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir investimentos.'], Response::HTTP_FORBIDDEN);
        }

        $investimento->delete();
        return response()->json(['message' => 'Investimento excluído.']);
    }

    /**
     * POST /api/v1/investimentos/{investimento}/rendimentos
     */
    public function storeRendimento(StoreRendimentoRequest $request, Investimento $investimento): JsonResponse
    {
        $this->ensureOwnership($request, $investimento);

        $rendimento = $investimento->rendimentos()->create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
        ]));

        return (new InvestimentoRendimentoResource($rendimento))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT /api/v1/investimentos/{investimento}/rendimentos/{rendimento}
     *
     * Atualiza um ponto de rendimento existente (campos parciais aceitos).
     */
    public function updateRendimento(UpdateRendimentoRequest $request, Investimento $investimento, InvestimentoRendimento $rendimento): InvestimentoRendimentoResource
    {
        $this->ensureOwnership($request, $investimento);

        if ($rendimento->investimento_id !== $investimento->id
            || $rendimento->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $rendimento->update($request->validated());

        return new InvestimentoRendimentoResource($rendimento);
    }

    /**
     * DELETE /api/v1/investimentos/{investimento}/rendimentos/{rendimento}
     */
    public function destroyRendimento(Request $request, Investimento $investimento, InvestimentoRendimento $rendimento): JsonResponse
    {
        $this->ensureOwnership($request, $investimento);

        if (! $request->user()->temPermissao('investimentos', 'editar')) {
            return response()->json(['message' => 'Sem permissão para alterar investimentos.'], Response::HTTP_FORBIDDEN);
        }

        if ($rendimento->investimento_id !== $investimento->id
            || $rendimento->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $rendimento->delete();
        return response()->json(['message' => 'Rendimento excluído.']);
    }

    private function ensureOwnership(Request $request, Investimento $investimento): void
    {
        if ($investimento->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
