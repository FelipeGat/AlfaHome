<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class LancamentosTelaTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        app(PlanilhaImportService::class)->importar($this->user->tenant_id, $this->user->id, $this->planilha(), 'p.xlsx');
        $this->actingAs($this->user);
    }

    private function tela(array $q = [])
    {
        return $this->get(route('planejamento.lancamentos', ['mes' => '2026-08'] + $q))->assertOk();
    }

    public function test_totais_do_mes_batem_com_a_planilha(): void
    {
        $r = $this->tela();
        $totais = $r->viewData('totais');

        // Mesmos números do Previsto x Realizado de agosto/2026.
        $this->assertSame(17632.86, $totais['entradas']);
        $this->assertSame(12225.10, $totais['saidas']);
        $this->assertSame(PlanLancamento::whereBetween('data', ['2026-08-01', '2026-08-31'])->count(), $r->viewData('qtd'));
        $r->assertSee('Agosto de 2026')->assertSee('R$ 17.632,86')->assertSee('Os da planilha mudam no Excel.');
    }

    public function test_filtros_de_tipo_situacao_categoria_e_conta(): void
    {
        $agosto = PlanLancamento::whereBetween('data', ['2026-08-01', '2026-08-31']);

        $this->assertSame((clone $agosto)->where('tipo', 'receita')->count(), $this->tela(['tipo' => 'receita'])->viewData('qtd'));
        $this->assertSame((clone $agosto)->where('status', 'pendente')->count(), $this->tela(['situacao' => 'pendente'])->viewData('qtd'));

        $categoria = (clone $agosto)->whereNotNull('categoria')->value('categoria');
        $this->assertSame((clone $agosto)->where('categoria', $categoria)->count(), $this->tela(['categoria' => $categoria])->viewData('qtd'));

        $conta = (clone $agosto)->whereNotNull('conta')->value('conta');
        $this->assertSame((clone $agosto)->where('conta', $conta)->count(), $this->tela(['conta' => $conta])->viewData('qtd'));

        // Os totais do mês não mudam com o filtro; só a lista.
        $this->assertSame(17632.86, $this->tela(['tipo' => 'despesa'])->viewData('totais')['entradas']);
    }

    public function test_busca_ignora_acento_e_maiuscula(): void
    {
        $l = PlanLancamento::whereBetween('data', ['2026-08-01', '2026-08-31'])->first();
        $l->update(['descricao' => 'Farmácia São João']);

        $r = $this->tela(['q' => 'FARMACIA sao']);
        $this->assertSame(1, $r->viewData('qtd'));
        $r->assertSee('Farmácia São João');
    }

    public function test_mes_vazio_explica(): void
    {
        $this->get(route('planejamento.lancamentos', ['mes' => '2030-01']))->assertOk()
            ->assertSee('Nenhum lançamento neste mês.');
        $this->tela(['q' => 'nao-existe-xyz'])->assertSee('Nenhum lançamento com esses filtros.');
    }

    public function test_menu_sem_telas_manuais_e_enderecos_antigos_levam_ao_extrato(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee(route('planejamento.lancamentos'), false)
            ->assertDontSee('data-label="Despesas"', false)
            ->assertDontSee('data-label="Receitas"', false)
            // Menu: Início, Extrato e Contas no topo; o resto em Planejamento e Mais.
            ->assertSeeInOrder(['data-label="Início"', 'data-label="Extrato"', 'data-label="Contas"', 'Planejamento', 'Mais', 'data-label="Planilha"'], false);

        // Endereços antigos levam ao Extrato.
        $this->get('/despesas')->assertRedirect('/planejamento/lancamentos');
        $this->get('/fluxo-caixa')->assertRedirect('/planejamento/lancamentos');
    }

    public function test_outra_familia_nao_ve_lancamentos_desta(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertSame(0, $this->tela()->viewData('qtd'));
    }
}
