<?php

namespace Tests\Feature\Api\V1;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Familiar;
use App\Models\Fornecedor;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão: a checagem em_uso usava ->exists() sem withTrashed(), e o
 * default scope do Eloquent filtra deleted_at IS NULL. Registros
 * soft-deleted (Despesa/Receita/Investimento/Transferencia) ainda têm a
 * FK física apontando para o catálogo (banco/categoria/fornecedor/
 * familiar), então um delete subsequente via API caía em FK violation
 * (500) em vez de retornar 409 amigável.
 *
 * Fix: usar ->withTrashed() em todos os checks que consultam tabelas
 * com SoftDeletes.
 */
class EmUsoSoftDeletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_banco_destroy_returns_409_when_only_soft_deleted_transferencia_exists(): void
    {
        $user    = User::factory()->create();
        $origem  = Banco::factory()->create(['tenant_id' => $user->tenant_id]);
        $destino = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        $transf = Transferencia::create([
            'tenant_id'  => $user->tenant_id,
            'user_id'    => $user->id,
            'valor'      => 50,
            'data'       => now()->format('Y-m-d'),
            'origem_id'  => $origem->id,
            'destino_id' => $destino->id,
        ]);
        // Soft delete da transferência
        $transf->delete();

        $this->assertNotNull($transf->fresh()->deleted_at, 'Transferência deveria estar soft-deleted');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/bancos/{$origem->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.transferencias', true);

        $this->deleteJson("/api/v1/bancos/{$destino->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.transferencias', true);
    }

    public function test_banco_destroy_returns_409_when_only_soft_deleted_despesa_exists(): void
    {
        $user  = User::factory()->create();
        $banco = Banco::factory()->create(['tenant_id' => $user->tenant_id]);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 100,
            'data_compra'     => now()->format('Y-m-d'),
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
        ]);
        $despesa->delete();

        $this->assertNotNull($despesa->fresh()->deleted_at);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/bancos/{$banco->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.despesas', true);
    }

    public function test_categoria_destroy_returns_409_when_only_soft_deleted_receita_exists(): void
    {
        $user      = User::factory()->create();
        $categoria = Categoria::factory()->receita()->create(['tenant_id' => $user->tenant_id]);

        $receita = Receita::create([
            'tenant_id'                 => $user->tenant_id,
            'user_id'                   => $user->id,
            'valor'                     => 1000,
            'data_prevista_recebimento' => now()->format('Y-m-d'),
            'categoria_id'              => $categoria->id,
        ]);
        $receita->delete();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/categorias/{$categoria->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.receitas', true);
    }

    public function test_fornecedor_destroy_returns_409_when_only_soft_deleted_despesa_exists(): void
    {
        $user       = User::factory()->create();
        $fornecedor = Fornecedor::factory()->create(['tenant_id' => $user->tenant_id]);

        $despesa = Despesa::create([
            'tenant_id'    => $user->tenant_id,
            'user_id'      => $user->id,
            'valor'        => 50,
            'data_compra'  => now()->format('Y-m-d'),
            'onde_comprou' => $fornecedor->id,
        ]);
        $despesa->delete();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/fornecedores/{$fornecedor->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.despesas', true);
    }

    public function test_familiar_destroy_returns_409_when_only_soft_deleted_despesa_exists(): void
    {
        $user     = User::factory()->create();
        $familiar = Familiar::factory()->create(['tenant_id' => $user->tenant_id]);

        $despesa = Despesa::create([
            'tenant_id'    => $user->tenant_id,
            'user_id'      => $user->id,
            'valor'        => 30,
            'data_compra'  => now()->format('Y-m-d'),
            'quem_comprou' => $familiar->id,
        ]);
        $despesa->delete();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/familiares/{$familiar->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.despesas', true);
    }
}
