<?php

namespace Tests\Feature\Lancamentos;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\User;
use App\Services\Financeiro\FinanceiroService;
use App\Services\Planejamento\PlanejamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Família que lança tudo pelo sistema: saída, entrada, transferência, cartão e fatura. */
class LancamentoManualTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Banco $itau;
    private Banco $sicoob;
    private Banco $nubank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-07 09:00:00');
        $this->user   = User::factory()->create();
        $this->itau   = $this->conta('Itaú', ['saldo' => 1000]);
        $this->sicoob = $this->conta('Sicoob', ['saldo' => 200]);
        // Cartão sem conta corrente: fecha dia 12, vence dia 22.
        $this->nubank = $this->conta('Nubank', ['tem_conta_corrente' => false, 'tem_cartao_credito' => true, 'limite_cartao' => 5000, 'dia_fechamento_cartao' => 12, 'dia_vencimento_cartao' => 22]);
        $this->travelTo('2026-10-07 12:00:00');
        Sanctum::actingAs($this->user);
    }

    private function conta(string $nome, array $extra = []): Banco
    {
        return Banco::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => $nome, 'tem_conta_corrente' => true,
        ]);
    }

    private function saldo(Banco $b): float
    {
        return collect(app(FinanceiroService::class)->contas($this->user->tenant_id)['itens'])->firstWhere('id', $b->id)['saldo'];
    }

    private function mes(): array
    {
        return app(FinanceiroService::class)->mes($this->user->tenant_id, now());
    }

    private function vencimentos(): array
    {
        return app(PlanejamentoService::class)->vencimentos($this->user->tenant_id);
    }

    public function test_saida_paga_sai_do_saldo_e_do_mes(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => '96,85', 'data_compra' => '2026-10-07', 'data_pagamento' => '2026-10-07', 'forma_pagamento' => $this->itau->id, 'tipo_pagamento' => 'pix', 'observacoes' => 'Açougue'])
            ->assertCreated();

        $this->assertSame(903.15, $this->saldo($this->itau));
        $this->assertSame(96.85, $this->mes()['saiu']);
        $this->assertSame(903.15, (float) $this->itau->fresh()->saldo);
    }

    public function test_entrada_pendente_aparece_a_receber_e_nao_mexe_no_saldo(): void
    {
        $this->postJson('/api/v1/receitas', ['valor' => 7500, 'data_prevista_recebimento' => '2026-10-10', 'forma_recebimento' => $this->itau->id, 'observacoes' => 'Salário'])->assertCreated();

        $this->assertSame(1000.0, $this->saldo($this->itau));
        $this->assertSame(0.0, $this->mes()['entrou']);
        $this->assertSame('Salário', $this->vencimentos()['a_receber'][0]['descricao']);

        $receita = Receita::withoutGlobalScopes()->first();
        $this->putJson("/api/v1/receitas/{$receita->id}", ['valor' => 7500, 'data_prevista_recebimento' => '2026-10-10', 'data_recebimento' => '2026-10-07', 'forma_recebimento' => $this->itau->id])->assertOk();
        $this->assertSame(8500.0, $this->saldo($this->itau));
        $this->assertSame([], $this->vencimentos()['a_receber']);
    }

    public function test_saida_pendente_aparece_em_a_pagar_e_atrasada_depois_da_data(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => 950, 'data_compra' => '2026-10-15', 'forma_pagamento' => $this->itau->id, 'tipo_pagamento' => 'boleto', 'observacoes' => 'Escola'])->assertCreated();
        $this->assertSame('Escola', $this->vencimentos()['a_pagar'][0]['descricao']);

        $this->travelTo('2026-10-16 08:00:00');
        $this->assertSame('Escola', $this->vencimentos()['atrasado'][0]['descricao']);
        $this->assertSame(1000.0, $this->saldo($this->itau));
    }

    public function test_transferencia_move_saldos_sem_entrar_no_mes_e_aparece_no_extrato(): void
    {
        $this->postJson('/api/v1/transferencias', ['valor' => 500, 'data' => '2026-10-07', 'origem_id' => $this->itau->id, 'destino_id' => $this->sicoob->id])->assertCreated();

        $this->assertSame(500.0, $this->saldo($this->itau));
        $this->assertSame(700.0, $this->saldo($this->sicoob));
        $this->assertSame(1200.0, app(FinanceiroService::class)->contas($this->user->tenant_id)['total']);
        $this->assertSame(0.0, $this->mes()['entrou']);
        $this->assertSame(0.0, $this->mes()['saiu']);

        $extrato = app(PlanejamentoService::class)->lancamentos($this->user->tenant_id, now(), comTransferencias: true);
        $this->assertSame('transferencia', $extrato[0]['tipo']);
        $this->assertSame('Itaú → Sicoob', $extrato[0]['conta']);
        $this->assertSame([], app(PlanejamentoService::class)->lancamentos($this->user->tenant_id, now()));
    }

    public function test_compra_parcelada_no_cartao_cai_uma_parcela_por_fatura(): void
    {
        // 07/10 é antes do fechamento (12): 1ª parcela vence 22/10.
        $this->postJson('/api/v1/despesas', ['valor' => 900, 'data_compra' => '2026-10-07', 'data_pagamento' => '2026-10-07', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito', 'parcelas' => 3, 'observacoes' => 'Geladeira'])
            ->assertCreated()->assertJsonPath('count', 3)->assertJsonPath('aviso', null);

        $datas = Despesa::withoutGlobalScopes()->orderBy('data_compra')->get()->map(fn ($d) => [$d->data_compra->format('Y-m-d'), (float) $d->valor, $d->data_pagamento])->all();
        $this->assertSame([['2026-10-22', 300.0, null], ['2026-11-22', 300.0, null], ['2026-12-22', 300.0, null]], $datas);

        $cartao = app(PlanejamentoService::class)->cartoes($this->user->tenant_id)['itens']->firstWhere('nome', 'Nubank');
        $this->assertSame(900.0, (float) $cartao->limite_utilizado);
        $this->assertSame(4100.0, $cartao->limite_disponivel);
        $this->assertSame(300.0, (float) $cartao->fatura_atual);
        $this->assertSame(0.0, $this->mes()['saiu']);
    }

    public function test_cartao_sem_fechamento_avisa_e_lanca_na_data(): void
    {
        $semDia = $this->conta('Celebre', ['tem_conta_corrente' => false, 'tem_cartao_credito' => true]);
        $this->postJson('/api/v1/despesas', ['valor' => 50, 'data_compra' => '2026-10-07', 'forma_pagamento' => $semDia->id, 'tipo_pagamento' => 'credito'])
            ->assertCreated()->assertJsonPath('aviso', 'Cadastre o fechamento e o vencimento do cartão para a compra cair na fatura certa.');
        $this->assertSame('2026-10-07', Despesa::withoutGlobalScopes()->first()->data_compra->format('Y-m-d'));
    }

    public function test_pagar_fatura_debita_a_conta_sem_contar_duas_vezes(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => 500, 'data_compra' => '2026-10-01', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito', 'observacoes' => 'Mercado'])->assertCreated();
        $this->postJson('/api/v1/despesas', ['valor' => 300, 'data_compra' => '2026-10-05', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito', 'observacoes' => 'Farmácia'])->assertCreated();
        // Compra depois do fechamento vai para novembro e continua pendente.
        $this->postJson('/api/v1/despesas', ['valor' => 40, 'data_compra' => '2026-10-13', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito'])->assertCreated();

        $fatura = collect($this->vencimentos()['a_pagar'])->firstWhere('origem', 'fatura');
        $this->assertSame(['Fatura Nubank', 800.0, '2026-10-22', false], [$fatura['descricao'], $fatura['valor'], $fatura['data'], $fatura['pago']]);

        $this->postJson("/api/v1/cartoes/{$this->nubank->id}/pagar-fatura", ['vencimento' => '2026-10-22', 'conta_id' => $this->itau->id, 'data' => '2026-10-07'])
            ->assertOk()->assertJsonPath('count', 2)->assertJsonPath('message', 'Fatura paga.');

        $this->assertSame(200.0, $this->saldo($this->itau));
        $this->assertSame(800.0, $this->mes()['saiu']);
        $fatura = collect($this->vencimentos()['a_pagar'])->firstWhere('origem', 'fatura');
        $this->assertTrue($fatura['pago']);
        $this->assertSame(0.0, $this->vencimentos()['totais']['a_pagar']);

        $faturas = app(PlanejamentoService::class)->faturasDoCartao($this->user->tenant_id, app(PlanejamentoService::class)->cartoes($this->user->tenant_id)['itens']->firstWhere('nome', 'Nubank'));
        $this->assertSame(['2026-11', 40.0, false], [$faturas[0]['mes'], $faturas[0]['total'], $faturas[0]['paga']]);
        $this->assertSame(['2026-10', 800.0, true, 'Itaú'], [$faturas[1]['mes'], $faturas[1]['total'], $faturas[1]['paga'], $faturas[1]['paga_com']]);

        // Fatura sem compras pendentes: nada a pagar.
        $this->postJson("/api/v1/cartoes/{$this->nubank->id}/pagar-fatura", ['vencimento' => '2026-10-22', 'conta_id' => $this->itau->id])
            ->assertStatus(422)->assertJsonPath('message', 'Esta fatura não tem compras pendentes.');
    }

    public function test_fatura_vencida_sem_pagamento_fica_atrasada(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => 120, 'data_compra' => '2026-10-01', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito'])->assertCreated();
        $this->travelTo('2026-10-25 08:00:00');

        $atrasada = collect($this->vencimentos()['atrasado'])->firstWhere('origem', 'fatura');
        $this->assertSame(['Fatura Nubank', 120.0, '2026-10-22'], [$atrasada['descricao'], $atrasada['valor'], $atrasada['data']]);
    }

    public function test_excluir_esta_e_as_proximas_da_serie(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => 100, 'data_compra' => '2026-10-10', 'forma_pagamento' => $this->itau->id, 'tipo_pagamento' => 'boleto', 'parcelas' => 4])->assertCreated();
        $segunda = Despesa::withoutGlobalScopes()->orderBy('data_compra')->skip(1)->first();

        $this->deleteJson("/api/v1/despesas/{$segunda->id}?escopo=esta_e_futuras")->assertOk()->assertJsonPath('count', 3);
        $this->assertSame(1, Despesa::withoutGlobalScope('tenant')->count());
    }

    public function test_cartao_da_planilha_nao_duplica_com_o_cadastro(): void
    {
        \App\Models\PlanCartao::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'chave' => 'c1', 'conteudo_hash' => 'x', 'linha' => 1, 'nome' => 'Cartão Nubank', 'banco' => 'Nubank', 'limite_total' => 3000, 'limite_utilizado' => 100]);

        $nomes = app(PlanejamentoService::class)->cartoes($this->user->tenant_id)['itens']->pluck('nome')->all();
        $this->assertSame(['Cartão Nubank'], $nomes);
    }

    public function test_outra_familia_nao_lanca_nem_paga_nem_exclui_nesta(): void
    {
        $this->postJson('/api/v1/despesas', ['valor' => 10, 'data_compra' => '2026-10-07', 'forma_pagamento' => $this->nubank->id, 'tipo_pagamento' => 'credito'])->assertCreated();
        $despesa = Despesa::withoutGlobalScopes()->first();

        $outro = User::factory()->create();
        Sanctum::actingAs($outro);
        $this->postJson('/api/v1/despesas', ['valor' => 10, 'data_compra' => '2026-10-07', 'forma_pagamento' => $this->itau->id, 'tipo_pagamento' => 'pix'])
            ->assertStatus(422)->assertJsonValidationErrors('forma_pagamento');
        $this->postJson('/api/v1/transferencias', ['valor' => 10, 'data' => '2026-10-07', 'origem_id' => $this->itau->id, 'destino_id' => $this->sicoob->id])->assertStatus(422);
        $this->postJson("/api/v1/cartoes/{$this->nubank->id}/pagar-fatura", ['vencimento' => '2026-10-22', 'conta_id' => $this->itau->id])->assertStatus(422);
        $propria = Banco::withoutGlobalScopes()->create(['tenant_id' => $outro->tenant_id, 'user_id' => $outro->id, 'nome' => 'Inter', 'tem_conta_corrente' => true]);
        $this->postJson("/api/v1/cartoes/{$this->nubank->id}/pagar-fatura", ['vencimento' => '2026-10-22', 'conta_id' => $propria->id])->assertNotFound();
        $this->assertNull(Despesa::withoutGlobalScopes()->first()->data_pagamento);
        $this->deleteJson("/api/v1/despesas/{$despesa->id}")->assertNotFound();
        $this->assertSame(1, Despesa::withoutGlobalScopes()->count());
        $this->assertSame([], app(PlanejamentoService::class)->cartoes($outro->tenant_id)['itens']->all());
    }
}
