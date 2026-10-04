<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Familiar;
use App\Models\Receita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReceitaController extends Controller
{
    /** Tipos de recebimento aceitos */

    public function index(Request $request)
    {
        $tenantId    = Auth::user()->tenant_id;
        $inicio      = $request->get('inicio', now()->startOfMonth()->format('Y-m-d'));
        $fim         = $request->get('fim', now()->endOfMonth()->format('Y-m-d'));
        $familiarId  = $request->get('familiar_id')  ? (int) $request->get('familiar_id')  : null;
        $bancoId     = $request->get('banco_id')     ? (int) $request->get('banco_id')     : null;
        $categoriaId = $request->get('categoria_id') ? (int) $request->get('categoria_id') : null;
        $tipoPag     = $request->get('tipo_pagamento') ?: null;

        $baseQuery = Receita::where('tenant_id', $tenantId)
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
            ->when($familiarId,  fn($q) => $q->where(function ($sub) use ($familiarId) {
                $sub->where('quem_recebeu', $familiarId)->orWhereNull('quem_recebeu');
            }))
            ->when($bancoId,     fn($q) => $q->where('forma_recebimento', $bancoId))
            ->when($categoriaId, fn($q) => $q->where('categoria_id', $categoriaId))
            ->when($tipoPag,     fn($q) => $q->where('tipo_pagamento', $tipoPag));

        $totalValor = (clone $baseQuery)->sum('valor');

        $receitas = Receita::with(['familiar', 'categoria', 'banco'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('data_prevista_recebimento', [$inicio, $fim])
            ->when($familiarId,  fn($q) => $q->where(function ($sub) use ($familiarId) {
                $sub->where('quem_recebeu', $familiarId)->orWhereNull('quem_recebeu');
            }))
            ->when($bancoId,     fn($q) => $q->where('forma_recebimento', $bancoId))
            ->when($categoriaId, fn($q) => $q->where('categoria_id', $categoriaId))
            ->when($tipoPag,     fn($q) => $q->where('tipo_pagamento', $tipoPag))
            ->orderByDesc('data_prevista_recebimento')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $categorias = Categoria::where('tipo', 'RECEITA')->orderBy('nome')->get();
        $familiares = Familiar::orderBy('nome')->get();
        $bancos     = Banco::orderBy('nome')->get();

        return view('receitas.index', compact(
            'receitas', 'totalValor', 'categorias', 'familiares',
            'bancos', 'inicio', 'fim',
            'familiarId', 'bancoId', 'categoriaId', 'tipoPag'
        ));
    }

    public function store(Request $request)
    {
        $userId   = Auth::id();
        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'valor'                     => 'required|numeric|min:0.01',
            'data_prevista_recebimento' => 'required|date',
            'data_recebimento'          => 'nullable|date',
            'categoria_id'              => ['nullable', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'quem_recebeu'              => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'forma_recebimento'         => ['nullable', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'tipo_pagamento'            => ['nullable', Rule::in(Receita::TIPOS_PAGAMENTO)],
            'parcelas'                  => 'nullable|integer|min:0|max:360',
            'frequencia'                => 'nullable|in:diaria,semanal,quinzenal,mensal,trimestral,semestral,anual',
        ]);

        $total = Receita::criarComRecorrencia($request->all(), $userId);

        return back()->with('success', "{$total} receita(s) salva(s) com sucesso!");
    }

    public function update(Request $request, Receita $receita)
    {
        $this->authorize('update', $receita);

        $tenantId = Auth::user()->tenant_id;

        $request->validate([
            'valor'                     => 'required|numeric|min:0.01',
            'data_prevista_recebimento' => 'required|date',
            'data_recebimento'          => 'nullable|date',
            'categoria_id'              => ['nullable', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'quem_recebeu'              => ['nullable', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'forma_recebimento'         => ['nullable', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'tipo_pagamento'            => ['nullable', Rule::in(Receita::TIPOS_PAGAMENTO)],
            'observacoes'               => 'nullable|string|max:2000',
        ]);

        $escopo = $request->get('escopo', 'apenas_esta');

        if ($escopo === 'esta_e_futuras' && $receita->grupo_recorrencia_id) {
            Receita::where('tenant_id', $tenantId)
                ->where('grupo_recorrencia_id', $receita->grupo_recorrencia_id)
                ->where('data_prevista_recebimento', '>=', $receita->data_prevista_recebimento)
                ->get()
                ->each->update([
                    'quem_recebeu'      => $request->quem_recebeu,
                    'categoria_id'      => $request->categoria_id,
                    'forma_recebimento' => $request->forma_recebimento,
                    'tipo_pagamento'    => $request->tipo_pagamento,
                    'valor'             => $request->valor,
                    'data_recebimento'  => $request->data_recebimento ?: null,
                    'observacoes'       => $request->observacoes,
                ]);
        } else {
            $receita->update([
                'quem_recebeu'              => $request->quem_recebeu,
                'categoria_id'              => $request->categoria_id,
                'forma_recebimento'         => $request->forma_recebimento,
                'tipo_pagamento'            => $request->tipo_pagamento,
                'valor'                     => $request->valor,
                'data_prevista_recebimento' => $request->data_prevista_recebimento,
                'data_recebimento'          => $request->data_recebimento ?: null,
                'observacoes'               => $request->observacoes,
            ]);
        }

        return back()->with('success', 'Receita atualizada com sucesso!');
    }

    public function destroy(Request $request, Receita $receita)
    {
        $this->authorize('delete', $receita);

        $tenantId = Auth::user()->tenant_id;
        $escopo   = $request->get('escopo', 'apenas_esta');

        if ($escopo === 'esta_e_futuras' && $receita->grupo_recorrencia_id) {
            $receitas = Receita::where('tenant_id', $tenantId)
                ->where('grupo_recorrencia_id', $receita->grupo_recorrencia_id)
                ->where('data_prevista_recebimento', '>=', $receita->data_prevista_recebimento)
                ->get();

            $count = $receitas->count();
            $receitas->each->delete(); // dispara observer para cada registro

            return back()->with('success', "{$count} receita(s) excluída(s)!");
        }

        $receita->delete();

        return back()->with('success', 'Receita excluída com sucesso!');
    }
}
