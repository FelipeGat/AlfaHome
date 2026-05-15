<?php

namespace Tests\Feature\Api\V1;

use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvestimentoIndexMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_meta_with_portfolio_totals(): void
    {
        $user = User::factory()->create();

        // Inv1: aporte 1000, último rendimento 1100 → ganho +100
        $inv1 = Investimento::create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'nome_ativo' => 'CDB A', 'tipo_investimento' => 'rendaFixa',
            'data_aporte' => now()->format('Y-m-d'),
            'valor_aportado' => 1000, 'quantidade_cotas' => 0,
        ]);
        InvestimentoRendimento::create([
            'tenant_id' => $user->tenant_id, 'investimento_id' => $inv1->id,
            'data' => now()->format('Y-m-d'), 'valor_atual' => 1100,
        ]);

        // Inv2: aporte 500, sem rendimentos → valor_atual = aporte → ganho 0
        Investimento::create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'nome_ativo' => 'FII B', 'tipo_investimento' => 'rendaVariavel',
            'data_aporte' => now()->format('Y-m-d'),
            'valor_aportado' => 500, 'quantidade_cotas' => 0,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/v1/investimentos')->assertOk();

        $this->assertEquals(1500.0, (float) $resp->json('meta.valor_aportado'));
        $this->assertEquals(1600.0, (float) $resp->json('meta.valor_atual'));
        $this->assertEquals(1600.0, (float) $resp->json('meta.total_valor')); // alias
        $this->assertEquals(100.0,  (float) $resp->json('meta.ganho_total'));
        $this->assertEqualsWithDelta(6.67, (float) $resp->json('meta.ganho_percentual'), 0.01);
        $this->assertEquals(2, $resp->json('meta.count'));
    }

    public function test_empty_portfolio_returns_zeros(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/v1/investimentos')->assertOk();
        $this->assertEquals(0.0, (float) $resp->json('meta.valor_aportado'));
        $this->assertEquals(0.0, (float) $resp->json('meta.valor_atual'));
        $this->assertEquals(0.0, (float) $resp->json('meta.ganho_percentual'));
        $this->assertEquals(0,   $resp->json('meta.count'));
    }
}
