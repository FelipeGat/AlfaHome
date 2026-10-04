<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanContaFixa;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanejamentoService;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class VencimentosTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-04 10:00:00');
        $this->user = User::factory()->create();
        app(PlanilhaImportService::class)->importar($this->user->tenant_id, $this->user->id, $this->planilha(), 'p.xlsx');
    }

    private function lancamento(string $tipo, string $data, string $descricao, ?float $valor): void
    {
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1($descricao . $data), 'conteudo_hash' => sha1('x'), 'linha' => 300,
            'data' => $data, 'tipo' => $tipo, 'descricao' => $descricao, 'valor_previsto' => $valor, 'status' => 'pendente',
        ]);
    }

    private function vencimentos(): array
    {
        return app(PlanejamentoService::class)->vencimentos($this->user->tenant_id);
    }

    public function test_faturas_abertas_e_parcelas_de_dividas_entram_em_a_pagar_no_proximo_vencimento(): void
    {
        $v = $this->vencimentos();

        // Na planilha de referência tudo está pago: nada atrasado, nada a receber.
        $this->assertSame([], $v['atrasado']);
        $this->assertSame([], $v['a_receber']);

        $this->assertSame([
            ['Parcela Empréstimo empresa', 720.21, '2026-10-05'],
            ['Fatura Cartão Banco do Brasil', 1628.33, '2026-10-13'],
            ['Fatura Cartão Sicoob', 3921.44, '2026-10-22'],
            ['Parcela Empréstimo pedreiro', 877.97, '2026-10-30'],
        ], array_map(fn ($i) => [$i['descricao'], $i['valor'], $i['data']], $v['a_pagar']));
        $this->assertSame(7147.95, $v['totais']['a_pagar']);

        // Cartão sem fatura informada (Celebre) não aparece; compra parcelada também não.
        $this->assertStringNotContainsString('Celebre', json_encode($v, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Iphone', json_encode($v, JSON_UNESCAPED_UNICODE));
    }

    public function test_vencimento_que_ja_passou_no_mes_vai_para_o_mes_seguinte(): void
    {
        $this->travelTo('2026-10-14 09:00:00');

        $datas = collect($this->vencimentos()['a_pagar'])->pluck('data', 'descricao');

        $this->assertSame('2026-11-05', $datas['Parcela Empréstimo empresa']);
        $this->assertSame('2026-11-13', $datas['Fatura Cartão Banco do Brasil']);
        $this->assertSame('2026-10-22', $datas['Fatura Cartão Sicoob']);
    }

    public function test_lancamentos_pendentes_viram_atrasado_a_pagar_ou_a_receber_pela_data(): void
    {
        $this->lancamento('despesa', '2026-09-20', 'Boleto esquecido', 150.00);
        $this->lancamento('receita', '2026-09-25', 'Cliente devendo', 800.00);
        $this->lancamento('despesa', '2026-10-04', 'Vence hoje', 40.00);
        $this->lancamento('despesa', '2026-10-20', 'Escola', 500.00);
        $this->lancamento('receita', '2026-10-28', 'Salário', 3000.00);
        $this->lancamento('despesa', '2026-09-30', 'Sem valor na planilha', null);

        $v = $this->vencimentos();

        $this->assertSame(['Boleto esquecido', 'Cliente devendo', 'Sem valor na planilha'], array_column($v['atrasado'], 'descricao'));
        $this->assertSame(950.00, $v['totais']['atrasado']);
        $this->assertNull($v['atrasado'][2]['valor'], 'valor vazio na planilha continua vazio');
        $this->assertSame('receita', $v['atrasado'][1]['tipo']);

        $this->assertContains('Vence hoje', array_column($v['a_pagar'], 'descricao'), 'o que vence hoje ainda não está atrasado');
        $this->assertContains('Escola', array_column($v['a_pagar'], 'descricao'));
        $this->assertSame(['Salário'], array_column($v['a_receber'], 'descricao'));
        $this->assertSame(3000.00, $v['totais']['a_receber']);
    }

    public function test_conta_fixa_pendente_atrasa_depois_do_dia_de_vencimento(): void
    {
        PlanContaFixa::withoutGlobalScopes()->where('conta', 'Internet Claro')->first()->update(['status' => 'pendente']); // dia 5
        PlanContaFixa::withoutGlobalScopes()->where('conta', 'Água')->first()->update(['status' => 'pendente']);           // dia 7

        $this->travelTo('2026-10-06 09:00:00');
        $v = $this->vencimentos();

        $this->assertSame(['Internet Claro'], array_column($v['atrasado'], 'descricao'));
        $this->assertSame('2026-10-05', $v['atrasado'][0]['data']);
        $this->assertContains('Água', array_column($v['a_pagar'], 'descricao'));
    }

    public function test_tela_de_alertas_mostra_os_tres_grupos_e_nao_diz_tudo_em_ordem(): void
    {
        $this->lancamento('despesa', '2026-09-20', 'Boleto esquecido', 150.00);
        $this->actingAs($this->user);

        $this->get(route('alertas.index'))->assertOk()
            ->assertSeeInOrder(['Atrasado', 'Boleto esquecido', 'A pagar', 'Fatura Cartão Sicoob', 'A receber', 'Nada a receber.'])
            ->assertSee('R$ 150,00')->assertSee('R$ 7.147,95')->assertSee('22/10/2026')
            ->assertDontSee('Tudo em ordem!');
    }

    public function test_sem_planilha_a_tela_continua_dizendo_tudo_em_ordem(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('alertas.index'))->assertOk()
            ->assertSee('Nada atrasado.')->assertSee('Nada a pagar.')->assertSee('Tudo em ordem!');
    }

    public function test_contas_baixas_saiu_do_menu(): void
    {
        $this->actingAs($this->user);

        $this->get(route('dashboard'))->assertOk()->assertDontSee('Contas / Baixas');
    }

    public function test_api_devolve_os_vencimentos_so_da_familia(): void
    {
        $api = fn (User $u) => $this->withHeader('Authorization', 'Bearer ' . $u->createToken('app')->plainTextToken);

        $api($this->user)->getJson('/api/v1/planejamento/vencimentos')->assertOk()
            ->assertJsonStructure(['data' => ['atrasado', 'a_pagar' => [['origem', 'tipo', 'descricao', 'valor', 'data', 'detalhe']], 'a_receber', 'totais' => ['atrasado', 'a_pagar', 'a_receber']]])
            ->assertJsonPath('data.totais.a_pagar', 7147.95)
            ->assertJsonPath('data.a_pagar.0.origem', 'divida');

        $this->app['auth']->forgetGuards();
        $api(User::factory()->create())->getJson('/api/v1/planejamento/vencimentos')->assertOk()
            ->assertJsonPath('data.a_pagar', [])->assertJsonPath('data.totais.a_pagar', 0);
    }
}
