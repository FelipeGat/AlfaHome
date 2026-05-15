<?php

namespace Tests\Feature\Api\V1;

use App\Models\Banco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DespesaCreditoValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_credito_sem_forma_pagamento_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/despesas', [
            'valor'          => 100,
            'data_compra'    => now()->format('Y-m-d'),
            'tipo_pagamento' => 'credito',
            // forma_pagamento omitido propositalmente
        ])->assertStatus(422)
          ->assertJsonValidationErrors('forma_pagamento');
    }

    public function test_credito_com_forma_pagamento_aceita(): void
    {
        $user   = User::factory()->create();
        $cartao = Banco::factory()->cartao()->create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/despesas', [
            'valor'           => 100,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ])->assertCreated();
    }

    public function test_outros_tipos_aceitam_forma_pagamento_nulo(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/despesas', [
            'valor'          => 50,
            'data_compra'    => now()->format('Y-m-d'),
            'tipo_pagamento' => 'pix',
        ])->assertCreated();
    }

    public function test_update_para_credito_sem_forma_pagamento_returns_422(): void
    {
        $user = User::factory()->create();
        $banco = Banco::factory()->create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
        ]);

        $despesa = \App\Models\Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 30,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'pix',
            'forma_pagamento' => $banco->id,
        ]);

        Sanctum::actingAs($user);

        // PUT exige valor + data_compra; vou enviá-los mas tirar forma_pagamento
        // e mudar pra credito → deve falhar
        $this->putJson("/api/v1/despesas/{$despesa->id}", [
            'valor'           => 30,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => null,
        ])->assertStatus(422)
          ->assertJsonValidationErrors('forma_pagamento');
    }
}
