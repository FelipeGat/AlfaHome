<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cobre o observer de saldo_cartao em DespesaObserver.
 *
 * Regra: uma despesa contribui para `bancos.saldo_cartao` quando
 *   tipo_pagamento === 'credito'
 *   AND forma_pagamento aponta para um banco
 *   AND data_pagamento é null (ainda na fatura aberta)
 */
class DespesaSaldoCartaoTest extends TestCase
{
    use RefreshDatabase;

    private function makeCartao(User $user, float $saldoInicial = 0): Banco
    {
        return Banco::factory()
            ->cartao()
            ->create([
                'tenant_id'    => $user->tenant_id,
                'user_id'      => $user->id,
                'saldo_cartao' => $saldoInicial,
            ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_create_credito_unpaid_increments_saldo_cartao(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 150.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);

        $this->assertEquals(150.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_create_credito_already_paid_does_not_increment(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        // Já paga no momento da criação (não está na fatura aberta)
        Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 150.0,
            'data_compra'     => now()->format('Y-m-d'),
            'data_pagamento'  => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);

        $this->assertEquals(0.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_marking_credito_as_paid_decrements_saldo_cartao(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 200.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        // Após create: 200
        $this->assertEquals(200.0, (float) $cartao->fresh()->saldo_cartao);

        // Marca como paga → sai da fatura
        $despesa->update(['data_pagamento' => now()->format('Y-m-d')]);

        $this->assertEquals(0.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_unmarking_credito_payment_increments_back(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 80.0,
            'data_compra'     => now()->format('Y-m-d'),
            'data_pagamento'  => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        $this->assertEquals(0.0, (float) $cartao->fresh()->saldo_cartao);

        // Desfaz pagamento → volta para a fatura
        $despesa->update(['data_pagamento' => null]);

        $this->assertEquals(80.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_changing_valor_adjusts_fatura(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 100.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        $this->assertEquals(100.0, (float) $cartao->fresh()->saldo_cartao);

        $despesa->update(['valor' => 175.0]);

        $this->assertEquals(175.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_changing_card_moves_balance(): void
    {
        $user    = $this->makeUser();
        $cartaoA = $this->makeCartao($user);
        $cartaoB = $this->makeCartao($user);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 120.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartaoA->id,
        ]);
        $this->assertEquals(120.0, (float) $cartaoA->fresh()->saldo_cartao);
        $this->assertEquals(0.0,   (float) $cartaoB->fresh()->saldo_cartao);

        $despesa->update(['forma_pagamento' => $cartaoB->id]);

        $this->assertEquals(0.0,   (float) $cartaoA->fresh()->saldo_cartao);
        $this->assertEquals(120.0, (float) $cartaoB->fresh()->saldo_cartao);
    }

    public function test_changing_type_from_credito_to_pix_removes_from_fatura(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user, saldoInicial: 0);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 90.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        $this->assertEquals(90.0, (float) $cartao->fresh()->saldo_cartao);

        // Trocou pra pix → não mais fatura
        $despesa->update(['tipo_pagamento' => 'pix']);

        $this->assertEquals(0.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_delete_credito_unpaid_decrements(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 60.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        $this->assertEquals(60.0, (float) $cartao->fresh()->saldo_cartao);

        $despesa->delete();

        $this->assertEquals(0.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_delete_credito_already_paid_does_not_touch_fatura(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user, saldoInicial: 100); // outras despesas
        $this->actingAs($user);

        $despesa = Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 50.0,
            'data_compra'     => now()->format('Y-m-d'),
            'data_pagamento'  => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
        ]);
        // Já paga, não entrou na fatura
        $this->assertEquals(100.0, (float) $cartao->fresh()->saldo_cartao);

        $despesa->delete();

        // Continua 100 — a deleção não tira nada da fatura
        $this->assertEquals(100.0, (float) $cartao->fresh()->saldo_cartao);
    }

    public function test_non_credito_does_not_touch_saldo_cartao(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user, saldoInicial: 500);
        $this->actingAs($user);

        Despesa::create([
            'tenant_id'       => $user->tenant_id,
            'user_id'         => $user->id,
            'valor'           => 70.0,
            'data_compra'     => now()->format('Y-m-d'),
            'tipo_pagamento'  => 'pix',
            'forma_pagamento' => $cartao->id,
        ]);

        $this->assertEquals(500.0, (float) $cartao->fresh()->saldo_cartao);
    }

    /**
     * Regressão: PUT /despesas/{id} com escopo=esta_e_futuras antes usava
     * Eloquent::query()->update() em massa, que pula model events. Em
     * recorrências de crédito isso fazia saldo_cartao drift do total real
     * de fatura aberta. O fix itera os modelos e chama ->update() em cada.
     */
    public function test_update_esta_e_futuras_keeps_saldo_cartao_in_sync(): void
    {
        $user   = $this->makeUser();
        $cartao = $this->makeCartao($user);
        $grupo  = (string) Str::uuid();

        Sanctum::actingAs($user);

        // 3 parcelas mensais de crédito, todas em aberto (não pagas)
        $parcelas = collect(['2026-05-15', '2026-06-15', '2026-07-15'])
            ->map(fn ($data) => Despesa::create([
                'tenant_id'             => $user->tenant_id,
                'user_id'               => $user->id,
                'valor'                 => 100.0,
                'data_compra'           => $data,
                'tipo_pagamento'        => 'credito',
                'forma_pagamento'       => $cartao->id,
                'grupo_recorrencia_id'  => $grupo,
                'origem'                => 'recorrencia',
                'recorrente'            => true,
                'frequencia'            => 'mensal',
            ]));

        // Após criar as 3, fatura = 300
        $this->assertEquals(300.0, (float) $cartao->fresh()->saldo_cartao);

        // Edição esta_e_futuras na primeira: muda valor de 100 → 250
        $primeira = $parcelas->first();
        $this->putJson("/api/v1/despesas/{$primeira->id}", [
            'valor'           => 250.0,
            'data_compra'     => $primeira->data_compra->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartao->id,
            'escopo'          => 'esta_e_futuras',
        ])->assertOk();

        // Esperado: 3 parcelas × 250 = 750
        $this->assertEquals(750.0, (float) $cartao->fresh()->saldo_cartao);

        // Cada parcela individualmente também precisa refletir
        foreach ($parcelas as $p) {
            $this->assertEquals(250.0, (float) $p->fresh()->valor);
        }
    }

    public function test_update_esta_e_futuras_changing_card_moves_balance_for_all(): void
    {
        $user    = $this->makeUser();
        $cartaoA = $this->makeCartao($user);
        $cartaoB = $this->makeCartao($user);
        $grupo   = (string) Str::uuid();

        Sanctum::actingAs($user);

        $parcelas = collect(['2026-05-15', '2026-06-15'])
            ->map(fn ($data) => Despesa::create([
                'tenant_id'            => $user->tenant_id,
                'user_id'              => $user->id,
                'valor'                => 80.0,
                'data_compra'          => $data,
                'tipo_pagamento'       => 'credito',
                'forma_pagamento'      => $cartaoA->id,
                'grupo_recorrencia_id' => $grupo,
                'origem'               => 'recorrencia',
                'recorrente'           => true,
                'frequencia'           => 'mensal',
            ]));

        $this->assertEquals(160.0, (float) $cartaoA->fresh()->saldo_cartao);
        $this->assertEquals(0.0,   (float) $cartaoB->fresh()->saldo_cartao);

        // Move todo o grupo para o cartaoB
        $primeira = $parcelas->first();
        $this->putJson("/api/v1/despesas/{$primeira->id}", [
            'valor'           => 80.0,
            'data_compra'     => $primeira->data_compra->format('Y-m-d'),
            'tipo_pagamento'  => 'credito',
            'forma_pagamento' => $cartaoB->id,
            'escopo'          => 'esta_e_futuras',
        ])->assertOk();

        $this->assertEquals(0.0,   (float) $cartaoA->fresh()->saldo_cartao);
        $this->assertEquals(160.0, (float) $cartaoB->fresh()->saldo_cartao);
    }
}
