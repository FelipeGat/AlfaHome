<?php

namespace App\Http\Controllers;

use App\Models\Despesa;
use App\Models\Fornecedor;
use Database\Seeders\FornecedoresDefaultSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FornecedorController extends Controller
{
    public function index()
    {
        $fornecedores = Fornecedor::orderBy('grupo')->orderBy('nome')->get()
            ->groupBy(fn($f) => $f->grupo ?: 'Outros');
        return view('fornecedores.index', compact('fornecedores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:150',
            'contato' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        Fornecedor::create([
            'user_id'     => Auth::id(),
            'nome'        => $request->nome,
            'icone'       => $request->icone ?: 'fa-store',
            'grupo'       => $request->grupo ?: null,
            'contato'     => $request->contato,
            'cnpj'        => $request->cnpj,
            'telefone'    => $request->telefone,
            'observacoes' => $request->observacoes,
        ]);

        return back()->with('success', 'Fornecedor cadastrado com sucesso!');
    }

    public function storeRapido(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:150',
        ]);

        $fornecedor = Fornecedor::create([
            'user_id' => Auth::id(),
            'nome'    => $request->nome,
            'icone'   => 'fa-store',
        ]);

        return response()->json(['id' => $fornecedor->id, 'nome' => $fornecedor->nome]);
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        $this->authorize('update', $fornecedor);

        $request->validate([
            'nome' => 'required|string|max:150',
            'contato' => 'nullable|string|max:100',
            'cnpj' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:20',
            'observacoes' => 'nullable|string',
        ]);

        $fornecedor->update($request->only(['nome', 'icone', 'grupo', 'contato', 'cnpj', 'telefone', 'observacoes']));

        return back()->with('success', 'Fornecedor atualizado com sucesso!');
    }

    public function destroy(Fornecedor $fornecedor)
    {
        $this->authorize('delete', $fornecedor);

        // Espelha FornecedorApiController::destroy: avisa quando o
        // fornecedor está em uso ATIVO. FK `despesas.onde_comprou` é
        // set null no DB — soft-deletados não bloqueiam a exclusão.
        $tenantId = Auth::user()->tenant_id;

        $emUso = [
            'despesas' => Despesa::where('tenant_id', $tenantId)
                ->where('onde_comprou', $fornecedor->id)->exists(),
        ];

        if ($emUso['despesas']) {
            return back()->withErrors([
                'fornecedor' => 'Fornecedor em uso e não pode ser excluído. '
                    . 'Remova primeiro as despesas vinculadas.',
            ]);
        }

        $fornecedor->delete();
        return back()->with('success', 'Fornecedor excluído com sucesso!');
    }

    public function importarPadrao()
    {
        $user = Auth::user();
        FornecedoresDefaultSeeder::seedParaTenant($user->tenant_id, $user->id);
        return back()->with('success', 'Fornecedores padrão importados com sucesso!');
    }
}
