<?php

namespace Tests\Feature\Correcoes;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Constitution V: saldo e fatura só mudam por caminho que dispara os
 * observers. A edição/exclusão "esta e as futuras" pela web usava update e
 * delete em massa, deixando saldo de conta e fatura de cartão errados.
 */
class SerieEstaEFuturasWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function banco(array $extra = []): Banco
    {
        return Banco::create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Banco', 'tem_conta_corrente' => true, 'saldo' => 1000] + $extra);
    }

    private function serieDeDespesas(Banco $banco, string $tipo, float $valor): array
    {
        return collect(['2026-08-10', '2026-09-10', '2026-10-10'])->map(fn ($data) => Despesa::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_pagamento' => $banco->id,
            'tipo_pagamento' => $tipo, 'valor' => $valor, 'data_compra' => $data, 'data_pagamento' => null,
            'grupo_recorrencia_id' => 'grupo-1', 'parcelas' => 3, 'recorrente' => true,
        ]))->all();
    }

    public function test_editar_valor_da_serie_no_cartao_atualiza_a_fatura(): void
    {
        $cartao = $this->banco(['tem_cartao_credito' => true, 'limite_cartao' => 5000]);
        $serie  = $this->serieDeDespesas($cartao, 'credito', 100);
        $this->assertEqualsWithDelta(300.0, (float) $cartao->fresh()->saldo_cartao, 0.001);

        // Altera a segunda e as futuras para R$ 150.
        $this->put(route('despesas.update', $serie[1]), [
            'escopo' => 'esta_e_futuras', 'forma_pagamento' => $cartao->id, 'tipo_pagamento' => 'credito',
            'valor' => 150, 'data_compra' => '2026-09-10',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['100.00', '150.00', '150.00'], Despesa::orderBy('data_compra')->pluck('valor')->all());
        $this->assertEqualsWithDelta(400.0, (float) $cartao->fresh()->saldo_cartao, 0.001);
    }

    public function test_marcar_a_serie_como_paga_debita_a_conta(): void
    {
        $conta = $this->banco();
        $serie = $this->serieDeDespesas($conta, 'pix', 100);

        $this->put(route('despesas.update', $serie[1]), [
            'escopo' => 'esta_e_futuras', 'forma_pagamento' => $conta->id, 'tipo_pagamento' => 'pix',
            'valor' => 100, 'data_compra' => '2026-09-10', 'data_pagamento' => '2026-09-10',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(800.0, (float) $conta->fresh()->saldo, 0.001);
    }

    private function serieDeReceitas(Banco $banco, ?string $recebidaEm): array
    {
        return collect(['2026-08-05', '2026-09-05', '2026-10-05'])->map(fn ($data) => Receita::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_recebimento' => $banco->id,
            'valor' => 200, 'data_prevista_recebimento' => $data, 'data_recebimento' => $recebidaEm ? $data : null,
            'grupo_recorrencia_id' => 'grupo-2', 'parcelas' => 3, 'recorrente' => true,
        ]))->all();
    }

    public function test_marcar_a_serie_de_receitas_como_recebida_credita_a_conta(): void
    {
        $conta = $this->banco();
        $serie = $this->serieDeReceitas($conta, null);

        $this->put(route('receitas.update', $serie[1]), [
            'escopo' => 'esta_e_futuras', 'forma_recebimento' => $conta->id, 'valor' => 200,
            'data_prevista_recebimento' => '2026-09-05', 'data_recebimento' => '2026-09-05',
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(1400.0, (float) $conta->fresh()->saldo, 0.001);
    }

    public function test_excluir_a_serie_de_receitas_recebidas_devolve_o_saldo(): void
    {
        $conta = $this->banco();
        $serie = $this->serieDeReceitas($conta, 'sim');
        $this->assertEqualsWithDelta(1600.0, (float) $conta->fresh()->saldo, 0.001);

        $this->delete(route('receitas.destroy', $serie[1]), ['escopo' => 'esta_e_futuras'])
            ->assertSessionHas('success', '2 receita(s) excluída(s)!');

        $this->assertSame(1, Receita::count());
        $this->assertEqualsWithDelta(1200.0, (float) $conta->fresh()->saldo, 0.001);
    }
}
