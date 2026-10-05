<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Banco;
use App\Models\NotificacaoConfig;
use App\Models\NotificacaoEnvio;
use App\Models\PlanCartao;
use App\Models\PlanLancamento;
use App\Models\TelegramDestinatario;
use App\Models\User;
use App\Services\Notificacoes\NotificacaoService;
use Illuminate\Support\Facades\Http;

class AvisosTest extends NotificacoesTestCase
{
    private int $linha = 100;

    private function processar(?User $user = null): array
    {
        return app(NotificacaoService::class)->processar(($user ?? $this->user)->tenant_id);
    }

    private function lancamento(array $a): PlanLancamento
    {
        $this->linha++;

        return PlanLancamento::withoutGlobalScopes()->create($a + [
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1($a['descricao'] . $this->linha), 'conteudo_hash' => sha1('x'),
            'linha' => $this->linha, 'tipo' => 'despesa', 'status' => 'pendente',
        ]);
    }

    private function conta(float $saldo): Banco
    {
        return Banco::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'user_id' => $this->user->id, 'nome' => 'Sicoob', 'tem_conta_corrente' => true, 'saldo' => $saldo,
        ]);
    }

    public function test_resumo_sai_as_7h_uma_vez_por_dia_por_pessoa(): void
    {
        $this->travelTo('2026-10-05 06:50:00');
        $this->configurar();
        $this->destinatario(1001);
        $this->destinatario(1002);
        $this->conta(250.10);
        $this->lancamento(['data' => '2026-10-05', 'descricao' => 'Conta de luz', 'valor_previsto' => 180.5]);

        $this->processar();
        $this->assertSame([], array_filter($this->enviadas(), fn ($t) => str_contains($t, 'Bom dia')));

        $this->travelTo('2026-10-05 07:05:00');
        $this->processar();
        $this->travelTo('2026-10-05 07:20:00');
        $this->processar();

        $resumos = array_values(array_filter($this->enviadas(1001), fn ($t) => str_contains($t, 'Bom dia')));
        $this->assertCount(1, $resumos);
        $this->assertStringContainsString('Resumo de seg, 05/10', $resumos[0]);
        $this->assertStringContainsString('Em contas: R$ 250,10', $resumos[0]);
        $this->assertStringContainsString('Conta de luz — R$ 180,50', $resumos[0]);
        $this->assertCount(1, array_filter($this->enviadas(1002), fn ($t) => str_contains($t, 'Bom dia')));
    }

    public function test_resumo_sem_nada_vencendo_diz_isso(): void
    {
        $this->travelTo('2026-10-05 07:30:00');
        $this->configurar();
        $this->destinatario();

        $this->processar();

        $this->assertStringContainsString('Nada atrasado e nada vencendo nos próximos 7 dias.', $this->enviadas()[0]);
    }

    public function test_conta_a_pagar_avisa_3_dias_antes_e_no_dia_uma_vez_cada(): void
    {
        $this->travelTo('2026-10-07 13:00:00');
        $this->configurar();
        $this->destinatario();
        $this->lancamento(['data' => '2026-10-10', 'descricao' => 'Escola <Davi>', 'valor_previsto' => 950]);

        $this->processar();
        $this->processar();
        $this->travelTo('2026-10-09 13:00:00');
        $this->processar();
        $this->travelTo('2026-10-10 13:00:00');
        $this->processar();

        $textos = $this->enviadas();
        $this->assertCount(2, $textos);
        $this->assertStringContainsString('Escola &lt;Davi&gt; — R$ 950,00', $textos[0]);
        $this->assertStringContainsString('vence em 3 dias (10/10)', $textos[0]);
        $this->assertStringContainsString('vence <b>hoje</b>', $textos[1]);
    }

    public function test_conta_a_receber_no_dia_e_atrasada(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $this->lancamento(['data' => '2026-10-05', 'tipo' => 'receita', 'descricao' => 'Salário', 'valor_previsto' => null]);
        // Atrasada desde antes de ligar os avisos: só no resumo, sem alerta.
        $this->lancamento(['data' => '2026-08-01', 'tipo' => 'receita', 'descricao' => 'Aluguel antigo', 'valor_previsto' => 100]);

        $this->processar();
        $this->travelTo('2026-10-06 13:00:00');
        $this->processar();
        $this->processar();

        $textos = $this->enviadas();
        $this->assertCount(2, $textos);
        $this->assertStringContainsString("A receber hoje</b>\nSalário — valor não informado", $textos[0]);
        $this->assertStringContainsString('Recebimento atrasado', $textos[1]);
        $this->assertStringContainsString('era para 05/10', $textos[1]);
    }

    public function test_saldo_negativo_avisa_uma_vez_e_rearma_quando_volta_ao_positivo(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $conta = $this->conta(-189.02);

        $this->processar();
        $this->processar();
        $conta->update(['saldo' => 50]);
        $this->processar();
        $conta->update(['saldo' => -10]);
        $this->processar();

        $textos = $this->enviadas();
        $this->assertCount(2, $textos);
        $this->assertStringContainsString('Sicoob: R$ -189,02', $textos[0]);
        $this->assertStringContainsString('Sicoob: R$ -10,00', $textos[1]);
    }

    public function test_alerta_de_saldo_nao_se_perde_quando_ninguem_esta_vinculado(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->conta(-189.02);

        $this->processar();
        $this->destinatario();
        $this->processar();

        $this->assertCount(1, $this->enviadas());
        $this->assertStringContainsString('Saldo negativo', $this->enviadas()[0]);
    }

    public function test_limite_acima_de_80_avisa_sem_repetir(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $cartao = PlanCartao::withoutGlobalScopes()->create([
            'tenant_id' => $this->user->tenant_id, 'chave' => sha1('sicoob'), 'conteudo_hash' => sha1('x'), 'linha' => 4,
            'nome' => 'Cartão Sicoob', 'limite_total' => 1000, 'limite_utilizado' => 700,
        ]);

        $this->processar();
        $cartao->update(['limite_utilizado' => 850]);
        $this->processar();
        $this->processar();

        $textos = $this->enviadas();
        $this->assertCount(1, $textos);
        $this->assertStringContainsString('Cartão Sicoob: 85,0% usado — disponível R$ 150,00', $textos[0]);
    }

    public function test_compra_nova_no_cartao_avisa_so_as_que_chegaram_depois_de_ligar(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->lancamento(['data' => '2026-09-20', 'descricao' => 'Compra antiga', 'forma' => 'cartao', 'valor_realizado' => 10]);
        $this->travelTo('2026-10-05 14:00:00');
        $this->configurar();
        $this->destinatario();

        $this->travelTo('2026-10-05 15:00:00');
        $this->lancamento(['data' => '2026-10-05', 'descricao' => 'Mercado', 'forma' => 'cartao', 'conta' => 'Cartão Sicoob', 'valor_realizado' => 230.45]);
        $this->processar();
        $this->processar();

        // Quem se vincula depois não recebe a compra anterior.
        $this->travelTo('2026-10-05 16:00:00');
        $this->destinatario(2002);
        $this->processar();

        $textos = $this->enviadas();
        $this->assertCount(1, $textos);
        $this->assertStringContainsString('Compra no cartão</b> no Cartão Sicoob', $textos[0]);
        $this->assertStringContainsString('Mercado — R$ 230,45', $textos[0]);
    }

    public function test_tipo_desligado_nao_e_enviado(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $this->conta(-5);

        $this->actingAs($this->user)->post(route('notificacoes.tipos'), ['tipos' => ['resumo' => 1, 'pagar' => 1]])->assertRedirect();
        $this->assertSame(['receber', 'saldo', 'compra', 'limite'], NotificacaoConfig::first()->tipos_desligados);

        $this->processar();
        $this->assertSame([], $this->enviadas());
    }

    public function test_falha_temporaria_tenta_de_novo_e_expira(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $this->conta(-5);
        $this->envio = fn () => Http::response(['ok' => false, 'description' => 'Too Many Requests'], 429);

        $this->processar();
        $envio = NotificacaoEnvio::withoutGlobalScopes()->first();
        $this->assertSame(NotificacaoEnvio::FALHOU, $envio->status);
        $this->assertSame(1, $envio->tentativas);

        $this->envio = fn () => Http::response(['ok' => true, 'result' => []]);
        $this->processar();
        $this->assertSame(NotificacaoEnvio::ENVIADO, $envio->fresh()->status);
        $this->assertSame(2, $envio->fresh()->tentativas);

        // Uma que falha sempre para em 5 tentativas.
        $this->envio = fn () => Http::response(['ok' => false, 'description' => 'Bad Gateway'], 502);
        app(NotificacaoService::class)->enviarTeste($this->user->tenant_id);
        foreach (range(1, 7) as $i) {
            $this->processar();
        }
        $teste = NotificacaoEnvio::withoutGlobalScopes()->where('tipo', 'teste')->first();
        $this->assertSame(NotificacaoEnvio::EXPIRADO, $teste->status);
        $this->assertSame(5, $teste->tentativas);
    }

    public function test_bot_bloqueado_desativa_a_pessoa(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $this->configurar();
        $this->destinatario();
        $this->envio = fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403);

        app(NotificacaoService::class)->enviarTeste($this->user->tenant_id);

        $d = TelegramDestinatario::withoutGlobalScopes()->first();
        $this->assertFalse($d->ativo);
        $this->assertStringContainsString('blocked', $d->motivo_inativo);

        // Desativada, não recebe mais nada.
        $this->conta(-5);
        $this->processar();
        $this->assertCount(1, $this->enviadas());
    }

    public function test_familias_nao_recebem_avisos_uma_da_outra(): void
    {
        $this->travelTo('2026-10-05 13:00:00');
        $outra = User::factory()->create();
        $this->liberar($outra);
        $this->configurar();
        $this->configurar($outra);
        $this->destinatario(1001);
        $this->destinatario(2002, $outra);
        $this->conta(-5);

        $this->processar($outra);
        $this->processar();

        $this->assertCount(1, $this->enviadas(1001));
        $this->assertSame([], $this->enviadas(2002));
    }

    public function test_botao_enviar_teste(): void
    {
        $this->configurar();
        $this->destinatario();

        $this->actingAs($this->user)->post(route('notificacoes.teste'))
            ->assertSessionHas('success', 'Teste: 1 enviado(s), 0 com falha.');
        $this->assertStringContainsString('Teste do AlfaHome', $this->enviadas()[0]);
    }
}
