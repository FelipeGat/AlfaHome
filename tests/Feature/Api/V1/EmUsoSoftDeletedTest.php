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
 * Regressão dirigida ao comportamento das FKs reais nas migrations.
 *
 * Regra: usar ->withTrashed() apenas onde a FK é RESTRICT (default).
 * Para FKs com onDelete('set null') ou nullOnDelete (a maioria deste
 * domínio), soft-deleted NÃO bloqueia o delete do pai porque o DB
 * apenas zera a referência sem violar integridade — e seria UX ruim
 * impedir a limpeza de uma categoria que só tem uma despesa antiga
 * descartada.
 *
 * Mapeamento das FKs (ver 2024_01_01_000050/060/070/040, etc.):
 *   - despesas.{categoria_id,forma_pagamento,onde_comprou,quem_comprou} → set null
 *   - receitas.{categoria_id,forma_recebimento,quem_recebeu}            → set null
 *   - investimentos.banco_id                                            → set null
 *   - bancos.titular_id                                                 → set null
 *   - users.familiar_id                                                 → null on delete
 *   - transferencias.{origem_id,destino_id}                             → RESTRICT
 *
 * Único caso que precisa de withTrashed: transferencias → bancos.
 */
class EmUsoSoftDeletedTest extends TestCase
{
    use RefreshDatabase;

    // ─── Caso RESTRICT: transferencias → bancos ────────────────────────────
    //   Transferência ativa bloqueia; a já excluída é removida de vez junto
    //   (senão a FK impediria apagar a conta para sempre).

    public function test_banco_destroy_bloqueia_transferencia_ativa_e_limpa_a_excluida(): void
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

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/bancos/{$origem->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.transferencias', true);

        $transf->delete();

        $this->deleteJson("/api/v1/bancos/{$origem->id}")->assertOk();
        $this->assertNull(Banco::withoutGlobalScopes()->find($origem->id));
        $this->assertNull(Transferencia::withTrashed()->withoutGlobalScopes()->find($transf->id));
        $this->deleteJson("/api/v1/bancos/{$destino->id}")->assertOk();
    }

    // ─── Casos set null: NÃO deve bloquear quando só há soft-deleted ───────
    //   Se só há filhos soft-deletados, o pai pode ser excluído livremente.

    public function test_banco_destroy_allowed_when_only_soft_deleted_despesa_exists(): void
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

        Sanctum::actingAs($user);

        // forma_pagamento é set null → permite excluir
        $this->deleteJson("/api/v1/bancos/{$banco->id}")->assertOk();
    }

    public function test_categoria_destroy_allowed_when_only_soft_deleted_receita_exists(): void
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

        // categoria_id é set null → permite excluir e zerar a ref na receita soft-deletada
        $this->deleteJson("/api/v1/categorias/{$categoria->id}")->assertOk();
    }

    public function test_fornecedor_destroy_allowed_when_only_soft_deleted_despesa_exists(): void
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

        // onde_comprou é set null → permite excluir
        $this->deleteJson("/api/v1/fornecedores/{$fornecedor->id}")->assertOk();
    }

    public function test_familiar_destroy_allowed_when_only_soft_deleted_despesa_exists(): void
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

        // quem_comprou é set null → permite excluir
        $this->deleteJson("/api/v1/familiares/{$familiar->id}")->assertOk();
    }

    // ─── Sanity: casos com filhos ATIVOS continuam bloqueando 409 ──────────

    public function test_categoria_destroy_blocked_when_active_despesa_exists(): void
    {
        $user      = User::factory()->create();
        $categoria = Categoria::factory()->despesa()->create(['tenant_id' => $user->tenant_id]);

        Despesa::create([
            'tenant_id'    => $user->tenant_id,
            'user_id'      => $user->id,
            'valor'        => 50,
            'data_compra'  => now()->format('Y-m-d'),
            'categoria_id' => $categoria->id,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/categorias/{$categoria->id}")
            ->assertStatus(409)
            ->assertJsonPath('em_uso.despesas', true);
    }
}
