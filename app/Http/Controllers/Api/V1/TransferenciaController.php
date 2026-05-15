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

        $query = Transferencia::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('data', [$inicio, $fim]);

        $bancoId = $request->query('banco_id');
        if ($bancoId) {
            $bancoId = (int) $bancoId;
            $query->where(function ($q) use ($bancoId) {
                $q->where('origem_id', $bancoId)
                  ->orWhere('destino_id', $bancoId);
            });
        }

        // Captura os totais ANTES de paginar — refletem exatamente os filtros.
        // Quando `banco_id` está setado, expomos também `total_entradas` e
        // `total_saidas` daquela conta no período (útil para a tela de extrato).
        $baseClone   = clone $query;
        $totalValor  = (float) $baseClone->sum('valor');

        $totalEntradas = null;
        $totalSaidas   = null;
        if ($bancoId) {
            $totalEntradas = (float) (clone $query)->where('destino_id', $bancoId)->sum('valor');
            $totalSaidas   = (float) (clone $query)->where('origem_id', $bancoId)->sum('valor');
        }

        $perPage = (int) $request->query('per_page', 30);

        $paginator = $query
            ->with(['origem', 'destino'])
            ->orderByDesc('data')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $meta = [
            'periodo'     => ['inicio' => $inicio, 'fim' => $fim],
            'total_valor' => $totalValor,
        ];
        if ($bancoId) {
            $meta['total_entradas'] = $totalEntradas;
            $meta['total_saidas']   = $totalSaidas;
        }

        return TransferenciaResource::collection($paginator)->additional(['meta' => $meta]);
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
