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

        // Card de cartões: uso, livre e fatura paga de cada um.
        $c = app(FinanceiroService::class)->inicio($this->user->tenant_id)['cartoes'];
        $this->assertSame(1, $c['quantidade']);
        $this->assertTrue($c['itens'][0]['fatura_paga']);
        $this->assertSame('2026-10-07', $c['itens'][0]['vencimento']);
        $this->get(route('dashboard'))->assertSeeInOrder(['Cartões', 'Mercado Pago', 'Fatura', 'Paga']);
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

    public function test_encargo_pendente_nao_impede_quando_as_pagas_cobrem_a_fatura(): void
    {
        $this->compra('2026-10-08', 183.26, 'concluido');
        $this->compra('2026-10-07', 5.49, 'pendente'); // juros da fatura anterior, vai para a próxima

        $this->assertTrue($this->fatura()['pago']);
    }

    public function test_fatura_e_a_soma_das_compras_da_aba_lancamentos(): void
    {
        // Caso do Sicoob: a aba Cartões dizia 3.921,44 e a aba Lançamentos tinha
        // as compras da fatura de 22/10 somando mais (a aba Cartões ficou velha).
        PlanCartao::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('sicoob'), 'conteudo_hash' => sha1('x'), 'linha' => 2,
            'nome' => 'Cartão Sicoob', 'banco' => 'Sicoob', 'dia_vencimento' => 22, 'dia_fechamento' => 12,
            'fatura_atual' => 3921.44, 'status_fatura' => 'Aberta',
        ]);
        $this->compra('2026-10-22', 4000.00, 'pendente', 'Sicoob');
        $this->compra('2026-10-22', 2317.66, 'pendente', 'Sicoob');
        $this->compra('2026-09-22', 999.99, 'concluido', 'Sicoob'); // fatura anterior, fora

        $v = app(PlanejamentoService::class)->vencimentos($this->user->tenant_id);
        $sicoob = collect($v['a_pagar'])->firstWhere('descricao', 'Fatura Cartão Sicoob');
        $this->assertSame(6317.66, $sicoob['valor']);
        $this->assertSame('2026-10-22', $sicoob['data']);

        $cartao = app(PlanejamentoService::class)->cartoes($this->user->tenant_id)['itens']->firstWhere('nome', 'Cartão Sicoob');
        $this->assertSame(6317.66, (float) $cartao->fatura_atual);
        $this->assertSame(3921.44, (float) $cartao->fatura_planilha);
        $inicio = collect(app(FinanceiroService::class)->inicio($this->user->tenant_id)['cartoes']['itens'])->firstWhere('nome', 'Cartão Sicoob');
        $this->assertSame(6317.66, $inicio['fatura']);
    }

    public function test_sem_compra_lancada_vale_o_valor_da_aba_cartoes(): void
    {
        $f = $this->fatura();
        $this->assertSame(183.26, $f['valor']);
    }
}
