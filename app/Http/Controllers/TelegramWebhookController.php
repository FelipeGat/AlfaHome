<?php

namespace App\Http\Controllers;

use App\Models\NotificacaoConfig;
use App\Models\TelegramDestinatario;
use App\Services\Notificacoes\TelegramClient;
use Illuminate\Http\Request;

/**
 * Recebe as mensagens do bot. Por enquanto só trata o /start do link de
 * vínculo; o Telegram identifica a família pela rota e prova a origem com o
 * segredo do cabeçalho.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, int $tenant, TelegramClient $telegram)
    {
        $segredo = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless(hash_equals(NotificacaoConfig::segredoWebhook($tenant), $segredo), 403);

        $config = NotificacaoConfig::withoutGlobalScopes()->where('tenant_id', $tenant)->first();
        $chatId = $request->input('message.chat.id');
        $texto  = trim((string) $request->input('message.text'));
        if (! $config?->telegram_token || ! is_numeric($chatId) || $request->input('message.chat.type') !== 'private') {
            return response()->json(['ok' => true]);
        }

        if (str_starts_with($texto, '/start')) {
            $codigo = trim(substr($texto, 6));
            $valido = $codigo !== '' && $config->codigo_vinculo && $config->codigo_vinculo_ate?->isFuture()
                && hash_equals($config->codigo_vinculo, $codigo);

            if (! $valido) {
                $telegram->enviar($config->telegram_token, (int) $chatId, 'Esse link não vale mais. Peça um novo na tela Notificações do AlfaHome.');

                return response()->json(['ok' => true]);
            }

            $nome = trim($request->input('message.from.first_name', '') . ' ' . $request->input('message.from.last_name', '')) ?: null;
            TelegramDestinatario::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenant, 'chat_id' => (int) $chatId],
                ['nome' => $nome, 'ativo' => true, 'motivo_inativo' => null]
            );
            $telegram->enviar($config->telegram_token, (int) $chatId,
                'Pronto' . ($nome ? ', ' . e($request->input('message.from.first_name')) : '') . '! Você vai receber os avisos da família: resumo às 7h, contas a pagar e a receber, saldo negativo e cartão.');

            return response()->json(['ok' => true]);
        }

        $vinculado = TelegramDestinatario::withoutGlobalScopes()->where('tenant_id', $tenant)->where('chat_id', (int) $chatId)->where('ativo', true)->exists();
        $telegram->enviar($config->telegram_token, (int) $chatId, $vinculado
            ? 'Por enquanto eu só envio os avisos. Em breve dá para lançar despesas por aqui.'
            : 'Para receber os avisos, abra o link da tela Notificações do AlfaHome.');

        return response()->json(['ok' => true]);
    }
}
