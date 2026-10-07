<?php

namespace Tests\Feature\Planejamento;

use App\Models\PlanCartao;
use App\Models\PlanilhaImportacao;
use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Financeiro\FinanceiroService;
use App\Services\Planejamento\PlanejamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fatura cujas compras já estão como Pago aparece paga (verde) e não soma no "a pagar". */
class FaturaPagaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $linha = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-07 09:00:00');
        $this->user = User::factory()->create();
        PlanCartao::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('mp'), 'conteudo_hash' => sha1('x'), 'linha' => 1,
            'nome' => 'Cartão Mercado Pago', 'banco' => 'Mercado Pago', 'dia_vencimento' => 7, 'dia_fechamento' => 2,
            'fatura_atual' => 183.26, 'status_fatura' => 'Aberta',
        ]);
    }

    private function compra(string $data, float $valor, string $status, string $conta = 'Mercado Pago'): void
    {
        PlanLancamento::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('c' . $this->linha), 'conteudo_hash' => sha1('x'), 'linha' => $this->linha++,
            'data' => $data, 'tipo' => 'despesa', 'descricao' => 'Computador (Par. 07 de 18)', 'forma' => 'cartao', 'conta' => $conta,
            'valor_previsto' => $valor, 'valor_realizado' => $status === 'concluido' ? $valor : null, 'status' => $status,
        ]);
    }

    private function fatura(): array
    {
        $v = app(PlanejamentoService::class)->vencimentos($this->user->tenant_id);

        return collect($v['a_pagar'])->firstWhere('origem', 'fatura') + ['total' => $v['totais']['a_pagar']];
    }

    public function test_compras_da_fatura_pagas_marcam_a_fatura_como_paga(): void
    {
        $this->compra('2026-10-08', 183.26, 'concluido');

        $f = $this->fatura();
        $this->assertTrue($f['pago']);
        $this->assertSame('2026-10-07', $f['data'], 'continua na lista até vencer');
        $this->assertSame(0.0, $f['total'], 'não soma no a pagar');
        $this->assertTrue(app(FinanceiroService::class)->proximosPagamentos($this->user->tenant_id)[0]['pago']);

        PlanilhaImportacao::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'arquivo_nome' => 'p.xlsx', 'arquivo_hash' => str_repeat('0', 64), 'status' => PlanilhaImportacao::SUCESSO,
        ]);
        $this->actingAs($this->user);
        $this->get(route('dashboard'))->assertOk()->assertSeeInOrder(['Fatura Cartão Mercado Pago', 'Pago']);
    }

    public function test_compra_pendente_mantem_a_fatura_a_pagar(): void
    {
        $this->compra('2026-10-08', 100, 'concluido');
        $this->compra('2026-10-05', 83.26, 'pendente');

        $f = $this->fatura();
        $this->assertFalse($f['pago']);
        $this->assertSame(183.26, $f['total']);
    }

    public function test_sem_compras_ou_de_outro_cartao_continua_a_pagar(): void
    {
        $this->assertFalse($this->fatura()['pago']);

        $this->compra('2026-10-08', 183.26, 'concluido', 'Sicoob');
        $this->assertFalse($this->fatura()['pago']);
    }
}
