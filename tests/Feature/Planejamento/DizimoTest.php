<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Dízimo: 10% das receitas da base, no domingo da semana em que entraram. */
class DizimoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $n = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-12 09:00:00'); // segunda-feira
        $this->user = User::factory()->create();
        $this->l('2026-10-01', 'receita', 'Ticket alimentação (Ju)', 'Renda fixa', 634, true);       // qui → dom 04/10
        $this->l('2026-10-04', 'receita', 'Adiantamento (Gdl)', 'Renda fixa', 100, true);           // no próprio domingo
        $this->l('2026-10-05', 'receita', 'Vixcard', 'Renda variável', 7500, true);                // seg → dom 11/10
        $this->l('2026-10-08', 'receita', 'Reembolso Hostinger', 'Reembolso', 300, true);          // fora da base
        $this->l('2026-10-20', 'receita', 'Venda casa (Par. 18 de 60)', 'Venda de imóvel', 1416.66, false); // fora
        $this->l('2026-10-10', 'receita', 'Pró-labore (Grupo soluções)', 'Renda fixa', 3000, false); // atrasada → dom 18/10
        $this->l('2026-10-30', 'receita', 'Salário (Gdl)', 'Renda fixa', 1200, false);             // sex → dom 01/11
        $this->l('2026-10-02', 'despesa', 'Devolução dízimo', 'Devolução Dízimo', 70, true);
        $this->l('2026-10-06', 'despesa', 'Devolução dízimo', 'Devolução Dízimo', 750, true);
    }

    private function l(string $data, string $tipo, string $desc, string $cat, float $valor, bool $feito): void
    {
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => 'k' . $this->n, 'conteudo_hash' => 'x', 'linha' => $this->n++,
            'data' => $data, 'tipo' => $tipo, 'descricao' => $desc, 'categoria' => $cat, 'forma' => 'pix', 'conta' => 'Sicoob',
            'valor_previsto' => $valor, 'valor_realizado' => $feito ? $valor : null, 'status' => $feito ? 'concluido' : 'pendente',
        ]);
    }

    public function test_calcula_o_dizimo_de_outubro_por_domingo(): void
    {
        $d = app(PlanejamentoService::class)->dizimo($this->user->tenant_id, Carbon::parse('2026-10-01'));

        $this->assertSame([8234.0, 823.4], [(float) $d['base_recebida'], (float) $d['dizimo_devido']]);
        $this->assertSame([11234.0, 1123.4], [(float) $d['base_prevista'], (float) $d['dizimo_previsto']]);
        $this->assertSame([820.0, 3.4], [(float) $d['devolvido'], (float) $d['falta']]);
        $this->assertSame([
            ['2026-10-04', 73.4, 'devolvido'],
            ['2026-10-11', 750.0, 'atrasado'],
            ['2026-10-18', 300.0, 'pendente'],
        ], array_map(fn ($x) => [$x['data'], (float) $x['valor'], $x['situacao']], $d['domingos']));
    }

    public function test_receita_do_fim_do_mes_vai_para_o_domingo_do_mes_seguinte(): void
    {
        $d = app(PlanejamentoService::class)->dizimo($this->user->tenant_id, Carbon::parse('2026-11-01'));
        $this->assertSame([['2026-11-01', 120.0]], array_map(fn ($x) => [$x['data'], (float) $x['valor']], $d['domingos']));
        $this->assertSame(0.0, (float) $d['devolvido']);
    }

    public function test_tela_do_mes_e_api(): void
    {
        $this->actingAs($this->user)->get(route('planejamento.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Dízimo de outubro')->assertSee('R$ 823,40')->assertSee('R$ 820,00')->assertSee('R$ 3,40')
            ->assertSeeInOrder(['Dom 04/10', 'Devolvido', 'Dom 11/10', 'Atrasado', 'Dom 18/10', 'Pendente']);

        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/planejamento/dizimo?mes=2026-10')->assertOk()
            ->assertJsonPath('data.dizimo_devido', 823.4)->assertJsonPath('data.domingos.1.situacao', 'atrasado');
    }

    public function test_outra_familia_nao_ve_o_dizimo_desta(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/planejamento/dizimo?mes=2026-10')->assertOk()
            ->assertJsonPath('data.base_recebida', 0)->assertJsonPath('data.domingos', []);
    }
}
