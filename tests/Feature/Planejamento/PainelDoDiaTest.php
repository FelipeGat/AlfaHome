<?php

namespace Tests\Feature\Planejamento;

use App\Models\Banco;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanejamentoService;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class PainelDoDiaTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-04 08:00:00');
        $this->user = User::factory()->create();
        app(PlanilhaImportService::class)->importar($this->user->tenant_id, $this->user->id, $this->planilha(), 'p.xlsx');

        $conta = fn (array $a) => Banco::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id] + $a);
        $conta(['nome' => 'Sicoob', 'tem_conta_corrente' => true, 'tem_cartao_credito' => true, 'saldo' => -189.02]);
        $conta(['nome' => 'Itaú', 'tem_conta_corrente' => true, 'saldo' => 35.42]);
        $conta(['nome' => 'Mercado Pago', 'tem_conta_corrente' => true, 'saldo' => 55.45]);
        $conta(['nome' => 'Celebre', 'tem_conta_corrente' => false, 'tem_cartao_credito' => true, 'saldo' => 0]);
    }

    private function pendente(string $tipo, string $data, string $desc, float $valor): void
    {
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1($desc), 'conteudo_hash' => sha1('x'), 'linha' => 300,
            'data' => $data, 'tipo' => $tipo, 'descricao' => $desc, 'valor_previsto' => $valor, 'status' => 'pendente',
        ]);
    }

    private function hoje(): array
    {
        return app(PlanejamentoService::class)->hoje($this->user->tenant_id);
    }

    public function test_saldo_em_contas_soma_so_as_contas_e_nao_o_cartao(): void
    {
        $h = $this->hoje();

        $this->assertSame(-98.15, $h['contas']['total']);
        $this->assertSame(['Itaú', 'Mercado Pago', 'Sicoob'], array_column($h['contas']['itens'], 'nome'));
        $this->assertTrue($h['planilha_importada']);
    }

    public function test_proximos_7_dias_e_fim_do_mes(): void
    {
        $this->pendente('receita', '2026-10-06', 'Adiantamento (Gdl)', 892.85);
        $this->pendente('despesa', '2026-10-25', 'Escola', 500.00);

        $h = $this->hoje();

        // Da planilha: parcela do empréstimo da empresa em 05/10; faturas do Sicoob (22/10) e BB (13/10) ficam fora da semana.
        $this->assertSame(['Parcela Empréstimo empresa'], array_column($h['proximos_7_dias']['a_pagar'], 'descricao'));
        $this->assertSame(720.21, $h['proximos_7_dias']['total_pagar']);
        $this->assertSame(['Adiantamento (Gdl)'], array_column($h['proximos_7_dias']['a_receber'], 'descricao'));

        // Até 31/10: 720,21 + 1.628,33 + 3.921,44 + 877,97 + 500,00 = 7.647,95.
        $this->assertSame(7647.95, $h['ate_fim_do_mes']['a_pagar']);
        $this->assertSame(892.85, $h['ate_fim_do_mes']['a_receber']);
        $this->assertSame(round(-98.15 + 892.85 - 7647.95, 2), $h['ate_fim_do_mes']['projecao_saldo']);

        $this->assertSame(['descricao' => 'Fatura Cartão Banco do Brasil', 'valor' => 1628.33, 'data' => '2026-10-13'], $h['cartoes']['proxima_fatura']);
        $this->assertSame(1855.15, $h['cartoes']['limite_disponivel']);
    }

    public function test_atrasado_entra_no_total_e_na_projecao(): void
    {
        $this->pendente('despesa', '2026-09-28', 'Boleto esquecido', 150.00);

        $h = $this->hoje();

        $this->assertSame(150.00, $h['atrasado']['total_pagar']);
        $this->assertSame('Boleto esquecido', $h['atrasado']['itens'][0]['descricao']);
        $this->assertSame(7297.95, $h['ate_fim_do_mes']['a_pagar'], 'atrasado entra no que precisa sair até o fim do mês');
    }

    public function test_mes_igual_ao_planejamento(): void
    {
        $this->travelTo('2026-08-31 09:00:00');

        $h = $this->hoje();

        $this->assertSame(['mes' => '2026-08', 'receitas' => 17632.86, 'despesas' => 12225.10, 'saldo' => 5407.76], $h['mes']);
    }

    public function test_api_devolve_o_painel_so_da_familia(): void
    {
        $api = fn (User $u) => $this->withHeader('Authorization', 'Bearer ' . $u->createToken('app')->plainTextToken);

        $api($this->user)->getJson('/api/v1/planejamento/hoje')->assertOk()
            ->assertJsonPath('data.contas.total', -98.15)
            ->assertJsonPath('data.proximos_7_dias.total_pagar', 720.21)
            ->assertJsonStructure(['data' => ['data', 'contas' => ['total', 'itens'], 'atrasado' => ['total_pagar', 'total_receber', 'itens'],
                'proximos_7_dias' => ['a_pagar', 'a_receber', 'total_pagar', 'total_receber'], 'ate_fim_do_mes' => ['a_pagar', 'a_receber', 'projecao_saldo'],
                'mes' => ['mes', 'receitas', 'despesas', 'saldo'], 'cartoes' => ['limite_disponivel', 'utilizado_pct', 'proxima_fatura'], 'planilha_importada']]);

        $this->app['auth']->forgetGuards();
        $api(User::factory()->create())->getJson('/api/v1/planejamento/hoje')->assertOk()
            ->assertJsonPath('data.contas.total', 0)->assertJsonPath('data.planilha_importada', false)
            ->assertJsonPath('data.proximos_7_dias.a_pagar', []);
    }

    public function test_dashboard_web_abre_com_o_painel_do_dia(): void
    {
        $this->pendente('despesa', '2026-09-28', 'Boleto esquecido', 150.00);
        $this->actingAs($this->user);

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeInOrder(['Bom dia', 'Saldo total', '-R$ 98,15', 'Próximos pagamentos', 'Boleto esquecido', 'Atrasado', 'Últimas movimentações'])
            ->assertSee('Mercado Pago')->assertDontSee('Nenhuma conta corrente cadastrada');
    }

    public function test_inicio_escolhe_o_mes_do_resumo_e_mostra_so_dados_reais(): void
    {
        $this->pendente('despesa', '2026-09-28', 'Boleto esquecido', 150.00);
        $this->actingAs($this->user);

        // Agosto da planilha: R$ 17.632,86 entrou (o mesmo do Previsto x Realizado).
        $this->get(route('dashboard', ['mes' => '2026-08']))->assertOk()
            ->assertSee('resumo das suas finanças em agosto')
            ->assertSee('+R$ 17.632,86')
            ->assertSee('Onde você gastou')
            ->assertSee('1 conta vencida')
            ->assertSee('Ocultar valores')
            // Sem comparação histórica calculada, nada de percentual de crescimento.
            ->assertDontSee('em relação ao mês anterior');

        $this->get(route('dashboard', ['mes' => 'qualquer']))->assertOk()->assertSee('resumo das suas finanças em outubro');
    }
}
