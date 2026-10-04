<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Despesa;
use App\Models\Receita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    /**
     * POST /api/sync/despesa
     * Replays a queued offline despesa from Background Sync.
     */
    public function despesa(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $data = $request->validate([
            'descricao'         => 'required|string|max:255',
            'valor'             => 'required|numeric|min:0.01',
            'data_compra'       => 'required|date',
            'categoria_id'      => ['nullable', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'forma_pagamento'   => ['nullable', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'quem_comprou'      => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'recorrente'        => 'nullable|boolean',
            'observacao'        => 'nullable|string|max:500',
            'client_queue_id'   => 'nullable|string|max:100', // idempotency key from IDB
        ]);

        // Idempotency: prevent double-sync of same queued item
        if (! empty($data['client_queue_id'])
            && Despesa::where('client_queue_id', $data['client_queue_id'])->exists()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        // Pelo model, para os observers manterem saldo e fatura em dia.
        $despesa = new Despesa([
            'tenant_id'       => $tenantId,
            'user_id'         => Auth::id(),
            'valor'           => $data['valor'],
            'data_compra'     => $data['data_compra'],
            'categoria_id'    => $data['categoria_id'] ?? null,
            'forma_pagamento' => $data['forma_pagamento'] ?? null,
            'quem_comprou'    => $data['quem_comprou'] ?? null,
            'recorrente'      => $data['recorrente'] ?? false,
            'observacoes'     => $this->observacoes($data),
            'origem'          => 'offline_sync',
        ]);
        $despesa->client_queue_id = $data['client_queue_id'] ?? null;
        $despesa->save();

        return response()->json(['ok' => true, 'id' => $despesa->id]);
    }

    /**
     * POST /api/sync/receita
     * Replays a queued offline receita from Background Sync.
     */
    public function receita(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $data = $request->validate([
            'descricao'                  => 'required|string|max:255',
            'valor'                      => 'required|numeric|min:0.01',
            'data_prevista_recebimento'  => 'required|date',
            'categoria_id'               => ['nullable', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'quem_recebeu'               => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'recorrente'                 => 'nullable|boolean',
            'observacao'                 => 'nullable|string|max:500',
            'client_queue_id'            => 'nullable|string|max:100',
        ]);

        if (! empty($data['client_queue_id'])
            && Receita::where('client_queue_id', $data['client_queue_id'])->exists()) {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $receita = new Receita([
            'tenant_id'                 => $tenantId,
            'user_id'                   => Auth::id(),
            'valor'                     => $data['valor'],
            'data_prevista_recebimento' => $data['data_prevista_recebimento'],
            'categoria_id'              => $data['categoria_id'] ?? null,
            'quem_recebeu'              => $data['quem_recebeu'] ?? null,
            'recorrente'                => $data['recorrente'] ?? false,
            'observacoes'               => $this->observacoes($data),
        ]);
        $receita->client_queue_id = $data['client_queue_id'] ?? null;
        $receita->save();

        return response()->json(['ok' => true, 'id' => $receita->id]);
    }

    /**
     * A fila offline manda "descricao" e "observacao"; as tabelas só têm
     * "observacoes". Junta as duas no campo que existe.
     */
    private function observacoes(array $data): string
    {
        return empty($data['observacao'])
            ? $data['descricao']
            : $data['descricao'] . ' — ' . $data['observacao'];
    }
}
