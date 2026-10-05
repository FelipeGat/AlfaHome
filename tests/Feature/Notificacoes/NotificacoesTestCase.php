<?php

namespace Tests\Feature\Notificacoes;

use App\Models\NotificacaoConfig;
use App\Models\TelegramDestinatario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

abstract class NotificacoesTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TOKEN = '123456789:AAHtoken-de-teste-do-bot-xyz';

    protected User $user;

    /** Resposta do sendMessage; trocada nos testes de falha. */
    protected \Closure $envio;

    /** Resposta do getMe; trocada no teste de token inválido. */
    protected \Closure $getMe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        // Nos testes os ids de família variam: a lista começa vazia e cada teste libera quem precisa.
        config(['services.notificacoes.tenants' => []]);
        $this->liberar($this->user);
        $this->envio = fn () => Http::response(['ok' => true, 'result' => ['message_id' => 1]]);
        $this->getMe = fn () => Http::response(['ok' => true, 'result' => ['id' => 1, 'is_bot' => true, 'username' => 'familia_bot']]);

        Http::fake([
            'api.telegram.org/*/getMe'       => fn ($r) => ($this->getMe)($r),
            'api.telegram.org/*/setWebhook'  => Http::response(['ok' => true, 'result' => true]),
            'api.telegram.org/*/sendMessage' => fn ($r) => ($this->envio)($r),
        ]);
    }

    /** Inclui a família na lista das que podem usar os avisos. */
    protected function liberar(User $user): void
    {
        config(['services.notificacoes.tenants' => array_merge(config('services.notificacoes.tenants'), [$user->tenant_id])]);
    }

    protected function configurar(?User $user = null, array $extra = []): NotificacaoConfig
    {
        $user ??= $this->user;

        return NotificacaoConfig::withoutGlobalScopes()->create([
            'tenant_id' => $user->tenant_id, 'telegram_token' => self::TOKEN, 'telegram_bot' => 'familia_bot',
            'codigo_vinculo' => 'codigo-valido-123', 'codigo_vinculo_ate' => now()->addDays(7), 'ligado_em' => now(),
        ] + $extra);
    }

    protected function destinatario(int $chatId = 1001, ?User $user = null): TelegramDestinatario
    {
        return TelegramDestinatario::withoutGlobalScopes()->create([
            'tenant_id' => ($user ?? $this->user)->tenant_id, 'chat_id' => $chatId, 'nome' => 'Felipe', 'ativo' => true,
        ]);
    }

    /** Textos enviados pelo sendMessage, na ordem. */
    protected function enviadas(?int $chatId = null): array
    {
        return Http::recorded(fn ($r) => str_ends_with($r->url(), '/sendMessage') && ($chatId === null || $r['chat_id'] === $chatId))
            ->map(fn ($par) => $par[0]['text'])->values()->all();
    }
}
