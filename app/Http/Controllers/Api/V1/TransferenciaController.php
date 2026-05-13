<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTransferenciaRequest;
use App\Http\Requests\Api\V1\UpdateTransferenciaRequest;
use App\Http\Resources\Api\V1\TransferenciaResource;
use App\Models\Transferencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TransferenciaController extends Controller
{
    /**
     * GET /api/v1/transferencias
     *
     * Filtros opcionais: inicio (Y-m-d), fim (Y-m-d), banco_id.
     * Quando banco_id está setado, retorna transferências onde
     * origem_id == banco_id OU destino_id == banco_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'inicio'   => ['nullable', 'date_format:Y-m-d'],
            'fim'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'banco_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $inicio   = $request->query('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim      = $request->query('fim',    now()->endOfMonth()->format('Y-m-d'));

        $query = Transferencia::with(['origem', 'destino'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('data', [$inicio, $fim]);

        if ($bancoId = $request->query('banco_id')) {
            $query->where(function ($q) use ($bancoId) {
                $q->where('origem_id', (int) $bancoId)
                  ->orWhere('destino_id', (int) $bancoId);
            });
        }

        $perPage = (int) $request->query('per_page', 30);

        $paginator = $query->orderByDesc('data')->orderByDesc('id')->paginate($perPage)->withQueryString();

        return TransferenciaResource::collection($paginator)->additional([
            'meta' => [
                'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            ],
        ]);
    }

    public function show(Request $request, Transferencia $transferencia): TransferenciaResource
    {
        $this->ensureOwnership($request, $transferencia);
        $transferencia->load(['origem', 'destino']);
        return new TransferenciaResource($transferencia);
    }

    public function store(StoreTransferenciaRequest $request): JsonResponse
    {
        $transferencia = Transferencia::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        $transferencia->load(['origem', 'destino']);

        return (new TransferenciaResource($transferencia))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateTransferenciaRequest $request, Transferencia $transferencia): TransferenciaResource
    {
        $this->ensureOwnership($request, $transferencia);
        $transferencia->update($request->validated());
        $transferencia->load(['origem', 'destino']);
        return new TransferenciaResource($transferencia);
    }

    public function destroy(Request $request, Transferencia $transferencia): JsonResponse
    {
        $this->ensureOwnership($request, $transferencia);

        if (! $request->user()->temPermissao('transferencias', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir transferências.'], Response::HTTP_FORBIDDEN);
        }

        $transferencia->delete();
        return response()->json(['message' => 'Transferência excluída.']);
    }

    private function ensureOwnership(Request $request, Transferencia $transferencia): void
    {
        if ($transferencia->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
