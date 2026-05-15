<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBancoRequest;
use App\Http\Requests\Api\V1\UpdateBancoRequest;
use App\Http\Resources\Api\V1\BancoResource;
use App\Models\Banco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BancoApiController extends Controller
{
    public function show(Request $request, Banco $banco): BancoResource
    {
        $this->ensureOwnership($request, $banco);
        return new BancoResource($banco);
    }

    public function store(StoreBancoRequest $request): JsonResponse
    {
        $banco = Banco::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new BancoResource($banco))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateBancoRequest $request, Banco $banco): BancoResource
    {
        $this->ensureOwnership($request, $banco);
        $banco->update($request->validated());
        return new BancoResource($banco);
    }

    public function destroy(Request $request, Banco $banco): JsonResponse
    {
        $this->ensureOwnership($request, $banco);

        if (! $request->user()->temPermissao('bancos', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir bancos.'], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $request->user()->tenant_id;

        // withTrashed() é essencial aqui: Despesa, Receita, Investimento
        // e Transferencia usam SoftDeletes. Mesmo após soft-delete, os
        // registros físicos permanecem com a FK apontando para bancos.id.
        // Sem withTrashed() a checagem retornaria false e o $banco->delete()
        // posterior daria FK violation (500) em vez do 409 idiomático.
        $emUso = [
            'despesas'       => \App\Models\Despesa::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('forma_pagamento', $banco->id)->exists(),
            'receitas'       => \App\Models\Receita::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('forma_recebimento', $banco->id)->exists(),
            'investimentos'  => \App\Models\Investimento::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('banco_id', $banco->id)->exists(),
            'transferencias' => \App\Models\Transferencia::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($banco) {
                    $q->where('origem_id', $banco->id)
                      ->orWhere('destino_id', $banco->id);
                })->exists(),
        ];

        if (in_array(true, $emUso, true)) {
            return response()->json([
                'message' => 'Banco em uso e não pode ser excluído.',
                'em_uso'  => $emUso,
            ], Response::HTTP_CONFLICT);
        }

        $banco->delete();
        return response()->json(['message' => 'Banco excluído.']);
    }

    private function ensureOwnership(Request $request, Banco $banco): void
    {
        if ($banco->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
