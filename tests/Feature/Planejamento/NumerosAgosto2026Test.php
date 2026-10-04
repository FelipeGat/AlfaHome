<?php

namespace Tests\Feature\Planejamento;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\PlanCompraParcelada;
use App\Models\PlanContaFixa;
use App\Models\Receita;
use App\Models\User;
use App\Services\Planejamento\PlanejamentoService;
use App\Services\Planejamento\PlanilhaImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

/**
 * Conferência com a planilha da família (constitution III): cada número
 * esperado aqui foi lido da planilha de referência, não calculado pelo sistema.
 */
class NumerosAgosto2026Test extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private User $user;
    private PlanejamentoService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->servico = app(PlanejamentoService::class);
        app(PlanilhaImportService::class)->importar($this->user->tenant_id, $this->user->id, $this->planilha(), 'planilha.xlsx');
    }

    private function agosto(): array
    {
        return $this->servico->resumoMes($this->user->tenant_id, Carbon::create(2026, 8, 1));
    }

    public function test_previsto_x_realizado_do_mes(): void
    {
        $r = $this->agosto();

        // Aba Planejamento Mensal, linha de ago/2026.
        $this->assertSame(['previsto' => 17261.86, 'realizado' => 17632.86], $r['receitas']);
        $this->assertSame(['previsto' => 12217.44, 'realizado' => 12225.10], $r['despesas']);
        $this->assertSame(['previsto' => 5044.42, 'realizado' => 5407.76], $r['saldo']);
        $this->assertSame(30.67, $r['economia_pct']);
        $this->assertSame(69.33, $r['comprometimento_pct']);
    }

    public function test_despesas_por_categoria(): void
    {
        $categorias = collect($this->agosto()['por_categoria'])->pluck('realizado', 'categoria');

        // Aba Dashboard, bloco "Despesas por categoria".
        $this->assertSame(3000.00, $categorias['Moradia']);
        $this->assertSame(549.46, $categorias['Alimentação']);
        $this->assertSame(98.37, $categorias['Transporte']);
        $this->assertSame(130.00, $categorias['Saúde']);
        $this->assertSame(109.29, $categorias['Educação']);
        $this->assertSame(85.92, $categorias['Lazer']);
        $this->assertSame(72.89, $categorias['Assinaturas']);
        $this->assertSame(304.09, $categorias['Compras']);
        $this->assertSame(4683.05, $categorias['Investimentos']);
        $this->assertSame(1134.70, $categorias['Pensão']);

        // O Dashboard da planilha lista só 11 categorias; o sistema mostra todas as que têm movimento.
        $this->assertArrayHasKey('Restaurante', $categorias);
        $this->assertEqualsWithDelta(12225.10, $categorias->sum(), 0.001);
    }

    public function test_cartoes(): void
    {
        $cartoes = $this->servico->cartoes($this->user->tenant_id);
        $sicoob  = $cartoes['itens']->firstWhere('nome', 'Cartão Sicoob');

        $this->assertSame('23593.00', $sicoob->limite_total);
        $this->assertSame('21737.85', $sicoob->limite_utilizado);
        $this->assertSame(1855.15, $sicoob->limite_disponivel);
        $this->assertSame(12, $sicoob->dia_fechamento);
        $this->assertSame(22, $sicoob->dia_vencimento);
        $this->assertSame('3921.44', $sicoob->fatura_atual);

        // Aba Dashboard: limite disponível, faturas em aberto, limite de cartão utilizado.
        $this->assertSame(1855.15, $cartoes['resumo']['limite_disponivel']);
        $this->assertSame(5549.77, $cartoes['resumo']['faturas_abertas']);
        $this->assertSame(92.64, $cartoes['resumo']['utilizado_pct']);

        $celebre = $cartoes['itens']->firstWhere('nome', 'Cartão celebre');
        $this->assertNull($celebre->limite_total);
        $this->assertNull($celebre->limite_disponivel);
        $this->assertNull($celebre->utilizado_pct);
    }

    public function test_compras_parceladas(): void
    {
        $iphone = PlanCompraParcelada::withoutGlobalScopes()->where('compra', 'Celular Iphone')->first();

        $this->assertSame(10, $iphone->parcelas);
        $this->assertSame(519.90, $iphone->valor_parcela);
        $this->assertSame(6, $iphone->parcelas_pagas);
        $this->assertSame(4, $iphone->parcelas_restantes);
        $this->assertSame(2079.60, $iphone->saldo);

        $computador = PlanCompraParcelada::withoutGlobalScopes()->where('compra', 'Computador')->first();
        $this->assertSame(177.77, $computador->valor_parcela);
        $this->assertSame(12, $computador->parcelas_restantes);
        $this->assertSame(2133.24, $computador->saldo);
    }

    public function test_dividas(): void
    {
        $dividas = $this->servico->dividas($this->user->tenant_id);
        $empresa = $dividas['itens']->first();

        $this->assertSame('Empréstimo empresa', $empresa->nome);
        $this->assertSame('Sicoob', $empresa->credor);
        $this->assertSame('15000.00', $empresa->saldo_inicial);
        $this->assertSame('6744.29', $empresa->saldo_atual);
        $this->assertSame(8255.71, $empresa->amortizado);
        $this->assertSame(55.04, $empresa->amortizado_pct);
        $this->assertSame('0.052000', $empresa->taxa_mensal);
        $this->assertSame('720.21', $empresa->parcela_mensal);
        $this->assertSame('alta', $empresa->prioridade);
        $this->assertSame('19 parcelas', $empresa->observacao);

        // Aba Dashboard: saldo de dívidas.
        $this->assertSame(10174.66, $dividas['resumo']['saldo_total']);
    }

    public function test_metas(): void
    {
        $metas   = $this->servico->metas($this->user->tenant_id);
        $reserva = $metas['itens']->firstWhere('nome', 'Reserva de emergência');
        $viagem  = $metas['itens']->firstWhere('nome', 'Viagem');

        $this->assertSame('30000.00', $reserva->valor_alvo);
        $this->assertSame(30000.00, $reserva->falta);
        $this->assertSame(0.0, $reserva->concluido_pct);
        $this->assertSame('1000.00', $reserva->aporte_mensal);
        $this->assertNull($reserva->prazo);

        $this->assertSame('7000.00', $viagem->valor_alvo);
        $this->assertNotNull($viagem->prazo);

        $this->assertSame(0.0, $metas['resumo']['progresso_medio_pct']);
    }

    public function test_contas_fixas_nao_entram_nos_totais_do_mes(): void
    {
        $contas = $this->servico->contasFixas($this->user->tenant_id);

        $this->assertCount(9, $contas['itens']);
        $this->assertSame('Internet Claro', $contas['itens']->first()->conta, 'ordenadas por dia de vencimento');

        $energia = $contas['itens']->firstWhere('conta', 'Energia');
        $this->assertSame(14, $energia->dia_vencimento);
        $this->assertSame('340.00', $energia->valor_previsto);
        $this->assertSame('303.36', $energia->valor_realizado);
        $this->assertSame('concluido', $energia->status);

        PlanContaFixa::withoutGlobalScopes()->get()->each->delete();
        $this->assertSame(12225.10, $this->agosto()['despesas']['realizado']);
    }

    public function test_visao_anual_soma_os_meses(): void
    {
        $anual = $this->servico->anual($this->user->tenant_id, 2026);

        $this->assertCount(12, $anual['meses']);
        $this->assertSame('2026-08', $anual['meses'][7]['mes']);
        $this->assertSame(17632.86, $anual['meses'][7]['receitas']['realizado']);
        $this->assertSame(0.0, $anual['meses'][0]['receitas']['realizado']);
        // Na planilha o Planejamento Anual dá 0 por defeito de fórmula; o correto é a soma dos meses.
        $this->assertSame(17632.86, $anual['total']['receitas']['realizado']);
        $this->assertSame(5407.76, $anual['total']['saldo']['realizado']);
    }

    public function test_mes_sem_lancamentos_fica_zerado_sem_erro(): void
    {
        $r = $this->servico->resumoMes($this->user->tenant_id, Carbon::create(2026, 9, 1));

        $this->assertSame(['previsto' => 0.0, 'realizado' => 0.0], $r['receitas']);
        $this->assertSame(0.0, $r['economia_pct']);
        $this->assertSame(0.0, $r['comprometimento_pct']);
        $this->assertSame([], $r['por_categoria']);
    }

    public function test_lancamentos_do_mes_mostram_previsto_realizado_e_diferenca(): void
    {
        $lancamentos = collect($this->servico->lancamentos($this->user->tenant_id, Carbon::create(2026, 8, 1)));
        $salario     = $lancamentos->firstWhere('descricao', 'Salário (Gdl)');

        $this->assertCount(52, $lancamentos);
        $this->assertSame(1200.00, $salario['valor_previsto']);
        $this->assertSame(1571.00, $salario['valor_realizado']);
        $this->assertSame(371.00, $salario['diferenca']);
        $this->assertSame('planilha', $salario['origem']);
        $this->assertFalse($salario['editavel']);

        $this->assertCount(13, $this->servico->lancamentos($this->user->tenant_id, Carbon::create(2026, 8, 1), 'receita'));
    }

    public function test_lancamento_manual_soma_no_mes_e_aparece_com_a_origem(): void
    {
        $this->actingAs($this->user);
        $banco = Banco::create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Carteira', 'eh_dinheiro' => true, 'saldo' => 500]);
        Despesa::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_pagamento' => $banco->id,
            'tipo_pagamento' => 'dinheiro', 'valor' => 100, 'data_compra' => '2026-08-15', 'data_pagamento' => '2026-08-15',
            'observacoes' => 'Feira',
        ]);
        Receita::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_recebimento' => $banco->id,
            'valor' => 200, 'data_prevista_recebimento' => '2026-08-20', 'data_recebimento' => null,
        ]);

        $r = $this->agosto();
        $this->assertSame(12325.10, $r['despesas']['realizado']);
        $this->assertSame(12317.44, $r['despesas']['previsto']);
        $this->assertSame(17461.86, $r['receitas']['previsto']);
        $this->assertSame(17632.86, $r['receitas']['realizado'], 'receita ainda não recebida não conta como realizada');

        $feira = collect($this->servico->lancamentos($this->user->tenant_id, Carbon::create(2026, 8, 1)))->firstWhere('descricao', 'Feira');
        $this->assertSame('manual', $feira['origem']);
        $this->assertTrue($feira['editavel']);
    }

    public function test_baixa_com_valor_diferente_preserva_o_previsto(): void
    {
        $this->actingAs($this->user);
        $banco   = Banco::create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Conta', 'tem_conta_corrente' => true, 'saldo' => 2000]);
        $despesa = Despesa::create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'forma_pagamento' => $banco->id,
            'tipo_pagamento' => 'pix', 'valor' => 800, 'data_compra' => '2026-08-05', 'data_pagamento' => null,
        ]);

        $this->post(route('fluxo-caixa.baixar-despesa', $despesa), ['data_pagamento' => '2026-08-05', 'valor' => 750])
            ->assertSessionHasNoErrors();

        $despesa->refresh();
        $this->assertSame('750.00', $despesa->valor);
        $this->assertSame('800.00', $despesa->valor_previsto);
        $this->assertEqualsWithDelta(1250.00, (float) $banco->fresh()->saldo, 0.001);

        $r = $this->agosto();
        $this->assertSame(13017.44, $r['despesas']['previsto']);
        $this->assertSame(12975.10, $r['despesas']['realizado']);

        // Estorno devolve o saldo pelo que foi pago e o lançamento ao valor previsto.
        $this->post(route('fluxo-caixa.estornar-despesa', $despesa))->assertSessionHasNoErrors();
        $despesa->refresh();
        $this->assertSame('800.00', $despesa->valor);
        $this->assertNull($despesa->valor_previsto);
        $this->assertEqualsWithDelta(2000.00, (float) $banco->fresh()->saldo, 0.001);
    }
}
