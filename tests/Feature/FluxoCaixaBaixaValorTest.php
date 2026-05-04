<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FluxoCaixaBaixaValorTest extends TestCase
{
    use RefreshDatabase;

    private function criarConta(User $user, float $saldo = 5000.00): Banco
    {
        return Banco::create([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'nome'               => 'Conta Fluxo',
            'tem_conta_corrente' => true,
            'saldo'              => $saldo,
        ]);
    }

    public function test_baixar_receita_com_valor_diferente_atualiza_valor_e_saldo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco   = $this->criarConta($user, 1000.00);
        $receita = Receita::create([
            'tenant_id'                  => $user->tenant_id,
            'user_id'                    => $user->id,
            'forma_recebimento'          => $banco->id,
            'valor'                      => 1000.00,
            'data_prevista_recebimento'  => '2026-04-26',
            'data_recebimento'           => null,
        ]);

        $resp = $this->post(route('fluxo-caixa.baixar-receita', $receita), [
            'data_recebimento' => '2026-04-26',
            'valor'            => 1500.00, // recebeu 500 a mais
        ]);

        $resp->assertSessionHasNoErrors();

        $receita->refresh();
        $this->assertEqualsWithDelta(1500.00, (float) $receita->valor, 0.01);
        $this->assertSame('2026-04-26', $receita->data_recebimento->format('Y-m-d'));
        // Saldo creditado pelo novo valor
        $this->assertEqualsWithDelta(2500.00, (float) $banco->fresh()->saldo, 0.01);
    }

    public function test_baixar_despesa_com_valor_menor_atualiza_valor_e_saldo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco   = $this->criarConta($user, 2000.00);
        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'valor'           => 800.00,
            'data_compra'     => '2026-04-26',
            'data_pagamento'  => null,
        ]);

        $resp = $this->post(route('fluxo-caixa.baixar-despesa', $despesa), [
            'data_pagamento' => '2026-04-26',
            'valor'          => 600.00, // pagou 200 a menos
        ]);

        $resp->assertSessionHasNoErrors();

        $despesa->refresh();
        $this->assertEqualsWithDelta(600.00, (float) $despesa->valor, 0.01);
        $this->assertSame('2026-04-26', $despesa->data_pagamento->format('Y-m-d'));
        // Saldo debitado pelo novo valor
        $this->assertEqualsWithDelta(1400.00, (float) $banco->fresh()->saldo, 0.01);
    }

    public function test_baixar_despesa_sem_valor_mantem_valor_original(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco   = $this->criarConta($user, 2000.00);
        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'valor'           => 250.00,
            'data_compra'     => '2026-04-26',
            'data_pagamento'  => null,
        ]);

        $this->post(route('fluxo-caixa.baixar-despesa', $despesa), [
            'data_pagamento' => '2026-04-26',
        ])->assertSessionHasNoErrors();

        $despesa->refresh();
        $this->assertEqualsWithDelta(250.00, (float) $despesa->valor, 0.01);
        $this->assertEqualsWithDelta(1750.00, (float) $banco->fresh()->saldo, 0.01);
    }
}
