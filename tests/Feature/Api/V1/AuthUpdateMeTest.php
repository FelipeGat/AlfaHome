<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthUpdateMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_returns_401(): void
    {
        $this->putJson('/api/v1/auth/me', ['name' => 'X'])->assertStatus(401);
    }

    public function test_updates_name(): void
    {
        $user = User::factory()->create(['name' => 'Antigo']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Novo Nome');

        $this->assertEquals('Novo Nome', $user->fresh()->name);
    }

    public function test_updates_email_and_resets_verification(): void
    {
        $user = User::factory()->create([
            'email'             => 'antigo@test.com',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['email' => 'novo@test.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'novo@test.com');

        $fresh = $user->fresh();
        $this->assertEquals('novo@test.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_unchanged_email_keeps_verification(): void
    {
        $verifiedAt = now()->subDay();
        $user = User::factory()->create([
            'email'             => 'mesmo@test.com',
            'email_verified_at' => $verifiedAt,
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', [
            'name'  => 'Mudei Só o Nome',
            'email' => 'mesmo@test.com',
        ])->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_duplicate_email_returns_422(): void
    {
        $other = User::factory()->create(['email' => 'ocupado@test.com']);
        $user  = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['email' => 'ocupado@test.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_email_format_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['email' => 'naoEhEmail'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_partial_update_does_not_touch_other_fields(): void
    {
        $user = User::factory()->create([
            'name'  => 'Original',
            'email' => 'original@test.com',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/me', ['name' => 'Apenas Nome'])->assertOk();

        $fresh = $user->fresh();
        $this->assertEquals('Apenas Nome', $fresh->name);
        $this->assertEquals('original@test.com', $fresh->email);
    }
}
