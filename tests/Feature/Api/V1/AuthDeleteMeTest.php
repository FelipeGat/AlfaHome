<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthDeleteMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->deleteJson('/api/v1/auth/me', ['current_password' => 'x'])
            ->assertStatus(401);
    }

    public function test_correct_password_deactivates_account(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
            'ativo'    => true,
        ]);
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertOk()
            ->assertJsonPath('message', 'Conta desativada. Para reativar, entre em contato com o administrador.');

        $fresh = $user->fresh();
        $this->assertFalse((bool) $fresh->ativo);
        // Usuário continua existindo (não foi hard-deleted)
        $this->assertNotNull($fresh);
    }

    public function test_revokes_all_tokens_including_current(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
        ]);
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        // Sanity: ambos funcionam
        $this->withHeader('Authorization', "Bearer {$token1}")->getJson('/api/v1/auth/me')->assertOk();

        $this->withHeader('Authorization', "Bearer {$token1}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertOk();

        // Ambos os tokens revogados — qualquer chamada subsequente bate em 401/403
        $r1 = $this->withHeader('Authorization', "Bearer {$token1}")->getJson('/api/v1/auth/me');
        $r2 = $this->withHeader('Authorization', "Bearer {$token2}")->getJson('/api/v1/auth/me');
        $this->assertContains($r1->status(), [401, 403]);
        $this->assertContains($r2->status(), [401, 403]);
    }

    public function test_wrong_password_returns_422_and_keeps_account_active(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Senha@123'),
            'ativo'    => true,
        ]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'ERRADA'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue((bool) $user->fresh()->ativo);
    }

    public function test_missing_password_returns_422(): void
    {
        $user  = User::factory()->create(['password' => Hash::make('Senha@123')]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }
}
