<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanCartao;
use App\Models\PlanLancamento;
use App\Models\PlanilhaImportacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tela de cartões: clicar num cartão mostra as faturas dele, por mês. */
class FaturasDoCartaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtro_mostra_as_faturas_do_cartao_por_mes(): void
    {
        $this->travelTo('2026-10-07 10:00:00');
        $user = User::factory()->create();
        PlanilhaImportacao::withoutGlobalScopes()->create(['tenant_id' => $user->tenant_id, 'arquivo_nome' => 'p.xlsx', 'arquivo_hash' => str_repeat('0', 64), 'status' => PlanilhaImportacao::SUCESSO]);
        $cartao = PlanCartao::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id, 'chave' => 'sicoob-chave', 'conteudo_hash' => sha1('x'), 'linha' => 1,
            'nome' => 'Cartão Sicoob', 'banco' => 'Sicoob', 'dia_vencimento' => 22, 'fatura_atual' => 3921.44, 'status_fatura' => 'Aberta',
        ]);
        $n = 1;
        foreach ([['2026-09-22', 'Netflix', 20.90, 'concluido', 'Sicoob'], ['2026-10-22', 'Uber', 21.59, 'pendente', 'Sicoob'], ['2026-10-22', 'Dentista', 130, 'concluido', 'Sicoob'], ['2026-10-13', 'Outro cartão', 99, 'pendente', 'Celebre']] as [$data, $desc, $valor, $status, $conta]) {
            PlanLancamento::withoutGlobalScopes()->create([
                'tenant_id' => $user->tenant_id, 'chave' => sha1($desc), 'conteudo_hash' => sha1('x'), 'linha' => $n++,
                'data' => $data, 'tipo' => 'despesa', 'descricao' => $desc, 'forma' => 'cartao', 'conta' => $conta,
                'valor_previsto' => $valor, 'valor_realizado' => $status === 'concluido' ? $valor : null, 'status' => $status,
            ]);
        }

        $this->actingAs($user);
        $r = $this->get(route('planejamento.cartoes', ['cartao' => 'sicoob-chave']))->assertOk();
        $faturas = $r->viewData('faturas');

        $this->assertSame(['2026-10', '2026-09'], array_column($faturas, 'mes'));
        $this->assertSame(151.59, $faturas[0]['total']);
        $this->assertSame(21.59, $faturas[0]['pendente']);
        $this->assertFalse($faturas[0]['paga']);
        $this->assertTrue($faturas[1]['paga']);
        $this->assertSame('2026-10-22', $faturas[0]['vencimento']);
        $r->assertSee('Faturas — Cartão Sicoob')->assertSee('Falta R$ 21,59')->assertDontSee('Outro cartão');

        // Sem filtro: lista todos, sem bloco de faturas.
        $this->get(route('planejamento.cartoes'))->assertOk()->assertDontSee('Faturas — ')->assertSee('Clique num cartão');
        // Cartão inexistente: ignora o filtro.
        $this->assertSame([], $this->get(route('planejamento.cartoes', ['cartao' => 'nao-existe']))->viewData('faturas'));
    }
}
