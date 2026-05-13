<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BancoResource;
use App\Http\Resources\Api\V1\CategoriaResource;
use App\Http\Resources\Api\V1\FamiliarResource;
use App\Http\Resources\Api\V1\FornecedorResource;
use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Familiar;
use App\Models\Fornecedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only catalog endpoints used to populate dropdowns / chips on mobile.
 * All endpoints scoped to the authenticated user's tenant.
 */
class CatalogosController extends Controller
{
    /**
     * GET /api/v1/categorias
     * Query: tipo (DESPESA | RECEITA, opcional)
     */
    public function categorias(Request $request): AnonymousResourceCollection
    {
        $request->validate(['tipo' => ['nullable', 'in:DESPESA,RECEITA']]);

        $query = Categoria::where('tenant_id', $request->user()->tenant_id);

        if ($tipo = $request->query('tipo')) {
            $query->where('tipo', $tipo);
        }

        return CategoriaResource::collection($query->orderBy('nome')->get());
    }

    /**
     * GET /api/v1/familiares
     */
    public function familiares(Request $request): AnonymousResourceCollection
    {
        return FamiliarResource::collection(
            Familiar::with('userVinculado')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderBy('nome')
                ->get()
        );
    }

    /**
     * GET /api/v1/fornecedores
     */
    public function fornecedores(Request $request): AnonymousResourceCollection
    {
        return FornecedorResource::collection(
            Fornecedor::where('tenant_id', $request->user()->tenant_id)
                ->orderBy('nome')
                ->get()
        );
    }

    /**
     * GET /api/v1/bancos
     */
    public function bancos(Request $request): AnonymousResourceCollection
    {
        return BancoResource::collection(
            Banco::where('tenant_id', $request->user()->tenant_id)
                ->orderBy('nome')
                ->get()
        );
    }
}
