<?php

namespace Tests\Feature\Api\V1;

use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestimentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_rendimento_requires_edit_permission(): void
    {
        $tenant = Tenant::factory()->create();

        // Usuário membro SEM permissão de editar investimentos
        $membro = User::factory()->create([
            'tenant_id'  => $tenant->id,
            'role'       => 'membro',
            'permissoes' => [
                'investimentos' => ['ver' => true, 'criar' => false, 'editar' => false, 'excluir' => false],
            ],
        ]);

        $inv = Investimento::create([
            'tenant_id'         => $tenant->id,
            'user_id'           => $membro->id,
            'nome_ativo'        => 'CDB Teste',
            'tipo_investimento' => 'rendaFixa',
            'data_aporte'       => now()->format('Y-m-d'),
            'valor_aportado'    => 1000,
            'quantidade_cotas'  => 0,
        ]);

        $rend = InvestimentoRendimento::create([
            'tenant_id'       => $tenant->id,
            'investimento_id' => $inv->id,
            'data'            => now()->format('Y-m-d'),
            'valor_atual'     => 1050,
        ]);

        Sanctum::actingAs($membro);

        $this->deleteJson("/api/v1/investimentos/{$inv->id}/rendimentos/{$rend->id}")
            ->assertStatus(403);

        // Confirma que o rendimento NÃO foi excluído
        $this->assertDatabaseHas('investimento_rendimentos', ['id' => $rend->id]);
    }

    public function test_master_can_destroy_rendimento(): void
    {
        $user = User::factory()->create(); // role=master por padrão (bypassa permissões)

        $inv = Investimento::create([
            'tenant_id'         => $user->tenant_id,
            'user_id'           => $user->id,
            'nome_ativo'        => 'CDB Teste',
            'tipo_investimento' => 'rendaFixa',
            'data_aporte'       => now()->format('Y-m-d'),
            'valor_aportado'    => 1000,
            'quantidade_cotas'  => 0,
        ]);

        $rend = InvestimentoRendimento::create([
            'tenant_id'       => $user->tenant_id,
            'investimento_id' => $inv->id,
            'data'            => now()->format('Y-m-d'),
            'valor_atual'     => 1050,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/investimentos/{$inv->id}/rendimentos/{$rend->id}")
            ->assertOk();

        $this->assertDatabaseMissing('investimento_rendimentos', ['id' => $rend->id]);
    }
}
