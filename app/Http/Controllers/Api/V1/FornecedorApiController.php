<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFornecedorRequest;
use App\Http\Requests\Api\V1\UpdateFornecedorRequest;
use App\Http\Resources\Api\V1\FornecedorResource;
use App\Models\Despesa;
use App\Models\Fornecedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FornecedorApiController extends Controller
{
    public function show(Request $request, Fornecedor $fornecedor): FornecedorResource
    {
        $this->ensureOwnership($request, $fornecedor);
        return new FornecedorResource($fornecedor);
    }

    public function store(StoreFornecedorRequest $request): JsonResponse
    {
        $fornecedor = Fornecedor::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new FornecedorResource($fornecedor))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateFornecedorRequest $request, Fornecedor $fornecedor): FornecedorResource
    {
        $this->ensureOwnership($request, $fornecedor);
        $fornecedor->update($request->validated());
        return new FornecedorResource($fornecedor);
    }

    public function destroy(Request $request, Fornecedor $fornecedor): JsonResponse
    {
        $this->ensureOwnership($request, $fornecedor);

        if (! $request->user()->temPermissao('fornecedores', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir fornecedores.'], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $request->user()->tenant_id;

        // FK despesas.onde_comprou é onDelete('set null') — sem FK
        // violation. Checagem sem withTrashed apenas reflete uso ativo.
        $emUso = [
            'despesas' => Despesa::where('tenant_id', $tenantId)
                ->where('onde_comprou', $fornecedor->id)->exists(),
        ];

        if (in_array(true, $emUso, true)) {
            return response()->json([
                'message' => 'Fornecedor em uso e não pode ser excluído.',
                'em_uso'  => $emUso,
            ], Response::HTTP_CONFLICT);
        }

        $fornecedor->delete();
        return response()->json(['message' => 'Fornecedor excluído.']);
    }

    private function ensureOwnership(Request $request, Fornecedor $fornecedor): void
    {
        if ($fornecedor->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
