<?php

namespace Tests\Feature\Api\V1;

use App\Models\Despesa;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DespesaPendingOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_only_returns_unpaid_excluding_credit(): void
    {
        $user = User::factory()->create();

        $paga = Despesa::factory()->create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'data_compra'    => now()->format('Y-m-d'),
            'data_pagamento' => now()->format('Y-m-d'),
            'tipo_pagamento' => 'pix',
            'valor'          => 100,
        ]);
        $aPagar = Despesa::factory()->create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'data_compra'    => now()->format('Y-m-d'),
            'data_pagamento' => null,
            'tipo_pagamento' => 'pix',
            'valor'          => 200,
        ]);
        $vencida = Despesa::factory()->create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'data_compra'    => now()->format('Y-m-d'),
            'data_pagamento' => null,
            'tipo_pagamento' => 'boleto',
            'valor'          => 50,
        ]);
        $credito = Despesa::factory()->create([
            'tenant_id'      => $user->tenant_id,
            'user_id'        => $user->id,
            'data_compra'    => now()->format('Y-m-d'),
            'data_pagamento' => null,
            'tipo_pagamento' => 'credito',
            'valor'          => 300,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/v1/despesas?pending_only=1')->assertOk();
        $ids  = collect($resp->json('data'))->pluck('id')->all();

        $this->assertContains($aPagar->id, $ids);
        $this->assertContains($vencida->id, $ids);
        $this->assertNotContains($paga->id, $ids);
        $this->assertNotContains($credito->id, $ids);

        // meta.total_valor deve refletir SOMENTE os itens listados (250 = 200 + 50)
        // — não inclui paga (100) nem crédito (300).
        $this->assertEquals(250.0, (float) $resp->json('meta.total_valor'));

        // Sanity: sem pending_only, total inclui tudo do período (650)
        $respSemFiltro = $this->getJson('/api/v1/despesas')->assertOk();
        $this->assertEquals(650.0, (float) $respSemFiltro->json('meta.total_valor'));
    }

    public function test_total_valor_respects_status_filter(): void
    {
        $user = User::factory()->create();

        Despesa::factory()->create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'data_compra' => now()->format('Y-m-d'),
            'data_pagamento' => now()->format('Y-m-d'),
            'valor' => 100,
        ]);
        Despesa::factory()->create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'data_compra' => now()->format('Y-m-d'),
            'data_pagamento' => null,
            'valor' => 50,
        ]);

        Sanctum::actingAs($user);

        // status=pago: total_valor deve trazer apenas R$ 100, não R$ 150
        $resp = $this->getJson('/api/v1/despesas?status=pago')->assertOk();
        $this->assertEquals(100.0, (float) $resp->json('meta.total_valor'));
    }

    public function test_receita_pending_only_returns_unreceived(): void
    {
        $user = User::factory()->create();

        $recebida = Receita::factory()->create([
            'tenant_id'                 => $user->tenant_id,
            'user_id'                   => $user->id,
            'data_prevista_recebimento' => now()->format('Y-m-d'),
            'data_recebimento'          => now()->format('Y-m-d'),
            'valor'                     => 5000,
        ]);
        $aReceber = Receita::factory()->create([
            'tenant_id'                 => $user->tenant_id,
            'user_id'                   => $user->id,
            'data_prevista_recebimento' => now()->format('Y-m-d'),
            'data_recebimento'          => null,
            'valor'                     => 3000,
        ]);

        Sanctum::actingAs($user);

        $resp = $this->getJson('/api/v1/receitas?pending_only=1')->assertOk();
        $ids  = collect($resp->json('data'))->pluck('id')->all();

        $this->assertContains($aReceber->id, $ids);
        $this->assertNotContains($recebida->id, $ids);

        // meta.total_valor deve trazer apenas a pendente (3000), não a recebida
        $this->assertEquals(3000.0, (float) $resp->json('meta.total_valor'));

        // Sanity: sem filtro, total = 8000
        $respSemFiltro = $this->getJson('/api/v1/receitas')->assertOk();
        $this->assertEquals(8000.0, (float) $respSemFiltro->json('meta.total_valor'));
    }
}
