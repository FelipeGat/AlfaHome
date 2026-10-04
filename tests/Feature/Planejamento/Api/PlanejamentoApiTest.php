<?php

namespace Tests\Feature\Planejamento\Api;

use App\Models\PlanLancamento;
use App\Models\User;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class PlanejamentoApiTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private const LEITURA = ['resumo', 'anual', 'lancamentos', 'cartoes', 'parceladas', 'dividas', 'metas', 'contas-fixas', 'importacoes'];

    private function comPlanilha(): User
    {
        $user = User::factory()->create();
        app(PlanilhaImportService::class)->importar($user->tenant_id, $user->id, $this->planilha(), 'Planejamento.xlsx');

        return $user;
    }

    private function como(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $user->createToken('teste')->plainTextToken);
    }

    private function upload(string $caminho, string $nome = 'Planejamento.xlsx'): UploadedFile
    {
        return new UploadedFile($caminho, $nome, null, null, true);
    }

    public function test_sem_token_toda_rota_responde_401(): void
    {
        foreach (self::LEITURA as $rota) {
            $this->getJson("/api/v1/planejamento/{$rota}")->assertStatus(401);
        }
        $this->postJson('/api/v1/planejamento/importar')->assertStatus(401);
    }

    public function test_resumo_do_mes_segue_o_contrato_e_os_numeros_da_planilha(): void
    {
        $resposta = $this->como($this->comPlanilha())->getJson('/api/v1/planejamento/resumo?mes=2026-08')->assertOk();

        $resposta->assertJsonStructure(['data' => [
            'mes', 'receitas' => ['previsto', 'realizado'], 'despesas' => ['previsto', 'realizado'], 'saldo' => ['previsto', 'realizado'],
            'economia_pct', 'comprometimento_pct', 'por_categoria' => [['categoria', 'realizado', 'pct']],
            'cartoes' => ['limite_total', 'limite_utilizado', 'limite_disponivel', 'faturas_abertas', 'utilizado_pct'],
            'dividas' => ['saldo_total', 'parcela_mensal_total', 'quantidade'],
            'metas'   => ['progresso_medio_pct', 'quantidade'],
            'ultima_importacao' => ['id', 'em', 'arquivo'],
        ]]);

        $resposta->assertJsonPath('data.mes', '2026-08')
            ->assertJsonPath('data.receitas.previsto', 17261.86)
            ->assertJsonPath('data.receitas.realizado', 17632.86)
            ->assertJsonPath('data.despesas.previsto', 12217.44)
            ->assertJsonPath('data.despesas.realizado', 12225.1)
            ->assertJsonPath('data.saldo.realizado', 5407.76)
            ->assertJsonPath('data.economia_pct', 30.67)
            ->assertJsonPath('data.comprometimento_pct', 69.33)
            ->assertJsonPath('data.cartoes.limite_disponivel', 1855.15)
            ->assertJsonPath('data.cartoes.faturas_abertas', 5549.77)
            ->assertJsonPath('data.cartoes.utilizado_pct', 92.64)
            ->assertJsonPath('data.dividas.saldo_total', 10174.66)
            ->assertJsonPath('data.ultima_importacao.arquivo', 'Planejamento.xlsx');

        $this->assertSame('Investimentos', $resposta->json('data.por_categoria.0.categoria'));
    }

    public function test_sem_mes_devolve_o_ultimo_mes_com_lancamento(): void
    {
        $user = $this->comPlanilha();
        $this->travelTo('2026-10-04');

        $this->como($user)->getJson('/api/v1/planejamento/resumo')->assertOk()
            ->assertJsonPath('data.mes', '2026-08')
            ->assertJsonPath('data.receitas.realizado', 17632.86);
        $this->como($user)->getJson('/api/v1/planejamento/lancamentos')->assertOk()->assertJsonCount(52, 'data');
    }

    public function test_sem_mes_e_sem_planilha_devolve_o_mes_corrente(): void
    {
        $this->travelTo('2026-10-04');

        $this->como(User::factory()->create())->getJson('/api/v1/planejamento/resumo')->assertOk()
            ->assertJsonPath('data.mes', '2026-10')
            ->assertJsonPath('data.ultima_importacao', null);
    }

    public function test_mes_invalido_responde_422_em_portugues(): void
    {
        $this->como($this->comPlanilha())->getJson('/api/v1/planejamento/resumo?mes=agosto')
            ->assertStatus(422)
            ->assertJsonPath('errors.mes.0', 'Informe o mês no formato AAAA-MM.');
    }

    public function test_anual_traz_doze_meses_e_o_total(): void
    {
        $this->como($this->comPlanilha())->getJson('/api/v1/planejamento/anual?ano=2026')->assertOk()
            ->assertJsonCount(12, 'data.meses')
            ->assertJsonPath('data.ano', 2026)
            ->assertJsonPath('data.meses.7.mes', '2026-08')
            ->assertJsonPath('data.meses.7.despesas.realizado', 12225.1)
            ->assertJsonPath('data.total.receitas.realizado', 17632.86);
    }

    public function test_lancamentos_do_mes(): void
    {
        $user = $this->comPlanilha();

        $resposta = $this->como($user)->getJson('/api/v1/planejamento/lancamentos?mes=2026-08')->assertOk()->assertJsonCount(52, 'data');
        $resposta->assertJsonStructure(['data' => [[
            'ref', 'origem', 'editavel', 'data', 'tipo', 'descricao', 'categoria', 'forma', 'conta',
            'valor_previsto', 'valor_realizado', 'diferenca', 'status', 'observacao',
        ]]]);

        $salario = collect($resposta->json('data'))->firstWhere('descricao', 'Salário (Gdl)');
        $this->assertSame('2026-08-28', $salario['data']);
        $this->assertEquals(1200, $salario['valor_previsto']);
        $this->assertEquals(1571, $salario['valor_realizado']);
        $this->assertEquals(371, $salario['diferenca']);
        $this->assertFalse($salario['editavel']);

        $this->como($user)->getJson('/api/v1/planejamento/lancamentos?mes=2026-08&tipo=receita')->assertOk()->assertJsonCount(13, 'data');
        $this->como($user)->getJson('/api/v1/planejamento/lancamentos?mes=2026-08&tipo=outro')->assertStatus(422);
    }

    public function test_cartoes_parceladas_dividas_metas_e_contas_fixas(): void
    {
        $user = $this->comPlanilha();

        $this->como($user)->getJson('/api/v1/planejamento/cartoes')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.nome', 'Cartão Sicoob')
            ->assertJsonPath('data.0.limite_total', 23593)
            ->assertJsonPath('data.0.limite_disponivel', 1855.15)
            ->assertJsonPath('data.0.dia_fechamento', 12)
            ->assertJsonPath('data.0.fatura_atual', 3921.44)
            ->assertJsonPath('data.0.status_fatura', 'Aberta')
            ->assertJsonPath('data.2.nome', 'Cartão celebre')
            ->assertJsonPath('data.2.limite_total', null)
            ->assertJsonPath('data.2.limite_disponivel', null)
            ->assertJsonPath('resumo.faturas_abertas', 5549.77);

        $this->como($user)->getJson('/api/v1/planejamento/parceladas')->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.2.compra', 'Celular Iphone')
            ->assertJsonPath('data.2.cartao_cadastrado', true)
            ->assertJsonPath('data.2.valor_parcela', 519.9)
            ->assertJsonPath('data.2.parcelas_restantes', 4)
            ->assertJsonPath('data.2.saldo', 2079.6)
            ->assertJsonPath('data.4.cartao', 'Cartão Mercado Pago')
            ->assertJsonPath('data.4.cartao_cadastrado', false)
            ->assertJsonStructure(['resumo' => ['saldo_total', 'parcela_mensal_total']]);

        $this->como($user)->getJson('/api/v1/planejamento/dividas')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nome', 'Empréstimo empresa')
            ->assertJsonPath('data.0.saldo_atual', 6744.29)
            ->assertJsonPath('data.0.amortizado', 8255.71)
            ->assertJsonPath('data.0.taxa_mensal_pct', 5.2)
            ->assertJsonPath('data.0.prioridade', 'alta')
            ->assertJsonPath('resumo.saldo_total', 10174.66);

        $this->como($user)->getJson('/api/v1/planejamento/metas')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nome', 'Reserva de emergência')
            ->assertJsonPath('data.0.valor_alvo', 30000)
            ->assertJsonPath('data.0.falta', 30000)
            ->assertJsonPath('data.0.prazo', null)
            ->assertJsonPath('resumo.progresso_medio_pct', 0);

        $this->como($user)->getJson('/api/v1/planejamento/contas-fixas')->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('data.0.conta', 'Internet Claro')
            ->assertJsonPath('data.0.dia_vencimento', 5)
            ->assertJsonPath('data.0.forma', 'pix')
            ->assertJsonPath('data.0.recorrente', true)
            ->assertJsonStructure(['resumo' => ['previsto_total', 'realizado_total']]);
    }

    public function test_outra_familia_nao_ve_nada(): void
    {
        $this->comPlanilha();
        $outro = User::factory()->create();

        $this->como($outro)->getJson('/api/v1/planejamento/resumo?mes=2026-08')->assertOk()
            ->assertJsonPath('data.receitas.realizado', 0)
            ->assertJsonPath('data.dividas.saldo_total', 0)
            ->assertJsonPath('data.cartoes.faturas_abertas', 0)
            ->assertJsonPath('data.ultima_importacao', null);

        foreach (['lancamentos?mes=2026-08', 'cartoes', 'parceladas', 'dividas', 'metas', 'contas-fixas', 'importacoes'] as $rota) {
            $this->como($outro)->getJson("/api/v1/planejamento/{$rota}")->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_importar_pela_api(): void
    {
        $user = User::factory()->create();

        $this->como($user)->post('/api/v1/planejamento/importar', ['arquivo' => $this->upload($this->planilha())], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sucesso')
            ->assertJsonPath('data.resumo.lancamentos.incluidas', 52)
            ->assertJsonPath('data.arquivo', 'Planejamento.xlsx');

        $this->como($user)->post('/api/v1/planejamento/importar', ['arquivo' => $this->upload($this->planilha())], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', 'sem_alteracoes');

        $this->como($user)->getJson('/api/v1/planejamento/importacoes')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'sem_alteracoes')
            ->assertJsonPath('data.0.usuario', $user->name);
    }

    public function test_planilha_invalida_responde_422_sem_gravar(): void
    {
        $user = $this->comPlanilha();

        $this->como($user)->post('/api/v1/planejamento/importar', ['arquivo' => $this->upload($this->planilhaSemAba('Cartões'))], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A planilha foi rejeitada e nada foi alterado.')
            ->assertJsonPath('data.status', 'rejeitada')
            ->assertJsonPath('data.erros.0.mensagem', 'A aba "Cartões" não foi encontrada na planilha.');

        $this->assertSame(52, PlanLancamento::withoutGlobalScopes()->count());

        $this->como($user)->postJson('/api/v1/planejamento/importar')
            ->assertStatus(422)
            ->assertJsonPath('errors.arquivo.0', 'Envie o arquivo da planilha.');
    }

    public function test_so_o_dono_da_conta_importa(): void
    {
        $dono   = User::factory()->create();
        $membro = User::factory()->create(['tenant_id' => $dono->tenant_id, 'role' => 'membro']);

        $this->como($membro)->post('/api/v1/planejamento/importar', ['arquivo' => $this->upload($this->planilha())], ['Accept' => 'application/json'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Somente o dono da conta pode importar a planilha.');

        $this->assertSame(0, PlanLancamento::withoutGlobalScopes()->count());
    }
}
