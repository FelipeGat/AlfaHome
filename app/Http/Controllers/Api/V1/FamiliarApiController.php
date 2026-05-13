<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFamiliarRequest;
use App\Http\Requests\Api\V1\UpdateFamiliarRequest;
use App\Http\Resources\Api\V1\FamiliarResource;
use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FamiliarApiController extends Controller
{
    public function show(Request $request, Familiar $familiar): FamiliarResource
    {
        $this->ensureOwnership($request, $familiar);
        $familiar->load('userVinculado');
        return new FamiliarResource($familiar);
    }

    public function store(StoreFamiliarRequest $request): JsonResponse
    {
        $familiar = Familiar::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));
        $familiar->load('userVinculado');

        return (new FamiliarResource($familiar))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateFamiliarRequest $request, Familiar $familiar): FamiliarResource
    {
        $this->ensureOwnership($request, $familiar);
        $familiar->update($request->validated());
        $familiar->load('userVinculado');
        return new FamiliarResource($familiar);
    }

    public function destroy(Request $request, Familiar $familiar): JsonResponse
    {
        $this->ensureOwnership($request, $familiar);

        if (! $request->user()->temPermissao('familiares', 'excluir')) {
            return response()->json(['message' => 'Sem permissão para excluir familiares.'], Response::HTTP_FORBIDDEN);
        }

        $tenantId = $request->user()->tenant_id;

        $emUso = [
            'despesas' => Despesa::where('tenant_id', $tenantId)
                ->where('quem_comprou', $familiar->id)->exists(),
            'receitas' => Receita::where('tenant_id', $tenantId)
                ->where('quem_recebeu', $familiar->id)->exists(),
            'bancos'   => Banco::where('tenant_id', $tenantId)
                ->where('titular_id', $familiar->id)->exists(),
            'usuario'  => User::where('familiar_id', $familiar->id)->exists(),
        ];

        if (in_array(true, $emUso, true)) {
            return response()->json([
                'message' => 'Familiar em uso e não pode ser excluído.',
                'em_uso'  => $emUso,
            ], Response::HTTP_CONFLICT);
        }

        $familiar->delete();
        return response()->json(['message' => 'Familiar excluído.']);
    }

    private function ensureOwnership(Request $request, Familiar $familiar): void
    {
        if ($familiar->tenant_id !== $request->user()->tenant_id) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }
}
