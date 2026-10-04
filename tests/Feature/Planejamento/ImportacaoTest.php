<?php

namespace Tests\Feature\Planejamento;

use App\Models\Banco;
use App\Models\Categoria;
use App\Models\Despesa;
use App\Models\PlanCartao;
use App\Models\PlanCompraParcelada;
use App\Models\PlanContaFixa;
use App\Models\PlanDivida;
use App\Models\PlanilhaImportacao;
use App\Models\PlanLancamento;
use App\Models\PlanMeta;
use App\Models\User;
use App\Services\Planejamento\PlanilhaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PlanilhaFixture;
use Tests\TestCase;

class ImportacaoTest extends TestCase
{
    use RefreshDatabase, PlanilhaFixture;

    private const TABELAS = ['plan_lancamentos', 'plan_contas_fixas', 'plan_cartoes', 'plan_compras_parceladas', 'plan_dividas', 'plan_metas'];

    private function importar(User $user, string $caminho): PlanilhaImportacao
    {
        return app(PlanilhaImportService::class)->importar($user->tenant_id, $user->id, $caminho, basename($caminho));
    }

    /** Retrato de tudo que a importação grava, para provar que nada mudou. */
    private function retrato(): array
    {
        return collect(self::TABELAS)->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()->toArray()])->all();
    }

    public function test_carga_inicial_importa_todas_as_abas(): void
    {
        $user = User::factory()->create();

        $importacao = $this->importar($user, $this->planilha());

        $this->assertSame(PlanilhaImportacao::SUCESSO, $importacao->status);
        $this->assertSame(52, PlanLancamento::withoutGlobalScopes()->count());
        $this->assertSame(9, PlanContaFixa::withoutGlobalScopes()->count());
        $this->assertSame(3, PlanCartao::withoutGlobalScopes()->count());
        $this->assertSame(6, PlanCompraParcelada::withoutGlobalScopes()->count());
        $this->assertSame(2, PlanDivida::withoutGlobalScopes()->count());
        $this->assertSame(2, PlanMeta::withoutGlobalScopes()->count());

        $this->assertSame(['incluidas' => 52, 'atualizadas' => 0, 'removidas' => 0, 'mantidas' => 0], $importacao->resumo['lancamentos']);
        // Relida do banco (que reordena chaves de JSON), a ordem continua a das abas.
        $this->assertSame(
            ['lancamentos', 'contas_fixas', 'cartoes', 'parceladas', 'dividas', 'metas'],
            array_keys(PlanilhaImportacao::withoutGlobalScopes()->find($importacao->id)->resumo)
        );
    }

    public function test_reimportar_o_mesmo_arquivo_nao_altera_nada(): void
    {
        $user = User::factory()->create();
        $this->importar($user, $this->planilha());
        $antes      = $this->retrato();
        $categorias = Categoria::withoutGlobalScopes()->count();

        $this->travel(1)->hour();
        $segunda = $this->importar($user, $this->planilha());

        $this->assertSame(PlanilhaImportacao::SEM_ALTERACOES, $segunda->status);
        $this->assertEquals($antes, $this->retrato());
        $this->assertSame($categorias, Categoria::withoutGlobalScopes()->count());
        $this->assertSame(52, $segunda->resumo['lancamentos']['mantidas']);
    }

    public function test_planilha_alterada_inclui_atualiza_e_remove(): void
    {
        $user = User::factory()->create();
        $this->importar($user, $this->planilha());
        $aluguel = PlanLancamento::withoutGlobalScopes()->where('linha', 4)->first();

        $importacao = $this->importar($user, $this->planilhaAtualizada());

        $this->assertSame(PlanilhaImportacao::SUCESSO, $importacao->status);
        // "Parcela casa (6 de 23)" virou "(5 de 23)": sai uma, entra outra; mais dois lançamentos novos.
        $this->assertSame(['incluidas' => 3, 'atualizadas' => 0, 'removidas' => 1, 'mantidas' => 51], $importacao->resumo['lancamentos']);
        // Nomes das dívidas mudaram só de caixa: mesma linha, conteúdo atualizado.
        $this->assertSame(['incluidas' => 0, 'atualizadas' => 2, 'removidas' => 0, 'mantidas' => 0], $importacao->resumo['dividas']);
        $this->assertSame(['incluidas' => 1, 'atualizadas' => 0, 'removidas' => 0, 'mantidas' => 2], $importacao->resumo['metas']);

        $this->assertSame(54, PlanLancamento::withoutGlobalScopes()->count());
        $this->assertSame(0, PlanLancamento::withoutGlobalScopes()->where('descricao', 'Parcela casa (6 de 23)')->count());
        $this->assertSame('Empréstimo Empresa', PlanDivida::withoutGlobalScopes()->orderBy('linha')->first()->nome);
        // Linha que não mudou mantém o mesmo registro.
        $this->assertSame($aluguel->id, PlanLancamento::withoutGlobalScopes()->where('linha', 4)->first()->id);
        $this->assertEquals($aluguel->updated_at, PlanLancamento::withoutGlobalScopes()->find($aluguel->id)->updated_at);
    }

    public function test_linha_invalida_rejeita_o_arquivo_inteiro(): void
    {
        $user = User::factory()->create();
        $this->importar($user, $this->planilha());
        $antes = $this->retrato();

        $importacao = $this->importar($user, $this->planilhaComTexto('Lançamentos', 'I20', 'dez reais'));

        $this->assertSame(PlanilhaImportacao::REJEITADA, $importacao->status);
        $this->assertSame('Lançamentos', $importacao->erros[0]['aba']);
        $this->assertSame(20, $importacao->erros[0]['linha']);
        $this->assertStringContainsString('"Realizado" deveria ser um número', $importacao->erros[0]['mensagem']);
        $this->assertEquals($antes, $this->retrato());
    }

    public function test_aba_ausente_rejeita_o_arquivo(): void
    {
        $user = User::factory()->create();

        $importacao = $this->importar($user, $this->planilhaSemAba('Metas'));

        $this->assertSame(PlanilhaImportacao::REJEITADA, $importacao->status);
        $this->assertSame('A aba "Metas" não foi encontrada na planilha.', $importacao->erros[0]['mensagem']);
        $this->assertSame(0, PlanLancamento::withoutGlobalScopes()->count());
    }

    public function test_arquivo_que_nao_e_planilha_e_rejeitado_com_mensagem_em_portugues(): void
    {
        $user    = User::factory()->create();
        $arquivo = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($arquivo, 'texto qualquer');

        $importacao = $this->importar($user, $arquivo);

        $this->assertSame(PlanilhaImportacao::REJEITADA, $importacao->status);
        $this->assertSame('O arquivo não é uma planilha Excel (.xlsx) válida.', $importacao->erros[0]['mensagem']);
    }

    public function test_avisa_categoria_criada_e_cartao_nao_cadastrado(): void
    {
        $user = User::factory()->create();
        Categoria::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'nome' => 'moradia', 'tipo' => 'DESPESA']);

        $importacao = $this->importar($user, $this->planilha());
        $mensagens  = array_column($importacao->avisos, 'mensagem');

        // "Moradia" já existia (sem diferenciar maiúsculas): reaproveitada, sem aviso.
        $this->assertNotContains('Categoria "Moradia" (despesa) não existia e foi criada.', $mensagens);
        $this->assertSame(1, Categoria::withoutGlobalScopes()->where('nome', 'moradia')->count());
        $this->assertContains('Categoria "Tarifa báncaria" (despesa) não existia e foi criada.', $mensagens);
        $this->assertContains('O cartão "Cartão Mercado Pago" não está na aba Cartões.', $mensagens);

        $computador = PlanCompraParcelada::withoutGlobalScopes()->where('compra', 'Computador')->first();
        $this->assertSame('Cartão Mercado Pago', $computador->cartao);
        $this->assertNull($computador->plan_cartao_id);
        $this->assertNotNull(PlanCompraParcelada::withoutGlobalScopes()->where('compra', 'Celular Iphone')->first()->plan_cartao_id);
    }

    public function test_nao_toca_em_lancamentos_manuais_nem_em_saldo_de_conta(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $banco   = Banco::create(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'nome' => 'Sicoob', 'tem_conta_corrente' => true, 'saldo' => 1234.56]);
        $despesa = Despesa::create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'forma_pagamento' => $banco->id,
            'tipo_pagamento' => 'pix', 'valor' => 50, 'data_compra' => '2026-08-10', 'data_pagamento' => null,
        ]);

        $this->importar($user, $this->planilha());
        $this->importar($user, $this->planilhaAtualizada());

        $this->assertEqualsWithDelta(1234.56, (float) $banco->fresh()->saldo, 0.001);
        $this->assertEquals($despesa->updated_at, $despesa->fresh()->updated_at);
        $this->assertSame(1, Despesa::withoutGlobalScopes()->count());
    }

    public function test_cada_familia_tem_a_sua_importacao(): void
    {
        $felipe = User::factory()->create();
        $outro  = User::factory()->create();

        $this->importar($felipe, $this->planilha());
        $this->importar($outro, $this->planilhaAtualizada());

        $this->assertSame(52, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $felipe->tenant_id)->count());
        $this->assertSame(54, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $outro->tenant_id)->count());

        // Reimportar a de uma família não mexe na da outra.
        $this->importar($felipe, $this->planilha());
        $this->assertSame(54, PlanLancamento::withoutGlobalScopes()->where('tenant_id', $outro->tenant_id)->count());

        $this->actingAs($felipe);
        $this->assertSame(52, PlanLancamento::count());
        $this->assertSame(2, PlanMeta::count());
    }

    public function test_comando_importa_e_informa_erros(): void
    {
        $user = User::factory()->create();

        $this->artisan('planilha:importar', ['arquivo' => $this->planilha(), '--tenant' => $user->tenant_id])
            ->expectsOutputToContain('Planilha importada.')
            ->assertExitCode(0);
        $this->assertSame(52, PlanLancamento::withoutGlobalScopes()->count());

        $this->artisan('planilha:importar', ['arquivo' => $this->planilha(), '--tenant' => $user->tenant_id])
            ->expectsOutputToContain('Sem alterações.')
            ->assertExitCode(0);

        $this->artisan('planilha:importar', ['arquivo' => '/nao/existe.xlsx', '--tenant' => $user->tenant_id])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertExitCode(1);

        $this->artisan('planilha:importar', ['arquivo' => $this->planilha(), '--tenant' => 999999])
            ->expectsOutputToContain('Informe um tenant existente')
            ->assertExitCode(1);
    }
}
