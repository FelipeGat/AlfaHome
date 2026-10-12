<?php

namespace Tests\Feature\Planejamento;

use App\Models\Banco;
use App\Models\PlanCartao;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Financeiro\FinanceiroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Compra no cartão da planilha sai da conta que paga a fatura (ex.: Mercado Pago pago pelo Sicoob). */
class ContaPagamentoCartaoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Banco $sicoob;
    private Banco $mp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-12 08:00:00');
        $this->user   = User::factory()->create();
        $this->sicoob = $this->conta('Sicoob', 3610.09);
        $this->mp     = $this->conta('Mercado Pago', 55.56);
        PlanCartao::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => 'mp', 'conteudo_hash' => 'x', 'linha' => 1,
            'nome' => 'Cartão Mercado Pago', 'banco' => 'Mercado Pago', 'limite_total' => 3500, 'limite_utilizado' => 2138.73,
            'dia_fechamento' => 2, 'dia_vencimento' => 7, 'fatura_atual' => 177.77, 'status_fatura' => 'Aberta',
        ]);
        $this->travelTo('2026-10-12 10:00:00');
        // Compra no cartão Mercado Pago marcada como paga (a fatura saiu do Sicoob).
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => 'c1', 'conteudo_hash' => 'x', 'linha' => 1,
            'data' => '2026-10-12', 'tipo' => 'despesa', 'descricao' => 'Computador (Par. 08 de 18)', 'categoria' => 'Compras',
            'forma' => 'cartao', 'conta' => 'Mercado Pago', 'valor_previsto' => 177.77, 'valor_realizado' => 177.77, 'status' => 'concluido',
        ]);
    }

    private function conta(string $nome, float $saldo): Banco
    {
        return Banco::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => $nome, 'tem_conta_corrente' => true, 'saldo' => $saldo]);
    }

    private function saldo(Banco $b): float
    {
        return collect(app(FinanceiroService::class)->contas($this->user->tenant_id)['itens'])->firstWhere('id', $b->id)['saldo'];
    }

    public function test_sem_conta_de_pagamento_sai_da_conta_do_cartao(): void
    {
        $this->assertSame(-122.21, $this->saldo($this->mp));
        $this->assertSame(3610.09, $this->saldo($this->sicoob));
    }

    public function test_com_conta_de_pagamento_sai_da_conta_que_paga_a_fatura(): void
    {
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/planejamento/cartoes/conta-pagamento', ['cartao' => 'Mercado Pago', 'banco_id' => $this->sicoob->id])
            ->assertOk()->assertJsonPath('message', 'Conta de pagamento salva.');

        $this->assertSame(55.56, $this->saldo($this->mp));
        $this->assertSame(3432.32, $this->saldo($this->sicoob));
        $this->assertSame(3432.32, (float) $this->sicoob->fresh()->saldo, 'saldo gravado recalculado');
        $this->getJson('/api/v1/planejamento/cartoes')->assertJsonPath('data.0.conta_pagamento_id', $this->sicoob->id);

        // Voltar para "mesma conta".
        $this->putJson('/api/v1/planejamento/cartoes/conta-pagamento', ['cartao' => 'Cartão Mercado Pago', 'banco_id' => null])->assertOk();
        $this->assertSame(-122.21, $this->saldo($this->mp));
    }

    public function test_tela_de_cartoes_mostra_o_cartao_e_grava_pelo_site(): void
    {
        $this->actingAs($this->user);
        $this->get(route('planejamento.cartoes'))->assertOk()
            ->assertSee('Fatura')->assertSee('Melhor dia')->assertSee('03/11')->assertSee('Fatura paga com');

        $this->postJson(route('planejamento.cartoes.conta-pagamento'), ['cartao' => 'Mercado Pago', 'banco_id' => $this->sicoob->id])->assertOk();
        $this->assertSame(3432.32, $this->saldo($this->sicoob));
    }

    public function test_outra_familia_nao_usa_conta_desta(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/v1/planejamento/cartoes/conta-pagamento', ['cartao' => 'Mercado Pago', 'banco_id' => $this->sicoob->id])
            ->assertStatus(422)->assertJsonPath('errors.banco_id.0', 'Conta não encontrada.');
        $this->assertSame(3610.09, $this->saldo($this->sicoob));
    }
}
