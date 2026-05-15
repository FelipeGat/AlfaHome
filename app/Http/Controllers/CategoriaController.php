<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Receita;
use Database\Seeders\CategoriasDefaultSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::orderBy('tipo')->orderBy('nome')->get();
        return view('categorias.index', compact('categorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:RECEITA,DESPESA',
            'icone' => 'nullable|string|max:50',
        ]);

        Categoria::create([
            'user_id' => Auth::id(),
            'nome' => $request->nome,
            'tipo' => $request->tipo,
            'icone' => $request->icone ?: 'fa-tag',
        ]);

        return back()->with('success', 'Categoria criada com sucesso!');
    }

    public function storeRapido(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:RECEITA,DESPESA',
        ]);

        $categoria = Categoria::create([
            'user_id' => Auth::id(),
            'nome' => $request->nome,
            'tipo' => $request->tipo,
            'icone' => 'fa-tag',
        ]);

        return response()->json(['id' => $categoria->id, 'nome' => $categoria->nome]);
    }

    public function update(Request $request, Categoria $categoria)
    {
        $this->authorize('update', $categoria);

        $request->validate([
            'nome' => 'required|string|max:100',
            'tipo' => 'required|in:RECEITA,DESPESA',
            'icone' => 'nullable|string|max:50',
        ]);

        $categoria->update([
            'nome' => $request->nome,
            'tipo' => $request->tipo,
            'icone' => $request->icone ?: 'fa-tag',
        ]);

        return back()->with('success', 'Categoria atualizada com sucesso!');
    }

    public function destroy(Categoria $categoria)
    {
        $this->authorize('delete', $categoria);

        // Espelha CategoriaApiController::destroy: avisa o usuário quando
        // a categoria está em uso ATIVO. FKs `despesas.categoria_id` e
        // `receitas.categoria_id` são set null no DB — registros antigos
        // soft-deletados não bloqueiam a exclusão.
        $tenantId = Auth::user()->tenant_id;

        $emUso = [
            'despesas' => Despesa::where('tenant_id', $tenantId)
                ->where('categoria_id', $categoria->id)->exists(),
            'receitas' => Receita::where('tenant_id', $tenantId)
                ->where('categoria_id', $categoria->id)->exists(),
        ];

        $bloqueios = array_keys(array_filter($emUso));
        if (! empty($bloqueios)) {
            return back()->withErrors([
                'categoria' => 'Categoria em uso e não pode ser excluída. '
                    . 'Remova primeiro: ' . implode(', ', $bloqueios) . '.',
            ]);
        }

        $categoria->delete();
        return back()->with('success', 'Categoria excluída com sucesso!');
    }

    public function importarPadrao()
    {
        $user = Auth::user();
        CategoriasDefaultSeeder::seedParaTenant($user->tenant_id, $user->id);
        return back()->with('success', 'Categorias padrão importadas com sucesso!');
    }
}
