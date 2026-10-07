<?php

namespace Tests\Feature\Lancamentos;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "+ Lançar", Extrato e Pagar fatura no site, para quem não usa planilha. */
class LancamentoWebTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-07 09:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function conta(string $nome, array $extra = []): Banco
    {
        return Banco::withoutGlobalScopes()->create($extra + ['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => $nome, 'tem_conta_corrente' => true]);
    }

    public function test_inicio_sem_conta_mostra_o_primeiro_passo(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Vamos começar?')->assertSee('Adicionar conta')->assertSee('data-lancar', false);

        $this->conta('Itaú');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Vamos começar?')->assertSee('Itaú');
    }

    public function test_lancar_saida_entrada_e_transferencia(): void
    {
        $itau = $this->conta('Itaú', ['saldo' => 1000]);
        $sicoob = $this->conta('Sicoob');
        $this->travelTo('2026-10-07 12:00:00');

        $this->postJson(route('lancar.saida'), ['valor' => '96,85', 'data_compra' => '2026-10-07', 'data_pagamento' => '2026-10-07', 'forma_pagamento' => $itau->id, 'tipo_pagamento' => 'pix', 'observacoes' => 'Açougue'])
            ->assertOk()->assertJsonPath('message', 'Saída registrada.')->assertSessionHas('success', 'Saída registrada.');
        $this->postJson(route('lancar.entrada'), ['valor' => 7500, 'data_prevista_recebimento' => '2026-10-10', 'forma_recebimento' => $itau->id, 'observacoes' => 'Salário'])
            ->assertOk()->assertJsonPath('message', 'Entrada registrada.');
        $this->postJson(route('lancar.transferencia'), ['valor' => 100, 'data' => '2026-10-07', 'origem_id' => $itau->id, 'destino_id' => $sicoob->id])
            ->assertOk()->assertJsonPath('message', 'Transferência registrada.');

        $this->assertSame(803.15, (float) $itau->fresh()->saldo);
        $this->assertSame(100.0, (float) $sicoob->fresh()->saldo);

        $despesa = Despesa::withoutGlobalScopes()->first();
        $this->get(route('planejamento.lancamentos', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Açougue')->assertSee('data-editar="saida:' . $despesa->id . '"', false)
            ->assertSee('Itaú → Sicoob')->assertSee('Entre contas')->assertSee('Transferências');
    }

    public function test_erro_de_validacao_em_portugues(): void
    {
        $this->postJson(route('lancar.saida'), ['valor' => '', 'data_compra' => '2026-10-07'])
            ->assertStatus(422)->assertJsonPath('errors.valor.0', 'Informe o valor.');
        $itau = $this->conta('Itaú');
        $this->postJson(route('lancar.transferencia'), ['valor' => 10, 'data' => '2026-10-07', 'origem_id' => $itau->id, 'destino_id' => $itau->id])
            ->assertStatus(422)->assertJsonPath('errors.origem_id.0', 'A conta de origem deve ser diferente da conta de destino.');
    }

    public function test_abrir_editar_marcar_pago_e_excluir(): void
    {
        $itau = $this->conta('Itaú', ['saldo' => 500]);
        $this->travelTo('2026-10-07 12:00:00');
        $this->postJson(route('lancar.saida'), ['valor' => 50, 'data_compra' => '2026-10-09', 'forma_pagamento' => $itau->id, 'tipo_pagamento' => 'boleto', 'observacoes' => 'Luz'])->assertOk();
        $d = Despesa::withoutGlobalScopes()->first();

        $this->getJson(route('lancar.mostrar', ['saida', $d->id]))->assertOk()
            ->assertJsonPath('valor', 50)->assertJsonPath('descricao', 'Luz')->assertJsonPath('pago_em', null)->assertJsonPath('conta_id', $itau->id);

        $this->putJson(route('lancar.saida.atualizar', $d->id), ['valor' => '55,10', 'data_compra' => '2026-10-09', 'forma_pagamento' => $itau->id, 'tipo_pagamento' => 'boleto', 'observacoes' => 'Conta de luz'])
            ->assertOk()->assertJsonPath('message', 'Saída atualizada.');
        $this->assertSame(55.10, (float) $d->fresh()->valor);

        $this->postJson(route('lancar.pagar', ['saida', $d->id]))->assertOk()->assertJsonPath('message', 'Marcada como paga.');
        $this->assertSame('2026-10-07', $d->fresh()->data_pagamento->format('Y-m-d'));
        $this->assertSame(444.9, (float) $itau->fresh()->saldo);

        $this->deleteJson(route('lancar.excluir', ['saida', $d->id]))->assertOk()->assertJsonPath('message', 'Lançamento excluído.');
        $this->assertSame(500.0, (float) $itau->fresh()->saldo);
    }

    public function test_pagar_fatura_pelo_site(): void
    {
        $itau = $this->conta('Itaú', ['saldo' => 1000]);
        $nubank = $this->conta('Nubank', ['tem_conta_corrente' => false, 'tem_cartao_credito' => true, 'limite_cartao' => 3000, 'dia_fechamento_cartao' => 12, 'dia_vencimento_cartao' => 22]);
        $this->travelTo('2026-10-07 12:00:00');
        $this->postJson(route('lancar.saida'), ['valor' => 300, 'data_compra' => '2026-10-05', 'forma_pagamento' => $nubank->id, 'tipo_pagamento' => 'credito', 'observacoes' => 'Mercado'])->assertOk();

        $this->get(route('dashboard'))->assertOk()->assertSee('Nubank')->assertSee('Pagar');
        $this->get(route('planejamento.cartoes', ['cartao' => 'banco:' . $nubank->id]))->assertOk()
            ->assertSee('Pagar fatura')->assertSee('Compras lançadas')->assertSee('Mercado');

        $this->postJson(route('lancar.fatura', $nubank->id), ['vencimento' => '2026-10-22', 'conta_id' => $itau->id, 'data' => '2026-10-07'])
            ->assertOk()->assertJsonPath('message', 'Fatura paga.');
        $this->assertSame(700.0, (float) $itau->fresh()->saldo);
        $this->get(route('planejamento.cartoes', ['cartao' => 'banco:' . $nubank->id]))->assertOk()->assertSee('Paga com Itaú em 07/10');
    }

    public function test_outra_familia_nao_abre_nem_altera(): void
    {
        $itau = $this->conta('Itaú');
        $d = Despesa::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'valor' => 10, 'data_compra' => '2026-10-07', 'forma_pagamento' => $itau->id]);
        $r = Receita::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'valor' => 10, 'data_prevista_recebimento' => '2026-10-07']);
        $t = Transferencia::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'valor' => 10, 'data' => '2026-10-07', 'origem_id' => $itau->id, 'destino_id' => $itau->id]);

        $this->actingAs(User::factory()->create());
        $this->getJson(route('lancar.mostrar', ['saida', $d->id]))->assertNotFound();
        $this->getJson(route('lancar.mostrar', ['entrada', $r->id]))->assertNotFound();
        $this->deleteJson(route('lancar.excluir', ['transferencia', $t->id]))->assertNotFound();
        $this->postJson(route('lancar.pagar', ['saida', $d->id]))->assertNotFound();
        $this->assertNull($d->fresh()->data_pagamento);
        $this->assertNotNull($t->fresh());
    }
}
