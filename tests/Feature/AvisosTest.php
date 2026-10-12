<?php

namespace Tests\Feature;

use App\Models\PlanLancamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sininho: avisos da família logada, e nada de outra família. */
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_vencidas_e_dizimo_atrasado_so_da_familia(): void
    {
        $this->travelTo('2026-10-12 09:00:00');
        $u = User::factory()->create();
        $n = 1;
        foreach ([['2026-10-05', 'despesa', 'Escola', 'Educação', 950, false], ['2026-10-01', 'receita', 'Vixcard', 'Renda variável', 1000, true]] as [$d, $t, $desc, $cat, $v, $feito]) {
            PlanLancamento::withoutGlobalScopes()->create(['tenant_id' => $u->tenant_id, 'chave' => 'k' . $n, 'conteudo_hash' => 'x', 'linha' => $n++, 'data' => $d, 'tipo' => $t,
                'descricao' => $desc, 'categoria' => $cat, 'forma' => 'pix', 'conta' => 'Sicoob', 'valor_previsto' => $v, 'valor_realizado' => $feito ? $v : null, 'status' => $feito ? 'concluido' : 'pendente']);
        }

        $this->actingAs($u)->getJson(route('avisos.index'))->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('itens.0.titulo', '1 conta vencida')
            ->assertJsonPath('itens.1.titulo', 'Dízimo: falta devolver R$ 100,00');

        $this->actingAs(User::factory()->create())->getJson(route('avisos.index'))->assertOk()->assertJsonPath('total', 0);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="avisos-btn"', false)->assertDontSee('push-toggle');
    }

    public function test_tela_do_mes_e_cartoes_tem_os_controles_novos(): void
    {
        $this->travelTo('2026-10-12 09:00:00');
        $u = User::factory()->create();
        PlanLancamento::withoutGlobalScopes()->create(['tenant_id' => $u->tenant_id, 'chave' => 'k', 'conteudo_hash' => 'x', 'linha' => 1, 'data' => '2026-10-05', 'tipo' => 'despesa',
            'descricao' => 'Mercado', 'categoria' => 'Alimentação', 'forma' => 'pix', 'conta' => 'Sicoob', 'valor_previsto' => 100, 'valor_realizado' => 100, 'status' => 'concluido']);
        $this->actingAs($u)->get(route('planejamento.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('data-modo="pizza"', false)->assertSee('data-modo="barras"', false)->assertSee('Alimentação');
    }
}
