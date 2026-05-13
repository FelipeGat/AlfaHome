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
        $this->assertNotContains($paga->id, $ids);
        $this->assertNotContains($credito->id, $ids);
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
    }
}
