<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCategoriaRequest;
use App\Http\Requests\Api\V1\UpdateCategoriaRequest;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Receita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CategoriaApiController extends Controller
{
    public function show(Request $request, Categoria $categoria): CategoriaResource
    {
        $this->ensureOwnership($request, $categoria);
        return new CategoriaResource($categoria);
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        $categoria = Categoria::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new CategoriaResource($categoria))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): CategoriaResource
    {
        $this->ensureOwnership($request, $categoria);
        $categoria->update($request->validated());
        return new CategoriaResource($categoria);
    }

    public function destroy(Request $request, Categoria $categoria): JsonResponse
    {
        $this->ensureOwnership($request, $categoria);

        if (! $request->user()->temPermissao('categorias', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir categorias.'], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $request->user()->tenant_id;

        // withTrashed(): Despesa e Receita usam SoftDeletes — registros
        // físicos com FK ainda apontam para categoria_id. Sem isso o
        // delete poderia cair em FK violation (500) em vez de 409.
        $emUso = [
            'despesas' => Despesa::withTrashed()
                ->where('tenant_id', $tenantId)->where('categoria_id', $categoria->id)->exists(),
            'receitas' => Receita::withTrashed()
                ->where('tenant_id', $tenantId)->where('categoria_id', $categoria->id)->exists(),
        ];

        if (in_array(true, $emUso, true)) {
            return response()->json([
                'message' => 'Categoria em uso e não pode ser excluída.',
                'em_uso'  => $emUso,
            ], Response::HTTP_CONFLICT);
        }

        $categoria->delete();
        return response()->json(['message' => 'Categoria excluída.']);
    }

    private function ensureOwnership(Request $request, Categoria $categoria): void
    {
        if ($categoria->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
