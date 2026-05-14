<?php

namespace Tests\Feature\Api\V1;

use App\Models\Revenda;
use App\Models\Tenant;
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

    // ─── Proteção contra órfão de administração ───────────────────────────

    public function test_super_admin_cannot_self_deactivate(): void
    {
        $user = User::factory()->create([
            'role'      => 'super_admin',
            'tenant_id' => null,
            'password'  => Hash::make('Senha@123'),
        ]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'last_active_admin');

        $this->assertTrue((bool) $user->fresh()->ativo);
    }

    public function test_lone_master_cannot_self_deactivate(): void
    {
        $tenant = Tenant::factory()->create();
        $master = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => true,
            'password'  => Hash::make('Senha@123'),
        ]);
        $token = $master->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'last_active_admin');

        $this->assertTrue((bool) $master->fresh()->ativo);
    }

    public function test_master_can_self_deactivate_when_another_master_exists(): void
    {
        $tenant = Tenant::factory()->create();
        $master1 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => true,
            'password'  => Hash::make('Senha@123'),
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => true,
        ]);

        $token = $master1->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertOk();

        $this->assertFalse((bool) $master1->fresh()->ativo);
    }

    public function test_inactive_master_does_not_count_as_remaining_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $masterAtivo = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => true,
            'password'  => Hash::make('Senha@123'),
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'role'      => 'master',
            'ativo'     => false, // já desativado — não conta
        ]);

        $token = $masterAtivo->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'last_active_admin');
    }

    public function test_membro_can_always_self_deactivate(): void
    {
        $user = User::factory()->create([
            'role'     => 'membro',
            'ativo'    => true,
            'password' => Hash::make('Senha@123'),
        ]);
        $token = $user->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertOk();

        $this->assertFalse((bool) $user->fresh()->ativo);
    }

    public function test_lone_admin_revenda_cannot_self_deactivate(): void
    {
        $revenda = Revenda::create([
            'nome'   => 'Revenda Teste',
            'email'  => 'rev@test.com',
            'status' => 'ativo',
        ]);
        $admin = User::factory()->create([
            'role'       => 'admin_revenda',
            'revenda_id' => $revenda->id,
            'tenant_id'  => null,
            'ativo'      => true,
            'password'   => Hash::make('Senha@123'),
        ]);
        $token = $admin->createToken('device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/v1/auth/me', ['current_password' => 'Senha@123'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'last_active_admin');
    }
}
