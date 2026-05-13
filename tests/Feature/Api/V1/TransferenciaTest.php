<?php

namespace Tests\Feature\Api\V1;

use App\Models\Banco;
use App\Models\Tenant;
use App\Models\Transferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_moves_balance_between_accounts(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 1000]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 200]);

        Sanctum::actingAs($user);

        $resp = $this->postJson('/api/v1/transferencias', [
            'valor'      => 250.0,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ]);

        $resp->assertCreated();

        $this->assertEquals(750.0, (float) $origem->refresh()->saldo);
        $this->assertEquals(450.0, (float) $destino->refresh()->saldo);
    }

    public function test_store_rejects_same_origin_and_destination(): void
    {
        $user  = User::factory()->create();
        $banco = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transferencias', [
            'valor'      => 100,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $banco->id,
            'destino_id' => $banco->id,
        ])->assertStatus(422)->assertJsonValidationErrors('origem_id');
    }

    public function test_update_value_adjusts_balances(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 1000]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 0]);

        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/transferencias', [
            'valor'      => 100,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ])->json('data.id');

        // Após criar: origem=900, destino=100
        $this->putJson("/api/v1/transferencias/{$created}", ['valor' => 300])->assertOk();

        // Reverteu (origem=1000, destino=0) e reaplicou (origem=700, destino=300)
        $this->assertEquals(700.0, (float) $origem->refresh()->saldo);
        $this->assertEquals(300.0, (float) $destino->refresh()->saldo);
    }

    public function test_update_with_only_one_side_does_not_trip_validation(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        Sanctum::actingAs($user);

        $id = Transferencia::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'valor'      => 50,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ])->id;

        // Update sem mexer em origem/destino — não deve quebrar
        $this->putJson("/api/v1/transferencias/{$id}", ['observacao' => 'aluguel'])->assertOk();
    }

    public function test_destroy_refunds_balance(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 1000]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id, 'saldo' => 0]);

        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/transferencias', [
            'valor'      => 200,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ])->json('data.id');

        // Após criar: origem=800, destino=200
        $this->deleteJson("/api/v1/transferencias/{$id}")->assertOk();

        // Após delete: estornado para o estado inicial
        $this->assertEquals(1000.0, (float) $origem->refresh()->saldo);
        $this->assertEquals(0.0, (float) $destino->refresh()->saldo);
    }

    public function test_cross_tenant_access_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA   = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB   = User::factory()->create(['tenant_id' => $tenantB->id]);

        $bancoB1 = Banco::factory()->create(['tenant_id' => $tenantB->id]);
        $bancoB2 = Banco::factory()->create(['tenant_id' => $tenantB->id]);

        $t = Transferencia::create([
            'tenant_id'  => $tenantB->id,
            'user_id'    => $userB->id,
            'valor'      => 50,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $bancoB1->id,
            'destino_id' => $bancoB2->id,
        ]);

        Sanctum::actingAs($userA);

        $this->getJson("/api/v1/transferencias/{$t->id}")->assertNotFound();
    }

    public function test_index_filters_by_banco_id(): void
    {
        $user = User::factory()->create();
        $a = Banco::factory()->create(['tenant_id' => $user->tenant_id]);
        $b = Banco::factory()->create(['tenant_id' => $user->tenant_id]);
        $c = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        Transferencia::create(['tenant_id'=>$user->tenant_id,'user_id'=>$user->id,'valor'=>10,'data'=>now()->format('Y-m-d'),'origem_id'=>$a->id,'destino_id'=>$b->id]);
        Transferencia::create(['tenant_id'=>$user->tenant_id,'user_id'=>$user->id,'valor'=>20,'data'=>now()->format('Y-m-d'),'origem_id'=>$b->id,'destino_id'=>$c->id]);
        Transferencia::create(['tenant_id'=>$user->tenant_id,'user_id'=>$user->id,'valor'=>30,'data'=>now()->format('Y-m-d'),'origem_id'=>$a->id,'destino_id'=>$c->id]);

        Sanctum::actingAs($user);

        // Filtrando por banco $b: deve trazer as duas onde $b é origem ou destino
        $resp = $this->getJson("/api/v1/transferencias?banco_id={$b->id}");
        $resp->assertOk();
        $this->assertCount(2, $resp->json('data'));
    }
}
