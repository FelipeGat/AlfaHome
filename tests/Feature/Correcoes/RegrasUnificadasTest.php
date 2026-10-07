<?php

namespace Tests\Feature\Correcoes;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Investimento;
use App\Models\InvestimentoRendimento;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Constitution IV (uma definição por número, web = API) e VI (isolamento por família). */
class RegrasUnificadasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function cartao(): Banco
    {
        return Banco::create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Cartão', 'tem_conta_corrente' => true, 'tem_cartao_credito' => true, 'limite_cartao' => 1000, 'saldo' => 0]);
    }

    private function despesa(Banco $banco, ?string $tipo, float $valor, string $data): Despesa
    {
        return Despesa::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_pagamento' => $banco->id,
            'tipo_pagamento' => $tipo, 'valor' => $valor, 'data_compra' => $data, 'data_pagamento' => null,
        ]);
    }

    public function test_fatura_do_cartao_e_a_mesma_no_painel_na_tela_de_bancos_e_no_saldo_gravado(): void
    {
        $cartao = $this->cartao();
        $this->despesa($cartao, 'credito', 300, now()->subMonths(2)->format('Y-m-d')); // fatura antiga ainda aberta
        $this->despesa($cartao, 'credito', 200, now()->format('Y-m-d'));
        $this->despesa($cartao, null, 999, now()->format('Y-m-d'));                    // importada de extrato: não é compra no cartão

        $gravado = (float) $cartao->fresh()->saldo_cartao;
        $this->assertEqualsWithDelta(500.0, $gravado, 0.001);

        $inicio = $this->get(route('dashboard'))->assertOk()->viewData('cartoesInfo');
        $this->assertEqualsWithDelta(500.0, $inicio['limite_utilizado'], 0.001, 'cartões do Início');
        $this->assertEqualsWithDelta(500.0, $inicio['itens'][0]['utilizado'], 0.001, 'cartão no Início');

        $bancos = $this->get(route('bancos.index'))->assertOk();
        $this->assertEqualsWithDelta(500.0, (float) $bancos->viewData('bancos')->firstWhere('id', $cartao->id)->saldo_cartao, 0.001, 'tela de bancos');
    }

    public function test_receita_aceita_todas_as_formas_na_api(): void
    {
        $token = $this->user->createToken('t')->plainTextToken;

        foreach (Receita::TIPOS_PAGAMENTO as $tipo) {
            $this->withHeader('Authorization', "Bearer {$token}")
                ->postJson('/api/v1/receitas', ['valor' => 10, 'data_prevista_recebimento' => '2026-08-01', 'tipo_pagamento' => $tipo])
                ->assertCreated();
        }

        $this->assertContains('deposito', Receita::TIPOS_PAGAMENTO);
        $this->assertContains('boleto', Receita::TIPOS_PAGAMENTO);
    }

    public function test_excluir_rendimento_exige_que_ele_seja_do_investimento_da_url(): void
    {
        $dados = ['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'tipo_investimento' => 'CDB', 'data_aporte' => '2026-01-01', 'valor_aportado' => 1000];
        $a = Investimento::create($dados + ['nome_ativo' => 'A']);
        $b = Investimento::create($dados + ['nome_ativo' => 'B']);
        $rendimentoDeB = InvestimentoRendimento::create(['investimento_id' => $b->id, 'tenant_id' => $this->user->tenant_id, 'data' => '2026-02-01', 'valor_atual' => 1010]);

        $this->delete(route('investimentos.rendimentos.destroy', [$a, $rendimentoDeB]))->assertNotFound();

        $this->assertNotNull($rendimentoDeB->fresh());
    }
}
