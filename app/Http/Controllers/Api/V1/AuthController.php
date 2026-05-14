<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteMeRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UpdateMeRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\UploadFotoRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Body: { email, password, device_name? }
     * Returns: { token, user }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $user->ativo) {
            throw ValidationException::withMessages([
                'email' => ['Usuário inativo. Contate o administrador.'],
            ]);
        }

        if ($user->tenant && ! $user->tenant->ativo) {
            throw ValidationException::withMessages([
                'email' => ['Tenant inativo. Contate o administrador.'],
            ]);
        }

        $deviceName = $request->input('device_name') ?: 'mobile-app';

        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * PUT /api/v1/auth/me
     *
     * Atualiza nome e/ou email do usuário logado.
     * Quando o email muda, zera email_verified_at (espelha ProfileController web).
     */
    public function updateMe(UpdateMeRequest $request): UserResource
    {
        $user = $request->user();

        $user->fill($request->safe()->only(['name', 'email']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return new UserResource($user);
    }

    /**
     * POST /api/v1/auth/me/password
     *
     * Troca a senha do usuário logado, exigindo a senha atual.
     * Após sucesso, revoga todos os outros tokens (mantém o atual ativo) —
     * outros dispositivos são forçados a re-autenticar.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->validated()['password']),
        ])->save();

        // Revoga todos os outros tokens, mantém apenas o usado nesta requisição
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Senha atualizada com sucesso. Outras sessões foram encerradas.',
        ]);
    }

    /**
     * POST /api/v1/auth/me/foto
     *
     * Upload de foto de perfil. Multipart/form-data com o campo `foto`.
     * Substitui a foto anterior (se houver) deletando do storage public.
     *
     * Ordem importante: persiste o novo arquivo PRIMEIRO, depois atualiza
     * o DB, e só ao final apaga o arquivo antigo. Se a escrita falhar
     * (disco cheio, permissão, erro transiente), a foto antiga permanece
     * intacta — evita perda de dados em rollback parcial.
     */
    public function uploadFoto(UploadFotoRequest $request): UserResource
    {
        $user = $request->user();
        $oldFotoPath = $user->foto;

        // 1) Persiste o arquivo novo. Se isso falhar, lança e nada mais é tocado.
        $newFotoPath = $request->file('foto')->store('usuarios', 'public');

        // 2) Atualiza o DB com o novo path.
        $user->foto = $newFotoPath;
        $user->save();

        // 3) Só depois do save bem-sucedido, remove o arquivo antigo.
        //    Falha aqui é log-only — o usuário fica com a foto nova correta;
        //    no pior cenário sobra um arquivo órfão no storage.
        if ($oldFotoPath && $oldFotoPath !== $newFotoPath) {
            try {
                Storage::disk('public')->delete($oldFotoPath);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return new UserResource($user);
    }

    /**
     * DELETE /api/v1/auth/me/foto
     *
     * Remove a foto de perfil do usuário.
     */
    public function deleteFoto(Request $request): UserResource
    {
        $user = $request->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->foto = null;
            $user->save();
        }

        return new UserResource($user);
    }

    /**
     * DELETE /api/v1/auth/me
     *
     * Desativa a conta do usuário logado. Exige a senha atual como confirmação.
     *
     * Comportamento (multi-tenant safe):
     *   - Marca `ativo = false` no usuário (NÃO faz hard delete — preserva
     *     histórico financeiro para auditoria e reativação pelo admin).
     *   - Revoga TODOS os tokens (forçando logout em todos os dispositivos).
     *   - Próxima requisição com qualquer token será bloqueada pelo middleware
     *     tenant.ativo.api retornando 403 code:tenant_inactive.
     *
     * Proteção contra órfão de administração:
     *   - super_admin não pode se auto-desativar (não há fluxo in-app para
     *     reativá-lo; precisa ser feito direto no banco / outro super_admin).
     *   - master só pode se desativar se houver OUTRO master ativo no mesmo
     *     tenant (MembroController::update rejeita role='master', então um
     *     master sozinho ficaria sem caminho de recuperação).
     *   - admin_revenda só pode se desativar se houver OUTRO admin_revenda
     *     ativo na mesma revenda.
     *   - membro pode sempre (master pode reativá-lo via gestão de membros).
     */
    public function deleteMe(DeleteMeRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($blockReason = $this->blockSelfDeactivationReason($user)) {
            return response()->json([
                'message' => $blockReason,
                'code'    => 'last_active_admin',
            ], 403);
        }

        $user->update(['ativo' => false]);

        // Revoga todos os tokens — incluindo o atual
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Conta desativada. Para reativar, entre em contato com o administrador.',
        ]);
    }

    /**
     * Retorna a razão para bloquear self-deactivation, ou null se permitido.
     *
     * Regras: ver docblock de deleteMe().
     */
    private function blockSelfDeactivationReason(User $user): ?string
    {
        if ($user->role === 'super_admin') {
            return 'Super administradores não podem se auto-desativar pelo app. '
                . 'Contate outro super administrador.';
        }

        if ($user->role === 'master') {
            $outrosMasters = User::where('tenant_id', $user->tenant_id)
                ->where('role', 'master')
                ->where('ativo', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $outrosMasters) {
                return 'Você é o único administrador (master) ativo deste tenant. '
                    . 'Crie outro master antes de desativar sua conta ou contate o suporte.';
            }
        }

        if ($user->role === 'admin_revenda') {
            $outrosAdmins = User::where('revenda_id', $user->revenda_id)
                ->where('role', 'admin_revenda')
                ->where('ativo', true)
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $outrosAdmins) {
                return 'Você é o único administrador ativo desta revenda. '
                    . 'Crie outro admin antes de desativar sua conta ou contate o suporte.';
            }
        }

        return null;
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Revokes the currently used personal access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    /**
     * POST /api/v1/auth/logout-all
     *
     * Revokes ALL tokens of the user (every device signs out).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Todas as sessões foram encerradas.']);
    }
}
