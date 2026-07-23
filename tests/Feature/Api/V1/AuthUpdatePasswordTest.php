<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthUpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson('/api/v1/auth/me/password', [
            'current_password'      => 'a',
            'password'              => 'NovaSenh@123',
            'password_confirmation' => 'NovaSenh@123',
        ])->assertStatus(401);
    }

    public function test_changes_password_with_correct_current(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SenhaAtual@123'),
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/me/password', [
                'current_password'      => 'SenhaAtual@123',
                'password'              => 'NovaSenh@456',
                'password_confirmation' => 'NovaSenh@456',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Senha atualizada com sucesso. Outras sessões foram encerradas.');

        $this->assertTrue(Hash::check('NovaSenh@456', $user->fresh()->password));
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('SenhaAtual@123'),
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/me/password', [
                'current_password'      => 'ERRADA',
                'password'              => 'NovaSenh@456',
                'password_confirmation' => 'NovaSenh@456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        // Senha continua a antiga
        $this->assertTrue(Hash::check('SenhaAtual@123', $user->fresh()->password));
    }

    public function test_password_confirmation_mismatch_returns_422(): void
    {
        $user  = User::factory()->create(['password' => Hash::make('SenhaAtual@123')]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/me/password', [
                'current_password'      => 'SenhaAtual@123',
                'password'              => 'NovaSenh@456',
                'password_confirmation' => 'DIFERENTE',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_weak_password_returns_422(): void
    {
        $user  = User::factory()->create(['password' => Hash::make('SenhaAtual@123')]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/me/password', [
                'current_password'      => 'SenhaAtual@123',
                'password'              => '123',
                'password_confirmation' => '123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_other_tokens_are_revoked_current_one_survives(): void
    {
        $user = User::factory()->create(['password' => Hash::make('SenhaAtual@123')]);

        $tokenOutroDispositivo = $user->createToken('android-velho')->plainTextToken;
        $tokenAtual            = $user->createToken('android-novo')->plainTextToken;

        // Sanity: ambos funcionam antes
        $this->withHeader('Authorization', "Bearer {$tokenOutroDispositivo}")
            ->getJson('/api/v1/auth/me')->assertOk();

        // forgetGuards em cada troca de token: o guard do Sanctum fica cacheado
        // entre requests do mesmo teste — sem limpar, o currentAccessToken da
        // troca de senha seria o do request de sanidade acima (revogaria o
        // token errado) e o token revogado continuaria "funcionando".
        $this->app['auth']->forgetGuards();

        // Troca senha usando o token atual
        $this->withHeader('Authorization', "Bearer {$tokenAtual}")
            ->postJson('/api/v1/auth/me/password', [
                'current_password'      => 'SenhaAtual@123',
                'password'              => 'NovaSenh@456',
                'password_confirmation' => 'NovaSenh@456',
            ])->assertOk();

        // Token atual ainda funciona
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenAtual}")
            ->getJson('/api/v1/auth/me')->assertOk();

        // Token do outro dispositivo foi revogado
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenOutroDispositivo}")
            ->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
