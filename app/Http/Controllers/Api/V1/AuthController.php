<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UpdateMeRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
