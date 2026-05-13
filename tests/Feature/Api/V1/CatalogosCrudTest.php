<?php

namespace Tests\Feature\Api\V1;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Fornecedor;
use App\Models\Tenant;
use App\Models\Transferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogosCrudTest extends TestCase
{
    use RefreshDatabase;

    // ─── Categoria ───────────────────────────────────────────────────────

    public function test_categoria_crud_full_lifecycle(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/categorias', [
            'nome' => 'Mercado', 'tipo' => 'DESPESA', 'icone' => 'cart',
        ])->assertCreated()->json('data');

        $id = $created['id'];

        $this->getJson("/api/v1/categorias/{$id}")
            ->assertOk()->assertJsonPath('data.nome', 'Mercado');

        $this->putJson("/api/v1/categorias/{$id}", ['nome' => 'Mercado e Hortifruti'])
            ->assertOk()->assertJsonPath('data.nome', 'Mercado e Hortifruti');

        $this->deleteJson("/api/v1/categorias/{$id}")->assertOk();
    }

    public function test_categoria_delete_returns_409_when_in_use(): void
    {
        $user = User::factory()->create();
        $cat  = Categoria::factory()->despesa()->create(['tenant_id' => $user->tenant_id]);
        Despesa::factory()->create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'categoria_id' => $cat->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/categorias/{$cat->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.despesas', true);
    }

    public function test_categoria_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA   = User::factory()->create(['tenant_id' => $tenantA->id]);
        $cat     = Categoria::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($userA);
        $this->getJson("/api/v1/categorias/{$cat->id}")->assertNotFound();
        $this->putJson("/api/v1/categorias/{$cat->id}", ['nome' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/categorias/{$cat->id}")->assertNotFound();
    }

    // ─── Banco ───────────────────────────────────────────────────────────

    public function test_banco_create_and_delete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/bancos', [
            'nome'               => 'Carteira',
            'eh_dinheiro'        => true,
            'tem_conta_corrente' => false,
            'tem_poupanca'       => false,
            'tem_cartao_credito' => false,
            'saldo'              => 50.0,
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/bancos/{$id}")->assertOk();
    }

    public function test_banco_delete_returns_409_when_used_in_transferencia(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        Transferencia::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'valor'      => 100,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ]);

        Sanctum::actingAs($user);

        // origem aparece em transferencia → 409
        $this->deleteJson("/api/v1/bancos/{$origem->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.transferencias', true);

        // destino também → 409
        $this->deleteJson("/api/v1/bancos/{$destino->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.transferencias', true);
    }

    // ─── Fornecedor ──────────────────────────────────────────────────────

    public function test_fornecedor_create(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/fornecedores', [
            'nome' => 'Padaria', 'grupo' => 'Mercados',
        ])->assertCreated()->assertJsonPath('data.nome', 'Padaria');
    }

    // ─── Familiar ────────────────────────────────────────────────────────

    public function test_familiar_create_and_returns_extra_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/familiares', [
            'nome' => 'Maria', 'salario' => 3000.0,
        ])->assertCreated()
          ->assertJsonPath('data.nome', 'Maria')
          ->assertJsonPath('data.salario', 3000.0);
    }
}
