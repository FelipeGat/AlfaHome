<?php

namespace Tests\Feature\Financeiro;

use App\Models\Banco;
use App\Models\PlanLancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceiroApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Banco $sicoob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-05 09:00:00');
        $this->user   = User::factory()->create();
        $this->sicoob = Banco::withoutGlobalScopes()->create(['tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Sicoob', 'tem_conta_corrente' => true, 'saldo' => -189.02]);
        $this->travelTo('2026-10-05 12:00:00');
        foreach ([['despesa', 'Açougue', 96.85, 96.85], ['receita', 'Salário', 5000, 5000], ['despesa', 'Escola', 950, null]] as $i => [$tipo, $desc, $prev, $real]) {
            PlanLancamento::withoutGlobalScopes()->create([
                'tenant_id' => $this->user->tenant_id, 'chave' => sha1($desc), 'conteudo_hash' => sha1('x'), 'linha' => $i + 1,
                'data' => $real === null ? '2026-10-07' : '2026-10-05', 'tipo' => $tipo, 'descricao' => $desc, 'categoria' => 'Casa', 'conta' => 'Sicoob',
                'valor_previsto' => $prev, 'valor_realizado' => $real, 'status' => $real === null ? 'pendente' : 'concluido',
            ]);
        }
        Sanctum::actingAs($this->user);
    }

    public function test_inicio_traz_saldo_mes_ultimas_e_proximos(): void
    {
        $this->getJson('/api/v1/financeiro/inicio')->assertOk()
            ->assertJsonPath('data.contas.total', 4714.13)
            ->assertJsonPath('data.contas.itens.0.nome', 'Sicoob')
            ->assertJsonPath('data.mes.entrou', 5000)
            ->assertJsonPath('data.mes.saiu', 96.85)
            ->assertJsonPath('data.mes.resultado', 4903.15)
            ->assertJsonPath('data.ultimas.0.data', '2026-10-05')
            ->assertJsonPath('data.proximos.0.descricao', 'Escola')
            ->assertJsonPath('data.proximos.0.valor', 950);
    }

    public function test_resumo_do_mes_e_mes_vazio(): void
    {
        $this->getJson('/api/v1/financeiro/resumo?mes=2026-10')->assertOk()
            ->assertJsonPath('data.saiu', 96.85)->assertJsonPath('data.tem_movimentacao', true)
            ->assertJsonPath('data.categorias.0.categoria', 'Casa');
        $this->getJson('/api/v1/financeiro/resumo?mes=2027-01')->assertOk()
            ->assertJsonPath('data.tem_movimentacao', false)->assertJsonPath('data.categorias', []);
        $this->getJson('/api/v1/financeiro/resumo?mes=outubro')->assertStatus(422);
    }

    public function test_conta_e_ajuste(): void
    {
        $this->getJson("/api/v1/financeiro/contas/{$this->sicoob->id}")->assertOk()
            ->assertJsonPath('data.saldo', 4714.13)
            ->assertJsonCount(2, 'data.movimentos');

        $this->travel(1)->minutes();
        $this->postJson("/api/v1/financeiro/contas/{$this->sicoob->id}/ajustar", ['saldo' => 4700])->assertOk()
            ->assertJsonPath('data.diferenca', -14.13)->assertJsonPath('message', 'Saldo ajustado.');
        $this->getJson('/api/v1/financeiro/contas')->assertJsonPath('data.total', 4700);
        $this->postJson("/api/v1/financeiro/contas/{$this->sicoob->id}/ajustar", [])->assertStatus(422);
    }

    public function test_outra_familia_e_sem_token(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/financeiro/contas/{$this->sicoob->id}")->assertNotFound();
        $this->postJson("/api/v1/financeiro/contas/{$this->sicoob->id}/ajustar", ['saldo' => 1])->assertNotFound();
        $this->getJson('/api/v1/financeiro/inicio')->assertOk()->assertJsonPath('data.contas.total', 0);

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/financeiro/inicio')->assertUnauthorized();
    }
}
