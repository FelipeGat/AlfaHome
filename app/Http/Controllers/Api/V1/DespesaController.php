<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDespesaRequest;
use App\Http\Requests\Api\V1\UpdateDespesaRequest;
use App\Http\Resources\Api\V1\DespesaResource;
use App\Models\Despesa;
use App\Services\Lancamentos\LancamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DespesaController extends Controller
{
    /**
     * GET /api/v1/despesas
     *
     * Filtros (todos opcionais):
     *   inicio, fim       — Y-m-d (padrão: mês corrente)
     *   familiar_id, fornecedor_id, banco_id, categoria_id, tipo_pagamento
     *   status            — pago | a_pagar | vencido
     *   pending_only      — 1 = atalho para a_pagar OU vencido, exclui crédito
     *   per_page          — 1..100 (padrão 30)
     *
     * Resposta: paginação Laravel padrão (data + meta + links).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'inicio'         => ['nullable', 'date_format:Y-m-d'],
            'fim'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'familiar_id'    => ['nullable', 'integer'],
            'fornecedor_id'  => ['nullable', 'integer'],
            'banco_id'       => ['nullable', 'integer'],
            'categoria_id'   => ['nullable', 'integer'],
            'tipo_pagamento' => ['nullable', 'string'],
            'status'         => ['nullable', 'in:pago,a_pagar,vencido'],
            'pending_only'   => ['nullable', 'boolean'],
            'per_page'       => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $inicio   = $request->query('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim      = $request->query('fim',    now()->endOfMonth()->format('Y-m-d'));

        $query = Despesa::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_compra', [$inicio, $fim]);

        $this->applyOptionalFilters($query, $request);
        $this->applyStatusFilter($query, $request->query('status'));

        if ($request->boolean('pending_only')) {
            // a_pagar OU vencido, excluindo cartão de crédito (vai pra fatura)
            $query->whereNull('data_pagamento')
                ->where(function ($q) {
                    $q->where('tipo_pagamento', '!=', 'credito')
                      ->orWhereNull('tipo_pagamento');
                });
        }

        // Clona ANTES do paginate para que `total_valor` reflita exatamente
        // a mesma seleção (inclui status + pending_only, que não cabem nos
        // filtros estruturais reaplicados).
        $totalValor = (float) (clone $query)->sum('valor');

        $perPage = (int) $request->query('per_page', 30);

        $paginator = $query
            ->with(['categoria', 'familiar', 'fornecedor', 'banco'])
            ->orderByDesc('data_compra')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return DespesaResource::collection($paginator)->additional([
            'meta' => [
                'periodo'     => ['inicio' => $inicio, 'fim' => $fim],
                'total_valor' => $totalValor,
            ],
        ]);
    }

    /**
     * POST /api/v1/despesas
     *
     * Cria uma despesa única (parcelas=1) ou múltiplas (parcelas>1 ou recorrente).
     * Para cartão de crédito (tipo_pagamento=credito), as datas das parcelas são
     * calculadas a partir do dia de fechamento/vencimento do banco automaticamente.
     */
    public function store(StoreDespesaRequest $request): JsonResponse
    {
        $r     = app(LancamentoService::class)->criarSaida($request->validated(), $request->user());
        $total = $r['total'];

        // Retorna a primeira despesa do grupo (ou a única, se não houver grupo)
        $created = Despesa::with(['categoria', 'familiar', 'fornecedor', 'banco'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit($total)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'message'    => "{$total} despesa(s) criada(s) com sucesso.",
            'count'      => $total,
            'aviso'      => $r['aviso'],
            'data'       => DespesaResource::collection($created)->toArray($request),
        ], 201);
    }

    /**
     * GET /api/v1/despesas/grupo/{grupoId}
     *
     * Lista todas as despesas pertencentes ao mesmo grupo de recorrência,
     * ordenadas por data crescente. Permite ao app mostrar todas as parcelas
     * de uma compra parcelada ou de uma recorrência.
     */
    public function grupo(Request $request, string $grupoId)
    {
        $tenantId = $request->user()->tenant_id;

        $items = Despesa::with(['categoria', 'familiar', 'fornecedor', 'banco'])
            ->where('tenant_id', $tenantId)
            ->where('grupo_recorrencia_id', $grupoId)
            ->orderBy('data_compra')
            ->get();

        return DespesaResource::collection($items);
    }

    /**
     * GET /api/v1/despesas/{despesa}
     */
    public function show(Request $request, Despesa $despesa): DespesaResource
    {
        $this->ensureOwnership($request, $despesa);
        $despesa->load(['categoria', 'familiar', 'fornecedor', 'banco']);
        return new DespesaResource($despesa);
    }

    /**
     * PUT /api/v1/despesas/{despesa}
     *
     * `escopo` (opcional): apenas_esta (padrão) | esta_e_futuras
     */
    public function update(UpdateDespesaRequest $request, Despesa $despesa): DespesaResource
    {
        $this->ensureOwnership($request, $despesa);

        app(LancamentoService::class)->atualizarSaida($despesa, $request->validated());

        $despesa->load(['categoria', 'familiar', 'fornecedor', 'banco']);
        return new DespesaResource($despesa);
    }

    /**
     * DELETE /api/v1/despesas/{despesa}
     *
     * `escopo` (query): apenas_esta (padrão) | esta_e_futuras
     */
    public function destroy(Request $request, Despesa $despesa): JsonResponse
    {
        $this->ensureOwnership($request, $despesa);

        if (! ($request->user()->temPermissao('despesas', 'excluir'))) {
            return response()->json(['message' => 'Sem permissão para excluir despesas.'], 403);
        }

        $count = app(LancamentoService::class)->excluir($despesa, (string) $request->query('escopo', 'apenas_esta'));

        return response()->json([
            'message' => $count > 1 ? "{$count} despesa(s) excluída(s)." : 'Despesa excluída.',
            'count'   => $count,
        ]);
    }

    // ─── helpers ───────────────────────────────────────────────────────────

    private function applyOptionalFilters($query, Request $request): void
    {
        if ($v = $request->query('familiar_id'))    $query->where('quem_comprou', (int) $v);
        if ($v = $request->query('fornecedor_id'))  $query->where('onde_comprou', (int) $v);
        if ($v = $request->query('banco_id'))       $query->where('forma_pagamento', (int) $v);
        if ($v = $request->query('categoria_id'))   $query->where('categoria_id', (int) $v);
        if ($v = $request->query('tipo_pagamento')) $query->where('tipo_pagamento', $v);
    }

    private function applyStatusFilter($query, ?string $status): void
    {
        match ($status) {
            'pago'    => $query->whereNotNull('data_pagamento'),
            'a_pagar' => $query->whereNull('data_pagamento'),
            'vencido' => $query->whereNull('data_pagamento')
                ->where('data_compra', '<', now()->toDateString()),
            default   => null,
        };
    }

    private function ensureOwnership(Request $request, Despesa $despesa): void
    {
        if ($despesa->tenant_id !== $request->user()->tenant_id) {
            abort(404);
        }
    }
}
