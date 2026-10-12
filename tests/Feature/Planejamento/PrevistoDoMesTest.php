<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanLancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tela do mês: o que há a receber e a pagar, com as compras no cartão agrupadas por fatura. */
class PrevistoDoMesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_a_receber_e_a_pagar_com_fatura_agrupada(): void
    {
        $this->travelTo('2026-11-12 09:00:00');
        $u = User::factory()->create();
        $n = 1;
        $l = function (string $data, string $tipo, string $desc, float $valor, string $status, ?string $forma = 'pix', string $conta = 'Sicoob') use ($u, &$n) {
            PlanLancamento::withoutGlobalScopes()->create([
                'tenant_id' => $u->tenant_id, 'chave' => 'k' . $n, 'conteudo_hash' => 'x', 'linha' => $n++, 'data' => $data, 'tipo' => $tipo,
                'descricao' => $desc, 'categoria' => 'Cat', 'forma' => $forma, 'conta' => $conta,
                'valor_previsto' => $valor, 'valor_realizado' => $status === 'concluido' ? $valor : null, 'status' => $status,
            ]);
        };
        $l('2026-11-05', 'receita', 'Vixcard', 7500, 'concluido');
        $l('2026-11-30', 'receita', 'Salário (Grupo soluções)', 405.25, 'pendente');
        $l('2026-11-10', 'despesa', 'Pensão (Novembro/2026)', 1134.70, 'pendente');
        $l('2026-11-22', 'despesa', 'Celular Iphone (Par. 08 de 10)', 519.90, 'pendente', 'cartao');
        $l('2026-11-22', 'despesa', 'Influx inglês (Par. 2 de 3)', 1176.00, 'pendente', 'cartao');
        $l('2026-12-10', 'despesa', 'Pensão (Dezembro/2026)', 1134.70, 'pendente');

        $this->actingAs($u)->get(route('planejamento.index', ['mes' => '2026-11']))->assertOk()
            ->assertSee('A receber em novembro')->assertSee('R$ 7.905,25')->assertSee('Recebido R$ 7.500,00 · falta R$ 405,25')
            ->assertSeeInOrder(['Vixcard', 'Recebido', 'Salário (Grupo soluções)', 'Pendente'])
            ->assertSee('A pagar em novembro')->assertSee('R$ 2.830,60')
            ->assertSeeInOrder(['Pensão (Novembro/2026)', 'Atrasado', 'Fatura Sicoob', '2 compras no cartão', 'R$ 1.695,90'])
            ->assertDontSee('Pensão (Dezembro/2026)')->assertDontSee('Celular Iphone (Par. 08 de 10)');
    }
}
