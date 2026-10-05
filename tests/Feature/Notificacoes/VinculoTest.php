<?php

namespace Tests\Feature\Notificacoes;

use App\Models\NotificacaoConfig;
use App\Models\TelegramDestinatario;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class VinculoTest extends NotificacoesTestCase
{
    private function webhook(int $tenant, string $texto, ?string $segredo = null, int $chatId = 555)
    {
        return $this->postJson(route('telegram.webhook', ['tenant' => $tenant]), [
            'update_id' => 1,
            'message'   => ['chat' => ['id' => $chatId, 'type' => 'private'], 'from' => ['first_name' => 'Ju', 'last_name' => 'Gat'], 'text' => $texto],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $segredo ?? NotificacaoConfig::segredoWebhook($tenant)]);
    }

    public function test_salvar_token_valida_no_telegram_registra_webhook_e_guarda_cifrado(): void
    {
        $this->actingAs($this->user)->post(route('notificacoes.token'), ['token' => self::TOKEN])
            ->assertRedirect(route('notificacoes.index'))
            ->assertSessionHas('success');

        $config = NotificacaoConfig::first();
        $this->assertSame(self::TOKEN, $config->telegram_token);
        $this->assertSame('familia_bot', $config->telegram_bot);
        $this->assertNotNull($config->ligado_em);
        $this->assertArrayNotHasKey('telegram_token', $config->toArray());
        $this->assertStringNotContainsString('AAHtoken', DB::table('notificacao_configs')->value('telegram_token'));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/setWebhook')
            && $r['url'] === route('telegram.webhook', ['tenant' => $this->user->tenant_id])
            && $r['secret_token'] === NotificacaoConfig::segredoWebhook($this->user->tenant_id));

        $this->get(route('notificacoes.index'))->assertOk()
            ->assertSee('@familia_bot')
            ->assertSee('https://t.me/familia_bot?start=' . $config->codigo_vinculo)
            ->assertDontSee('AAHtoken');
    }

    public function test_token_que_o_telegram_nao_reconhece_e_recusado(): void
    {
        $this->getMe = fn () => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401);

        $this->actingAs($this->user)->post(route('notificacoes.token'), ['token' => self::TOKEN])
            ->assertSessionHasErrors(['token' => 'O Telegram não reconheceu esse token.']);
        $this->post(route('notificacoes.token'), ['token' => 'nao-e-token'])->assertSessionHasErrors('token');

        $this->assertSame(0, NotificacaoConfig::count());
    }

    public function test_start_com_codigo_valido_vincula_e_responde(): void
    {
        $this->configurar();

        $this->webhook($this->user->tenant_id, '/start codigo-valido-123')->assertOk();

        $d = TelegramDestinatario::withoutGlobalScopes()->first();
        $this->assertSame(555, $d->chat_id);
        $this->assertSame('Ju Gat', $d->nome);
        $this->assertTrue($d->ativo);
        $this->assertStringStartsWith('Pronto, Ju!', $this->enviadas(555)[0]);
    }

    public function test_codigo_invalido_ou_vencido_nao_vincula(): void
    {
        $this->configurar();
        $this->webhook($this->user->tenant_id, '/start outro-codigo')->assertOk();
        $this->webhook($this->user->tenant_id, '/start')->assertOk();

        $this->travel(8)->days();
        $this->webhook($this->user->tenant_id, '/start codigo-valido-123')->assertOk();

        $this->assertSame(0, TelegramDestinatario::withoutGlobalScopes()->count());
        $this->assertCount(3, $this->enviadas());
        $this->assertStringContainsString('não vale mais', $this->enviadas()[2]);
    }

    public function test_webhook_sem_o_segredo_da_familia_e_recusado(): void
    {
        $this->configurar();
        $outra = User::factory()->create();
        $this->configurar($outra);

        $this->webhook($this->user->tenant_id, '/start codigo-valido-123', 'segredo-errado')->assertForbidden();
        // Segredo de outra família também não serve.
        $this->webhook($this->user->tenant_id, '/start codigo-valido-123', NotificacaoConfig::segredoWebhook($outra->tenant_id))->assertForbidden();

        $this->assertSame(0, TelegramDestinatario::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }

    public function test_remover_pessoa_e_permissoes(): void
    {
        $this->configurar();
        $d = $this->destinatario();

        $membro = User::factory()->create(['tenant_id' => $this->user->tenant_id, 'role' => 'membro']);
        $this->actingAs($membro)->get(route('notificacoes.index'))->assertForbidden();

        $outro = User::factory()->create();
        $this->actingAs($outro)->delete(route('notificacoes.destinatarios.remover', $d))->assertNotFound();

        $this->actingAs($this->user)->delete(route('notificacoes.destinatarios.remover', $d))->assertRedirect(route('notificacoes.index'));
        $this->assertSame(0, TelegramDestinatario::withoutGlobalScopes()->count());
    }
}
