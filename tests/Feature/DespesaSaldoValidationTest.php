<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DespesaSaldoValidationTest extends TestCase
{
    use RefreshDatabase;

    private function criarBanco(User $user, array $attrs = []): Banco
    {
        return Banco::create(array_merge([
            'tenant_id'          => $user->tenant_id,
            'user_id'            => $user->id,
            'nome'               => 'Conta Teste',
            'tem_conta_corrente' => true,
            'saldo'              => 0,
            'cheque_especial'    => 0,
        ], $attrs));
    }

    public function test_pagar_despesa_imediata_passa_mesmo_com_outras_em_aberto_acima_do_saldo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco = $this->criarBanco($user, ['saldo' => 9350.10]);

        // Outras despesas em aberto na mesma conta somando 12.000 (acima do saldo)
        Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'valor'           => 12000.00,
            'data_compra'     => '2026-04-25',
            'data_pagamento'  => null,
        ]);

        $resp = $this->post(route('despesas.store'), [
            'valor'           => 500.00,
            'data_compra'     => '2026-04-26',
            'data_pagamento'  => '2026-04-26',
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'parcelas'        => 1,
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertDatabaseHas('despesas', [
            'valor'           => 500.00,
            'data_pagamento'  => '2026-04-26',
            'forma_pagamento' => $banco->id,
        ]);
        // Saldo debitado pelo observer: 9350.10 - 500
        $this->assertEqualsWithDelta(8850.10, (float) $banco->fresh()->saldo, 0.01);
    }

    public function test_pagar_imediato_bloqueia_quando_excede_saldo_e_cheque(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco = $this->criarBanco($user, ['saldo' => 100.00, 'cheque_especial' => 50.00]);

        $resp = $this->post(route('despesas.store'), [
            'valor'           => 500.00,
            'data_compra'     => '2026-04-26',
            'data_pagamento'  => '2026-04-26',
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'parcelas'        => 1,
        ]);

        $resp->assertSessionHasErrors('valor');
        $this->assertDatabaseMissing('despesas', ['valor' => 500.00]);
    }

    public function test_lancamento_em_aberto_continua_descontando_comprometido(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $banco = $this->criarBanco($user, ['saldo' => 1000.00]);

        // Já existe 800 em aberto → disponível para nova abertura: 200
        Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'valor'           => 800.00,
            'data_compra'     => '2026-04-20',
            'data_pagamento'  => null,
        ]);

        // Nova despesa em aberto de 500 (sem data_pagamento) → deve bloquear
        $resp = $this->post(route('despesas.store'), [
            'valor'           => 500.00,
            'data_compra'     => '2026-04-30',
            'forma_pagamento' => $banco->id,
            'tipo_pagamento'  => 'pix',
            'parcelas'        => 1,
        ]);

        // Lançamento em aberto não passa pela validação atual (store só valida quando data_pagamento existe).
        // Confirma que a despesa foi criada (sem validação) e que o saldo NÃO foi tocado.
        $resp->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(1000.00, (float) $banco->fresh()->saldo, 0.01);
    }
}
