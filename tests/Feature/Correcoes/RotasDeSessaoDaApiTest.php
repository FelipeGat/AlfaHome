<?php

namespace Tests\Feature\Correcoes;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * As rotas /api que o navegador chama com o cookie de sessão (snapshot do
 * painel, push e sincronização offline) respondiam 401 em toda página, porque
 * o grupo "api" não carrega a sessão.
 */
class RotasDeSessaoDaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotas_chamadas_pelo_navegador_carregam_a_sessao(): void
    {
        foreach (['api.dashboard.snapshot', 'api.push.subscribe', 'api.push.unsubscribe', 'api.sync.despesa', 'api.sync.receita'] as $nome) {
            $this->assertContains('web', Route::getRoutes()->getByName($nome)->gatherMiddleware(), "{$nome} sem sessão");
        }
    }

    public function test_snapshot_responde_para_usuario_logado_e_recusa_visitante(): void
    {
        $this->getJson('/api/dashboard/snapshot')->assertStatus(401);

        $this->actingAs(User::factory()->create())->getJson('/api/dashboard/snapshot')->assertOk();
    }

    public function test_sincronizar_despesa_offline_grava_e_nao_duplica(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $banco     = Banco::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'nome' => 'Conta', 'tem_conta_corrente' => true, 'saldo' => 100]);
        $categoria = Categoria::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'nome' => 'Mercado', 'tipo' => 'DESPESA']);

        $payload = [
            'descricao' => 'Pão', 'valor' => 12.5, 'data_compra' => '2026-08-10', 'categoria_id' => $categoria->id,
            'forma_pagamento' => $banco->id, 'observacao' => 'padaria da esquina', 'client_queue_id' => 'fila-1',
        ];

        $this->postJson('/api/sync/despesa', $payload)->assertOk()->assertJsonPath('ok', true);
        $this->postJson('/api/sync/despesa', $payload)->assertOk()->assertJsonPath('skipped', true);

        $this->assertSame(1, Despesa::count());
        $despesa = Despesa::first();
        $this->assertSame('12.50', $despesa->valor);
        $this->assertSame('Pão — padaria da esquina', $despesa->observacoes);
        $this->assertSame('offline_sync', $despesa->origem);
        $this->assertSame($user->tenant_id, $despesa->tenant_id);
        $this->assertNull($despesa->data_pagamento);
        $this->assertEqualsWithDelta(100.0, (float) $banco->fresh()->saldo, 0.001, 'despesa em aberto não mexe no saldo');
    }

    public function test_sincronizar_receita_offline_grava(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/sync/receita', ['descricao' => 'Freela', 'valor' => 300, 'data_prevista_recebimento' => '2026-08-20', 'client_queue_id' => 'fila-9'])
            ->assertOk()->assertJsonPath('ok', true);

        $receita = Receita::first();
        $this->assertSame('300.00', $receita->valor);
        $this->assertSame('Freela', $receita->observacoes);
    }

    public function test_sincronizacao_recusa_conta_e_categoria_de_outra_familia(): void
    {
        $outro     = User::factory()->create();
        $bancoDele = Banco::withoutEvents(fn () => Banco::create(['tenant_id' => $outro->tenant_id, 'user_id' => $outro->id, 'nome' => 'Alheio', 'tem_conta_corrente' => true, 'saldo' => 0]));

        $this->actingAs(User::factory()->create());

        $this->postJson('/api/sync/despesa', ['descricao' => 'X', 'valor' => 10, 'data_compra' => '2026-08-10', 'forma_pagamento' => $bancoDele->id])
            ->assertStatus(422)->assertJsonValidationErrors('forma_pagamento');

        $this->assertSame(0, Despesa::withoutGlobalScopes()->count());
    }
}
