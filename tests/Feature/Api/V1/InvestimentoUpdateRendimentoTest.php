<?php

namespace Tests\Feature\Api\V1;

use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestimentoUpdateRendimentoTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvestimentoWithRendimento(User $user): array
    {
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

        return [$inv, $rend];
    }

    public function test_updates_valor_atual(): void
    {
        $user = User::factory()->create();
        [$inv, $rend] = $this->makeInvestimentoWithRendimento($user);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/investimentos/{$inv->id}/rendimentos/{$rend->id}", [
            'valor_atual' => 1100.50,
        ])->assertOk()
          ->assertJsonPath('data.valor_atual', 1100.50);

        $this->assertEquals(1100.50, (float) $rend->fresh()->valor_atual);
    }

    public function test_partial_update_does_not_touch_other_fields(): void
    {
        $user = User::factory()->create();
        [$inv, $rend] = $this->makeInvestimentoWithRendimento($user);
        $rend->update(['observacoes' => 'Original']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/investimentos/{$inv->id}/rendimentos/{$rend->id}", [
            'valor_atual' => 999.0,
        ])->assertOk();

        $fresh = $rend->fresh();
        $this->assertEquals(999.0, (float) $fresh->valor_atual);
        $this->assertEquals('Original', $fresh->observacoes);
    }

    public function test_requires_edit_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $membro = User::factory()->create([
            'tenant_id'  => $tenant->id,
            'role'       => 'membro',
            'permissoes' => [
                'investimentos' => ['ver' => true, 'criar' => false, 'editar' => false, 'excluir' => false],
            ],
        ]);
        [$inv, $rend] = $this->makeInvestimentoWithRendimento($membro);

        Sanctum::actingAs($membro);

        $this->putJson("/api/v1/investimentos/{$inv->id}/rendimentos/{$rend->id}", [
            'valor_atual' => 1100,
        ])->assertStatus(403);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userA   = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB   = User::factory()->create(['tenant_id' => $tenantB->id]);
        [, $rendB] = $this->makeInvestimentoWithRendimento($userB);
        [$invA] = $this->makeInvestimentoWithRendimento($userA);

        Sanctum::actingAs($userA);

        // Tentando combinar investimento de A com rendimento de B → 404
        $this->putJson("/api/v1/investimentos/{$invA->id}/rendimentos/{$rendB->id}", [
            'valor_atual' => 1,
        ])->assertNotFound();
    }
}
