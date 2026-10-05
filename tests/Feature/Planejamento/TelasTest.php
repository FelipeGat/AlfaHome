<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class TelasTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private const TELAS = ['planejamento.index', 'planejamento.anual', 'planejamento.cartoes', 'planejamento.dividas', 'planejamento.metas', 'planejamento.contas-fixas', 'planejamento.importar'];

    private function comPlanilha(): User
    {
        $user = User::factory()->create();
        app(PlanilhaImportService::class)->importar($user->tenant_id, $user->id, $this->planilha(), 'Planejamento.xlsx');

        return $user;
    }

    public function test_visitante_e_mandado_para_o_login(): void
    {
        foreach (self::TELAS as $tela) {
            $this->get(route($tela))->assertRedirect(route('login'));
        }
    }

    public function test_telas_abrem_sem_planilha_importada(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (self::TELAS as $tela) {
            $this->get(route($tela))->assertOk();
        }
        $this->get(route('planejamento.index'))->assertSee('Nenhum lançamento neste mês.')->assertSee('A planilha ainda não foi importada');
        $this->get(route('planejamento.dividas'))->assertSee('Nenhuma dívida na planilha.');
        $this->get(route('planejamento.metas'))->assertSee('Nenhuma meta na planilha.');
    }

    public function test_mes_mostra_os_numeros_da_planilha(): void
    {
        $this->actingAs($this->comPlanilha());

        $this->get(route('planejamento.index', ['mes' => '2026-08']))->assertOk()
            ->assertSee('Agosto de 2026')
            ->assertSee('R$ 17.632,86')
            ->assertSee('R$ 17.261,86')
            ->assertSee('R$ 12.225,10')
            ->assertSee('R$ 12.217,44')
            ->assertSee('R$ 5.407,76')
            ->assertSee('30,67%')
            ->assertSee('69,33%')
            ->assertSee('Salário (Gdl)')
            ->assertSee('+371,00')
            ->assertSee('Tarifa báncaria')
            ->assertSee(route('planejamento.lancamentos', ['mes' => '2026-08']), false)
            ->assertSee('última importação em')
            ->assertDontSee('Nenhum lançamento neste mês.');
    }

    public function test_sem_mes_escolhido_abre_o_ultimo_mes_com_lancamento(): void
    {
        $this->actingAs($this->comPlanilha());
        $this->travelTo('2026-10-03');

        // Outubro não tem lançamento na planilha; o último mês com dados é agosto.
        $this->get(route('planejamento.index'))->assertOk()->assertSee('Agosto de 2026')->assertSee('R$ 17.632,86');

        $this->travelTo('2026-08-20');
        $this->get(route('planejamento.index'))->assertSee('Agosto de 2026');

        $this->travelTo('2027-02-01');
        $this->get(route('planejamento.anual'))->assertSee('Total 2026');
    }

    public function test_mes_invalido_cai_no_mes_padrao_sem_erro(): void
    {
        $this->actingAs($this->comPlanilha());

        $this->get(route('planejamento.index', ['mes' => 'abc']))->assertOk();
        $this->get(route('planejamento.index', ['mes' => '2026-13']))->assertOk();
        $this->get(route('planejamento.anual', ['ano' => 'x']))->assertOk();
    }

    public function test_anual_cartoes_dividas_metas_e_contas_fixas(): void
    {
        $this->actingAs($this->comPlanilha());

        $this->get(route('planejamento.anual', ['ano' => 2026]))->assertOk()
            ->assertSee('Ago/2026')->assertSee('Total 2026')->assertSee('R$ 17.632,86')->assertSee('R$ 5.407,76');

        $this->get(route('planejamento.cartoes'))->assertOk()
            ->assertSee('Cartão Sicoob')->assertSee('R$ 23.593,00')->assertSee('R$ 21.737,85')->assertSee('R$ 3.921,44')
            ->assertSee('92,64%')->assertSee('Cartão celebre')
            ->assertSee('Celular Iphone')->assertSee('10 x 519,90')->assertSee('R$ 2.079,60')
            ->assertSee('Cartão Mercado Pago')->assertSee('Este cartão não está na aba Cartões da planilha');

        $this->get(route('planejamento.dividas'))->assertOk()
            ->assertSee('Empréstimo empresa')->assertSee('R$ 6.744,29')->assertSee('R$ 15.000,00')
            ->assertSee('R$ 8.255,71')->assertSee('5,2%')->assertSee('R$ 720,21')->assertSee('R$ 10.174,66')
            ->assertSee('Prioridade alta')->assertSee('19 parcelas');

        $this->get(route('planejamento.metas'))->assertOk()
            ->assertSee('Reserva de emergência')->assertSee('6 meses de despesas')->assertSee('R$ 30.000,00')
            ->assertSee('Viagem')->assertSee('R$ 7.000,00')->assertSee('0,00% concluído');

        $this->get(route('planejamento.contas-fixas'))->assertOk()
            ->assertSeeInOrder(['Internet Claro', 'Água', 'Pensão', 'Aluguel', 'Energia', 'Celular Vivo (Ju)', 'Academia (Ju)'])
            ->assertSee('R$ 303,36');
    }

    public function test_registros_da_planilha_nao_tem_acao_de_edicao(): void
    {
        $this->actingAs($this->comPlanilha());

        $this->get(route('planejamento.index', ['mes' => '2026-08']))
            ->assertSee('são mantidos na planilha', false);

        // Não existe rota de escrita para os dados da planilha, na web nem na API:
        // só gravam as que trazem a planilha (importar, analisar e o link dela).
        $escrita = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'planejamento') && array_diff($r->methods(), ['GET', 'HEAD']))
            ->map(fn ($r) => $r->uri())->unique()->sort()->values()->all();
        $this->assertSame([
            'api/v1/planejamento/analisar', 'api/v1/planejamento/importar',
            'planejamento/analisar', 'planejamento/fonte', 'planejamento/importar',
        ], $escrita);

        $lancamento = PlanLancamento::first();
        $this->put('/planejamento/' . $lancamento->id, [])->assertNotFound();
        $this->delete('/planejamento/' . $lancamento->id)->assertNotFound();
    }

    public function test_painel_inicial_com_planilha_mostra_o_painel_do_dia_sem_resumo_repetido(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Planejamento do mês');

        $this->actingAs($this->comPlanilha());
        $this->get(route('dashboard', ['inicio' => '2026-08-01', 'fim' => '2026-08-31']))->assertOk()
            // Com a planilha, o painel do dia é o Dashboard: sem o resumo repetido.
            ->assertSee('Saldo em contas')->assertSee('Próximos 7 dias')
            ->assertDontSee('Planejamento do mês');
    }

    public function test_importar_pela_tela(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('planejamento.importar.store'), ['arquivo' => new UploadedFile($this->planilha(), 'Planejamento.xlsx', null, null, true)])
            ->assertRedirect(route('planejamento.importar'))
            ->assertSessionHas('success', 'Planilha importada.');

        $this->get(route('planejamento.importar'))->assertOk()
            ->assertSee('Planejamento.xlsx')->assertSee('Lançamentos')->assertSee('Compras Parceladas')
            ->assertSee('O cartão "Cartão Mercado Pago" não está na aba Cartões.');

        $this->post(route('planejamento.importar.store'), ['arquivo' => new UploadedFile($this->planilha(), 'Planejamento.xlsx', null, null, true)])
            ->assertSessionHas('success', 'A planilha já estava em dia: nenhuma alteração.');

        $this->assertSame(52, PlanLancamento::count());
    }

    public function test_planilha_invalida_mostra_o_motivo_e_nao_altera_nada(): void
    {
        $user = $this->comPlanilha();
        $this->actingAs($user);

        $this->post(route('planejamento.importar.store'), ['arquivo' => new UploadedFile($this->planilhaComTexto('Dívidas', 'D4', 'muito'), 'errada.xlsx', null, null, true)])
            ->assertRedirect(route('planejamento.importar'))
            ->assertSessionHas('error');

        $this->get(route('planejamento.importar'))
            ->assertSee('Nada foi alterado', false)
            ->assertSee('Dívidas, linha 4:', false)
            ->assertSee('A coluna "Saldo Atual" deveria ser um número, mas contém "muito".');

        $this->get(route('planejamento.dividas'))->assertSee('R$ 6.744,29');

        $this->post(route('planejamento.importar.store'), [])->assertSessionHasErrors(['arquivo' => 'Escolha o arquivo da planilha.']);
    }

    public function test_so_o_dono_da_conta_importa(): void
    {
        $dono   = User::factory()->create();
        $membro = User::factory()->create(['tenant_id' => $dono->tenant_id, 'role' => 'membro']);
        $this->actingAs($membro);

        $this->get(route('planejamento.importar'))->assertStatus(403);
        $this->post(route('planejamento.importar.store'), ['arquivo' => new UploadedFile($this->planilha(), 'p.xlsx', null, null, true)])->assertStatus(403);
        $this->assertSame(0, PlanLancamento::withoutGlobalScopes()->count());

        // Ver, ele vê.
        $this->get(route('planejamento.index'))->assertOk()->assertDontSee('Importar Planilha');
    }

    public function test_outra_familia_nao_ve_os_dados(): void
    {
        $this->comPlanilha();
        $this->actingAs(User::factory()->create());

        $this->get(route('planejamento.index', ['mes' => '2026-08']))->assertOk()->assertDontSee('Salário (Gdl)')->assertDontSee('17.632,86');
        $this->get(route('planejamento.dividas'))->assertDontSee('Empréstimo empresa');
        $this->get(route('planejamento.metas'))->assertDontSee('Reserva de emergência');
        $this->get(route('planejamento.cartoes'))->assertDontSee('Cartão Sicoob');
        $this->get(route('planejamento.contas-fixas'))->assertDontSee('Internet Claro');
        $this->get(route('planejamento.importar'))->assertDontSee('Planejamento.xlsx');
    }
}
