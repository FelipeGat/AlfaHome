<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReceitaRequest;
use App\Http\Requests\Api\V1\UpdateReceitaRequest;
use App\Http\Resources\Api\V1\ReceitaResource;
use App\Models\Receita;
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

        $query = Receita::with(['categoria', 'familiar', 'banco'])
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

        $perPage = (int) $request->query('per_page', 30);

        $paginator = $query
            ->orderByDesc('data_prevista_recebimento')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return ReceitaResource::collection($paginator)->additional([
            'meta' => [
                'periodo' => ['inicio' => $inicio, 'fim' => $fim],
                'total_valor' => (float) Receita::where('tenant_id', $tenantId)
                    ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
                    ->when($request->query('familiar_id'),    fn($q,$v) => $q->where('quem_recebeu', $v))
                    ->when($request->query('banco_id'),       fn($q,$v) => $q->where('forma_recebimento', $v))
                    ->when($request->query('categoria_id'),   fn($q,$v) => $q->where('categoria_id', $v))
                    ->when($request->query('tipo_pagamento'), fn($q,$v) => $q->where('tipo_pagamento', $v))
                    ->sum('valor'),
            ],
        ]);
    }

    /**
     * POST /api/v1/receitas
     */
    public function store(StoreReceitaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $total = Receita::criarComRecorrencia($data, $request->user()->id);

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

        $tenantId = $request->user()->tenant_id;
        $data     = $request->validated();
        $escopo   = $data['escopo'] ?? 'apenas_esta';

        $payload = collect($data)->only([
            'quem_recebeu', 'categoria_id', 'forma_recebimento',
            'tipo_pagamento', 'valor',
            'data_prevista_recebimento', 'data_recebimento',
            'observacoes',
        ])->all();

        if (array_key_exists('data_recebimento', $payload) && $payload['data_recebimento'] === '') {
            $payload['data_recebimento'] = null;
        }

        if ($escopo === 'esta_e_futuras' && $receita->grupo_recorrencia_id) {
            Receita::where('tenant_id', $tenantId)
                ->where('grupo_recorrencia_id', $receita->grupo_recorrencia_id)
                ->where('data_prevista_recebimento', '>=', $receita->data_prevista_recebimento)
                ->update(collect($payload)->except('data_prevista_recebimento')->all());
            $receita->refresh();
        } else {
            $receita->update($payload);
        }

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

        $tenantId = $request->user()->tenant_id;
        $escopo   = $request->query('escopo', 'apenas_esta');

        if ($escopo === 'esta_e_futuras' && $receita->grupo_recorrencia_id) {
            $count = Receita::where('tenant_id', $tenantId)
                ->where('grupo_recorrencia_id', $receita->grupo_recorrencia_id)
                ->where('data_prevista_recebimento', '>=', $receita->data_prevista_recebimento)
                ->get()
                ->each
                ->delete()
                ->count();

            return response()->json(['message' => "{$count} receita(s) excluída(s).", 'count' => $count]);
        }

        $receita->delete();
        return response()->json(['message' => 'Receita excluída.', 'count' => 1]);
    }

    private function ensureOwnership(Request $request, Receita $receita): void
    {
        if ($receita->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}
