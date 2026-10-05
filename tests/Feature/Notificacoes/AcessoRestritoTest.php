<?php

namespace Tests\Feature\Notificacoes;

use App\Models\Banco;
use App\Models\NotificacaoConfig;
use App\Models\User;
use App\Services\Notificacoes\NotificacaoService;
use Illuminate\Support\Facades\Http;

/** Os avisos só existem para as famílias liberadas (hoje, só a do Felipe). */
class AcessoRestritoTest extends NotificacoesTestCase
{
    public function test_familia_fora_da_lista_nao_tem_acesso_a_nada(): void
    {
        $this->travelTo('2026-10-05 07:30:00');
        $intrusa = User::factory()->create();
        $tenant  = $intrusa->tenant_id;
        // Mesmo com configuração e destinatário gravados por fora, nada sai.
        $this->configurar($intrusa);
        $this->destinatario(9009, $intrusa);
        Banco::withoutGlobalScopes()->create(['tenant_id' => $tenant, 'user_id' => $intrusa->id, 'nome' => 'X', 'tem_conta_corrente' => true, 'saldo' => -1]);

        $this->actingAs($intrusa);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Avisos no Telegram');
        $this->get(route('notificacoes.index'))->assertNotFound();
        $this->post(route('notificacoes.token'), ['token' => self::TOKEN])->assertNotFound();
        $this->post(route('notificacoes.teste'))->assertNotFound();

        $this->postJson(route('telegram.webhook', ['tenant' => $tenant]), ['message' => ['chat' => ['id' => 1, 'type' => 'private'], 'text' => '/start codigo-valido-123']],
            ['X-Telegram-Bot-Api-Secret-Token' => NotificacaoConfig::segredoWebhook($tenant)])->assertNotFound();
        $this->post(route('notificacoes.relogio', ['tenant' => $tenant, 'chave' => NotificacaoConfig::chaveRelogio($tenant)]))->assertNotFound();

        app(NotificacaoService::class)->processar($tenant);
        app(NotificacaoService::class)->enviarTeste($tenant);
        $this->artisan('notificacoes:processar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_familia_liberada_ve_o_menu(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee('Avisos no Telegram');
    }
}
