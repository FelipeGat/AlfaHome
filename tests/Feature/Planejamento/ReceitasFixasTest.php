<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanContaFixa;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanejamentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Receitas fixas na aba Contas Fixas (pela categoria) e a conferência do que não foi lançado no mês. */
class ReceitasFixasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $n = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-12 09:00:00');
        $this->user = User::factory()->create();
        $this->fixa('Vixcard', 'Renda variável', 5, 7500, 'concluido');
        $this->fixa('Pró-labore (Grupo soluções)', 'Renda fixa', 10, 3000, 'pendente');
        $this->fixa('Celular Vivo (Ju)', 'Assinaturas', 17, 72.96, 'pendente');
        $this->fixa('Academia (Fe)', 'Saúde', 22, 167, 'pendente');
        $this->lanc('2026-11-05', 'receita', 'Vixcard', 7500);
        $this->lanc('2026-11-22', 'despesa', 'Wellness Club - Fe (academia)', 167);
    }

    private function fixa(string $conta, string $cat, int $dia, float $valor, string $status): void
    {
        PlanContaFixa::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'chave' => 'f' . $this->n, 'conteudo_hash' => 'x', 'linha' => $this->n++,
            'conta' => $conta, 'categoria' => $cat, 'dia_vencimento' => $dia, 'valor_previsto' => $valor, 'status' => $status, 'mes_inicial' => '2026-08-01']);
    }

    private function lanc(string $data, string $tipo, string $desc, float $valor): void
    {
        PlanLancamento::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'chave' => 'l' . $this->n, 'conteudo_hash' => 'x', 'linha' => $this->n++,
            'data' => $data, 'tipo' => $tipo, 'descricao' => $desc, 'valor_previsto' => $valor, 'status' => 'pendente']);
    }

    public function test_separa_receitas_e_despesas_fixas(): void
    {
        $r = app(PlanejamentoService::class)->contasFixas($this->user->tenant_id);
        $this->assertSame(['Vixcard', 'Pró-labore (Grupo soluções)'], $r['receitas']->pluck('conta')->all());
        $this->assertSame(10500.0, $r['resumo']['receitas_total']);
        $this->assertSame(239.96, $r['resumo']['despesas_total']);

        $this->actingAs($this->user)->get(route('planejamento.contas-fixas'))->assertOk()
            ->assertSeeInOrder(['Receitas fixas', 'Vixcard', 'Despesas fixas', 'Celular Vivo (Ju)']);
    }

    public function test_receita_fixa_pendente_nao_vira_conta_a_pagar(): void
    {
        $v = app(PlanejamentoService::class)->vencimentos($this->user->tenant_id);
        $this->assertNotContains('Pró-labore (Grupo soluções)', array_column(array_merge($v['a_pagar'], $v['atrasado']), 'descricao'));
        $this->assertContains('Celular Vivo (Ju)', array_column($v['a_pagar'], 'descricao'));
    }

    public function test_avisa_contas_fixas_sem_lancamento_no_mes(): void
    {
        $faltando = app(PlanejamentoService::class)->contasFixasSemLancamento($this->user->tenant_id, Carbon::parse('2026-11-01'));
        $this->assertSame(['Pró-labore (Grupo soluções)', 'Celular Vivo (Ju)'], array_column($faltando, 'conta'));

        // O aviso fica no sininho (mês atual e o próximo).
        $this->actingAs($this->user)->getJson(route('avisos.index'))->assertOk()
            ->assertJsonFragment(['titulo' => '2 contas fixas não lançadas em novembro', 'detalhe' => 'Pró-labore (Grupo soluções), Celular Vivo (Ju)']);
        // Antes do mês inicial nada é cobrado.
        $this->assertSame([], app(PlanejamentoService::class)->contasFixasSemLancamento($this->user->tenant_id, Carbon::parse('2026-07-01')));
    }
}
