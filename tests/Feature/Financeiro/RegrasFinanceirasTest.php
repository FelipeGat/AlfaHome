<?php

namespace Tests\Feature\Financeiro;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\PlanLancamento;
use App\Models\SaldoAjuste;
use App\Models\Transferencia;
use App\Models\User;
use App\Services\Financeiro\FinanceiroService;
use App\Services\Planejamento\PlanejamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Regras únicas do dinheiro: saldo com ajuste, mês, cartão e transferência. */
class RegrasFinanceirasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Banco $sicoob;
    private Banco $itau;
    private int $linha = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-05 09:00:00');
        $this->user   = User::factory()->create();
        $this->sicoob = $this->conta('Sicoob', -189.02, cartao: true);
        $this->itau   = $this->conta('Itaú', 35.42);
    }

    private function conta(string $nome, float $saldo, bool $cartao = false, bool $corrente = true): Banco
    {
        return Banco::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => $nome,
            'tem_conta_corrente' => $corrente, 'tem_cartao_credito' => $cartao, 'saldo' => $saldo,
        ]);
    }

    /** Lançamento da planilha; `realizado` nulo = pendente. */
    private function planilha(string $tipo, string $data, string $descricao, float $previsto, ?float $realizado, ?string $conta = 'Sicoob', ?string $forma = 'pix', ?string $categoria = 'Alimentação'): PlanLancamento
    {
        return PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1($descricao . $this->linha), 'conteudo_hash' => sha1('x'), 'linha' => $this->linha++,
            'data' => $data, 'tipo' => $tipo, 'descricao' => $descricao, 'categoria' => $categoria, 'forma' => $forma, 'conta' => $conta,
            'valor_previsto' => $previsto, 'valor_realizado' => $realizado, 'status' => $realizado !== null ? 'concluido' : 'pendente',
        ]);
    }

    private function servico(): FinanceiroService
    {
        return app(FinanceiroService::class);
    }

    private function saldo(Banco $b): float
    {
        return collect($this->servico()->contas($this->user->tenant_id)['itens'])->firstWhere('id', $b->id)['saldo'];
    }

    private function recalcular(): void
    {
        $this->servico()->recalcular($this->user->tenant_id);
    }

    public function test_sem_movimento_novo_o_saldo_e_o_informado(): void
    {
        $this->assertSame(-189.02, $this->saldo($this->sicoob));
        $this->assertSame(-153.6, $this->servico()->contas($this->user->tenant_id)['total']);
    }

    public function test_saida_e_entrada_realizadas_depois_do_ajuste_movem_o_saldo(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $this->planilha('despesa', '2026-10-06', 'Mercado', 50, 50);
        $this->planilha('receita', '2026-10-06', 'Pró-labore', 3000, 3000);
        $this->recalcular();

        $this->assertSame(2760.98, $this->saldo($this->sicoob));
        $this->assertSame(2760.98, (float) $this->sicoob->fresh()->saldo, 'a coluna guarda o mesmo número');
    }

    public function test_saida_paga_com_data_anterior_ao_ajuste_nao_muda_o_saldo(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $this->planilha('despesa', '2026-10-03', 'Farmácia', 80, 80);
        $this->recalcular();

        $this->assertSame(-189.02, $this->saldo($this->sicoob));
    }

    public function test_mesmo_dia_conta_so_o_registrado_depois_do_ajuste(): void
    {
        $this->travelTo('2026-10-05 08:00:00');
        $this->planilha('despesa', '2026-10-05', 'Já no saldo', 10, 10);
        $this->travelTo('2026-10-05 09:00:00');
        $this->servico()->ajustar($this->sicoob->fresh(), -189.02);
        $this->travelTo('2026-10-05 15:00:00');
        $this->planilha('despesa', '2026-10-05', 'Depois do ajuste', 20, 20);

        $this->assertSame(-209.02, $this->saldo($this->sicoob));
    }

    public function test_pendente_nao_mexe_no_saldo(): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        $this->planilha('despesa', '2026-10-07', 'Escola', 950, null);

        $this->assertSame(-189.02, $this->saldo($this->sicoob));
    }

    public function test_saldo_negativo_e_conta_sem_cadastro(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $this->planilha('despesa', '2026-10-06', 'Almoço', 30, 30, conta: 'Ticket', forma: 'debito');
        $contas = $this->servico()->contas($this->user->tenant_id);

        $this->assertLessThan(0, $this->saldo($this->sicoob));
        $this->assertSame([['conta' => 'Ticket', 'movimentos' => 1]], $contas['sem_conta']);
        $this->assertSame(-153.6, $contas['total'], 'conta sem cadastro não mexe em saldo');
    }

    public function test_ajustar_saldo_registra_a_diferenca_e_vale_em_toda_tela(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $this->planilha('despesa', '2026-10-06', 'Mercado', 50, 50);
        $this->travelTo('2026-10-06 18:00:00');
        $ajuste = $this->servico()->ajustar($this->sicoob->fresh(), -200.00, userId: $this->user->id);

        $this->assertSame(-200.0, $this->saldo($this->sicoob));
        $this->assertSame(39.02, (float) $ajuste->diferenca, '-200 informado − (-239,02) calculado');
        $this->assertSame(-200.0, (float) $this->sicoob->fresh()->saldo);
        $hoje = app(PlanejamentoService::class)->hoje($this->user->tenant_id);
        $this->assertSame(-164.58, $hoje['contas']['total']);
    }

    public function test_transferencia_entre_contas_proprias_nao_e_entrada_nem_saida(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        Transferencia::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'valor' => 500,
            'data' => '2026-10-06', 'origem_id' => $this->itau->id, 'destino_id' => $this->sicoob->id,
        ]);

        $this->assertSame(310.98, $this->saldo($this->sicoob));
        $this->assertSame(-464.58, $this->saldo($this->itau));
        $this->assertSame(-153.6, $this->servico()->contas($this->user->tenant_id)['total'], 'total não muda');
        $mes = $this->servico()->mes($this->user->tenant_id, now());
        $this->assertSame(0.0, $mes['entrou']);
        $this->assertSame(0.0, $mes['saiu']);
        $this->assertSame(-464.58, (float) $this->itau->fresh()->saldo, 'o observer recalcula sozinho');
    }

    public function test_lancamento_do_sistema_pago_e_excluido(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $despesa = Despesa::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_pagamento' => $this->itau->id,
            'tipo_pagamento' => 'pix', 'valor' => 20, 'data_compra' => '2026-10-06', 'data_pagamento' => '2026-10-06',
        ]);
        $this->assertSame(15.42, (float) $this->itau->fresh()->saldo);

        $despesa->delete();
        $this->assertSame(35.42, (float) $this->itau->fresh()->saldo);
        $this->assertSame(0.0, $this->servico()->mes($this->user->tenant_id, now())['saiu']);
    }

    public function test_compra_no_cartao_conta_uma_vez_e_limite_nao_e_saldo(): void
    {
        $this->sicoob->update(['limite_cartao' => 23593]);
        $this->travelTo('2026-10-22 10:00:00');
        $this->planilha('despesa', '2026-10-22', 'Netflix', 20.90, 20.90, forma: 'cartao', categoria: 'Assinaturas');

        $mes = $this->servico()->mes($this->user->tenant_id, now());
        $this->assertSame(20.9, $mes['saiu']);
        $this->assertSame(-209.92, $this->saldo($this->sicoob), 'a fatura paga sai da conta do banco');
        $this->assertSame(-174.5, $this->servico()->contas($this->user->tenant_id)['total'], 'limite fora do saldo');
    }

    public function test_mes_igual_ao_previsto_x_realizado_e_mes_vazio(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        $this->planilha('despesa', '2026-10-01', 'IOF', 2.36, 2.36, categoria: 'Tarifa');
        $this->planilha('despesa', '2026-10-05', 'Açougue', 96.85, 96.85);
        $this->planilha('receita', '2026-10-03', 'Salário', 5000, 5000, conta: 'Itaú', categoria: 'Renda');
        $this->planilha('despesa', '2026-10-20', 'Escola', 950, null);

        $mes = $this->servico()->mes($this->user->tenant_id, now());
        $resumo = app(PlanejamentoService::class)->resumoMes($this->user->tenant_id, now());
        $this->assertSame(5000.0, $mes['entrou']);
        $this->assertSame(99.21, $mes['saiu']);
        $this->assertSame(4900.79, $mes['resultado']);
        $this->assertEquals($resumo['despesas']['realizado'], $mes['saiu']);
        $this->assertSame('Alimentação', $mes['categorias'][0]['categoria']);

        $vazio = $this->servico()->mes($this->user->tenant_id, now()->addMonths(3));
        $this->assertFalse($vazio['tem_movimentacao']);
        $this->assertSame([], $vazio['categorias']);
    }

    public function test_realizado_e_a_situacao_pago_nao_o_valor_preenchido(): void
    {
        $this->travelTo('2026-10-08 10:00:00');
        // A Ju preenche o realizado de contas futuras; continuam pendentes.
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('pend'), 'conteudo_hash' => sha1('x'), 'linha' => 90,
            'data' => '2026-10-07', 'tipo' => 'despesa', 'descricao' => 'Água Ago/2026', 'conta' => 'Sicoob', 'forma' => 'boleto',
            'valor_previsto' => 80, 'valor_realizado' => 80, 'status' => 'pendente',
        ]);
        // Pago sem valor realizado vale o previsto.
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('pago'), 'conteudo_hash' => sha1('x'), 'linha' => 91,
            'data' => '2026-10-07', 'tipo' => 'despesa', 'descricao' => 'Computador', 'conta' => 'Sicoob', 'forma' => 'pix',
            'valor_previsto' => 177.77, 'valor_realizado' => null, 'status' => 'concluido',
        ]);

        $mes = $this->servico()->mes($this->user->tenant_id, now());
        $this->assertSame(177.77, $mes['saiu']);
        $this->assertSame(-366.79, $this->saldo($this->sicoob));
        $this->assertSame('Água Ago/2026', $this->servico()->proximosPagamentos($this->user->tenant_id)[0]['descricao'], 'continua a pagar');
    }

    public function test_virada_de_mes(): void
    {
        $this->travelTo('2026-10-31 10:00:00');
        $this->planilha('despesa', '2026-10-31', 'Pizza', 60, 60);
        $this->travelTo('2026-11-01 08:00:00');
        $this->planilha('despesa', '2026-11-01', 'Padaria', 12, 12);

        $inicio = $this->servico()->inicio($this->user->tenant_id);
        $this->assertSame('2026-11', $inicio['mes']['mes']);
        $this->assertSame(12.0, $inicio['mes']['saiu']);
        $this->assertSame('Padaria', $inicio['ultimas'][0]['descricao']);
        $this->assertSame(-261.02, collect($inicio['contas']['itens'])->firstWhere('id', $this->sicoob->id)['saldo'], 'o saldo carrega os dois meses');
    }

    public function test_centavos_nao_se_perdem(): void
    {
        $this->travelTo('2026-10-06 10:00:00');
        foreach (range(1, 3) as $i) {
            $this->planilha('despesa', '2026-10-06', "Parcela {$i}", 0.1, 0.1);
        }
        $this->planilha('despesa', '2026-10-06', 'Ajuste', 0.2, 0.2);

        $this->assertSame(-189.52, $this->saldo($this->sicoob));
    }

    public function test_outra_familia_nao_entra_na_conta(): void
    {
        $outro = User::factory()->create();
        $this->travelTo('2026-10-06 10:00:00');
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $outro->tenant_id, 'chave' => sha1('x'), 'conteudo_hash' => sha1('x'), 'linha' => 1,
            'data' => '2026-10-06', 'tipo' => 'despesa', 'descricao' => 'De outra família', 'conta' => 'Sicoob',
            'valor_previsto' => 999, 'valor_realizado' => 999, 'status' => 'concluido',
        ]);

        $this->assertSame(-189.02, $this->saldo($this->sicoob));
        $this->assertSame(1, SaldoAjuste::withoutGlobalScopes()->where('banco_id', $this->sicoob->id)->count());
    }
}
