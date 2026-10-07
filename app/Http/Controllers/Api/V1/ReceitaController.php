<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReceitaRequest;
use App\Http\Requests\Api\V1\UpdateReceitaRequest;
use App\Http\Resources\Api\V1\ReceitaResource;
use App\Models\Receita;
use App\Services\Lancamentos\LancamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReceitaController extends Controller
{
    /**
     * GET /api/v1/receitas
     *
     * Filtros: inicio, fim, familiar_id, banco_id, categoria_id, tipo_pagamento,
     *          status (recebido | a_receber | vencido),
     *          pending_only (1 = a_receber OU vencido),
     *          per_page (1..100, default 30).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'inicio'         => ['nullable', 'date_format:Y-m-d'],
            'fim'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'familiar_id'    => ['nullable', 'integer'],
            'banco_id'       => ['nullable', 'integer'],
            'categoria_id'   => ['nullable', 'integer'],
            'tipo_pagamento' => ['nullable', 'string'],
            'status'         => ['nullable', 'in:recebido,a_receber,vencido'],
            'pending_only'   => ['nullable', 'boolean'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $inicio   = $request->query('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim      = $request->query('fim',    now()->endOfMonth()->format('Y-m-d'));

        $query = Receita::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim]);

        if ($v = $request->query('familiar_id'))    $query->where('quem_recebeu', (int) $v);
        if ($v = $request->query('banco_id'))       $query->where('forma_recebimento', (int) $v);
        if ($v = $request->query('categoria_id'))   $query->where('categoria_id', (int) $v);
        if ($v = $request->query('tipo_pagamento')) $query->where('tipo_pagamento', $v);

        match ($request->query('status')) {
            'recebido'  => $query->whereNotNull('data_recebimento'),
            'a_receber' => $query->whereNull('data_recebimento'),
            'vencido'   => $query->whereNull('data_recebimento')
                ->where('data_prevista_recebimento', '<', now()->toDateString()),
            default     => null,
        };

        if ($request->boolean('pending_only')) {
            // a_receber OU vencido
            $query->whereNull('data_recebimento');
        }

        // Clona ANTES do paginate para que `total_valor` reflita exatamente
        // a mesma seleção (inclui status + pending_only).
        $totalValor = (float) (clone $query)->sum('valor');

        $perPage = (int) $request->query('per_page', 30);

        $paginator = $query
            ->with(['categoria', 'familiar', 'banco'])
            ->orderByDesc('data_prevista_recebimento')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReceitaResource::collection($paginator)->additional([
            'meta' => [
                'periodo'     => ['inicio' => $inicio, 'fim' => $fim],
                'total_valor' => $totalValor,
            ],
        ]);
    }

    /**
     * POST /api/v1/receitas
     */
    public function store(StoreReceitaRequest $request): JsonResponse
    {
        $total = app(LancamentoService::class)->criarEntrada($request->validated(), $request->user());

        $created = Receita::with(['categoria', 'familiar', 'banco'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit($total)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'message' => "{$total} receita(s) criada(s) com sucesso.",
            'count'   => $total,
            'data'    => ReceitaResource::collection($created)->toArray($request),
        ], 201);
    }

    /**
     * GET /api/v1/receitas/{receita}
     */
    public function show(Request $request, Receita $receita): ReceitaResource
    {
        $this->ensureOwnership($request, $receita);
        $receita->load(['categoria', 'familiar', 'banco']);
        return new ReceitaResource($receita);
    }

    /**
     * PUT /api/v1/receitas/{receita}
     */
    public function update(UpdateReceitaRequest $request, Receita $receita): ReceitaResource
    {
        $this->ensureOwnership($request, $receita);

        app(LancamentoService::class)->atualizarEntrada($receita, $request->validated());

        $receita->load(['categoria', 'familiar', 'banco']);
        return new ReceitaResource($receita);
    }

    /**
     * DELETE /api/v1/receitas/{receita}
     */
    public function destroy(Request $request, Receita $receita): JsonResponse
    {
        $this->ensureOwnership($request, $receita);

        if (! ($request->user()->temPermissao('receitas', 'excluir'))) {
            return response()->json(['message' => 'Sem permissão para excluir receitas.'], 403);
        }

        $count = app(LancamentoService::class)->excluir($receita, (string) $request->query('escopo', 'apenas_esta'));

        return response()->json([
            'message' => $count > 1 ? "{$count} receita(s) excluída(s)." : 'Receita excluída.',
            'count'   => $count,
        ]);
    }

    private function ensureOwnership(Request $request, Receita $receita): void
    {
        if ($receita->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}
