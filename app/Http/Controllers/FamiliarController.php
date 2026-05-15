<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Receita;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;

class FamiliarController extends Controller
{
    public function index()
    {
        $familiares = Familiar::with('userVinculado')->orderByRaw(
            "EXISTS (SELECT 1 FROM users WHERE users.familiar_id = familiares.id AND users.role = 'master') DESC, nome ASC"
        )->get();
        return view('familiares.index', compact('familiares'));
    }

    public function store(Request $request)
    {
        $rules = [
            'nome'          => 'required|string|max:100',
            'salario'       => 'nullable|numeric|min:0',
            'limite_cartao' => 'nullable|numeric|min:0',
            'limite_cheque' => 'nullable|numeric|min:0',
            'foto'          => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ];

        if ($request->boolean('tem_acesso')) {
            $this->somenteMaster();

            $tenant = Tenant::with('plano')->find(Auth::user()->tenant_id);
            if ($tenant && $tenant->limiteUsuariosAtingido()) {
                return back()->withErrors([
                    'tem_acesso' => 'Limite de usuários do plano atingido. Faça upgrade do plano para adicionar mais membros.',
                ]);
            }

            $rules['email']    = 'required|email|unique:users,email';
            $rules['password'] = ['required', Rules\Password::defaults()];
        }

        $request->validate($rules);

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('familiares', 'public');
        }

        $familiar = Familiar::create([
            'user_id'       => Auth::id(),
            'nome'          => $request->nome,
            'salario'       => $request->salario ?? 0,
            'limite_cartao' => $request->limite_cartao ?? 0,
            'limite_cheque' => $request->limite_cheque ?? 0,
            'foto'          => $fotoPath,
        ]);

        if ($request->boolean('tem_acesso')) {
            User::create([
                'name'        => $request->nome,
                'email'       => $request->email,
                'password'    => Hash::make($request->password),
                'tenant_id'   => Auth::user()->tenant_id,
                'familiar_id' => $familiar->id,
                'role'        => 'membro',
                'permissoes'  => $this->buildPermissoes($request),
                'ativo'       => true,
            ]);
        }

        return back()->with('success', 'Membro adicionado com sucesso!');
    }

    public function update(Request $request, Familiar $familiar)
    {
        $this->authorize('update', $familiar);

        $isMasterFamiliar = $familiar->isMaster();

        $rules = [
            'nome'          => 'required|string|max:100',
            'salario'       => 'nullable|numeric|min:0',
            'limite_cartao' => 'nullable|numeric|min:0',
            'limite_cheque' => 'nullable|numeric|min:0',
            'foto'          => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ];

        $temAcesso    = !$isMasterFamiliar && $request->boolean('tem_acesso');
        $membro       = $familiar->membro;

        if ($temAcesso) {
            $this->somenteMaster();
            $emailRule = $membro
                ? 'required|email|unique:users,email,' . $membro->id
                : 'required|email|unique:users,email';
            $rules['email']    = $emailRule;
            $rules['password'] = ['nullable', Rules\Password::defaults()];
        }

        $request->validate($rules);

        $data = [
            'nome'          => $request->nome,
            'salario'       => $request->salario ?? 0,
            'limite_cartao' => $request->limite_cartao ?? 0,
            'limite_cheque' => $request->limite_cheque ?? 0,
        ];

        if ($request->hasFile('foto')) {
            if ($familiar->foto) {
                Storage::disk('public')->delete($familiar->foto);
            }
            $data['foto'] = $request->file('foto')->store('familiares', 'public');
        }

        $familiar->update($data);

        // Sincronizar nome no user master
        if ($isMasterFamiliar && $familiar->userVinculado) {
            $familiar->userVinculado->update(['name' => $request->nome]);
        }

        if ($temAcesso) {
            $permissoes = $this->buildPermissoes($request);

            if ($membro) {
                // Atualiza conta existente
                $membroData = [
                    'name'       => $request->nome,
                    'email'      => $request->email,
                    'ativo'      => $request->boolean('ativo', true),
                    'permissoes' => $permissoes,
                ];
                if ($request->filled('password')) {
                    $membroData['password'] = Hash::make($request->password);
                }
                $membro->update($membroData);
            } else {
                // Cria nova conta — verificar limite do plano
                $tenant = Tenant::with('plano')->find(Auth::user()->tenant_id);
                if ($tenant && $tenant->limiteUsuariosAtingido()) {
                    return back()->withErrors([
                        'tem_acesso' => 'Limite de usuários do plano atingido. Faça upgrade do plano para adicionar mais membros.',
                    ]);
                }

                User::create([
                    'name'        => $request->nome,
                    'email'       => $request->email,
                    'password'    => Hash::make($request->password),
                    'tenant_id'   => Auth::user()->tenant_id,
                    'familiar_id' => $familiar->id,
                    'role'        => 'membro',
                    'permissoes'  => $permissoes,
                    'ativo'       => true,
                ]);
            }
        } elseif ($membro) {
            // Remove acesso ao sistema se existia
            $this->somenteMaster();
            $membro->delete();
        }

        return back()->with('success', 'Membro atualizado com sucesso!');
    }

    public function destroy(Familiar $familiar)
    {
        $this->authorize('delete', $familiar);

        if ($familiar->isMaster()) {
            return back()->withErrors(['nome' => 'O membro master não pode ser excluído.']);
        }

        // Espelha FamiliarApiController::destroy: avisa o usuário quando
        // o membro está em uso ATIVO. Todas as FKs aqui são set null /
        // null-on-delete — soft-deletados não bloqueiam a exclusão.
        $tenantId = Auth::user()->tenant_id;

        $emUso = [
            'despesas' => Despesa::where('tenant_id', $tenantId)
                ->where('quem_comprou', $familiar->id)->exists(),
            'receitas' => Receita::where('tenant_id', $tenantId)
                ->where('quem_recebeu', $familiar->id)->exists(),
            'bancos'   => Banco::where('tenant_id', $tenantId)
                ->where('titular_id', $familiar->id)->exists(),
        ];

        $bloqueios = array_keys(array_filter($emUso));
        if (! empty($bloqueios)) {
            return back()->withErrors([
                'nome' => 'Membro em uso e não pode ser excluído. '
                    . 'Remova primeiro: ' . implode(', ', $bloqueios) . '.',
            ]);
        }

        if ($familiar->membro) {
            $familiar->membro->delete();
        }

        if ($familiar->foto) {
            Storage::disk('public')->delete($familiar->foto);
        }

        $familiar->delete();
        return back()->with('success', 'Membro excluído com sucesso!');
    }

    private function somenteMaster(): void
    {
        if (Auth::user()->role !== 'master') {
            abort(403);
        }
    }

    private function buildPermissoes(Request $request): array
    {
        $modulos = ['despesas', 'receitas', 'investimentos', 'bancos', 'categorias', 'fornecedores', 'familiares'];
        $acoes   = ['ver', 'criar', 'editar', 'excluir'];

        $permissoes = [];
        foreach ($modulos as $modulo) {
            foreach ($acoes as $acao) {
                $permissoes[$modulo][$acao] = $request->boolean("perm_{$modulo}_{$acao}");
            }
        }

        return $permissoes;
    }
}
