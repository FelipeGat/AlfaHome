<?php

namespace App\Services\Notificacoes;

use Illuminate\Support\Facades\Http;

/**
 * Chamadas à Bot API do Telegram. Cada método devolve
 * ['ok' => bool, 'resultado' => mixed, 'erro' => ?string, 'bloqueado' => bool];
 * "bloqueado" indica que o chat não vai mais receber (bot bloqueado ou chat
 * inexistente) e que não adianta tentar de novo.
 */
class TelegramClient
{
    private const API = 'https://api.telegram.org/bot';

    public function getMe(string $token): array
    {
        return $this->chamar($token, 'getMe', []);
    }

    public function setWebhook(string $token, string $url, string $segredo): array
    {
        return $this->chamar($token, 'setWebhook', [
            'url'             => $url,
            'secret_token'    => $segredo,
            'allowed_updates' => ['message'],
        ]);
    }

    public function enviar(string $token, int $chatId, string $texto): array
    {
        return $this->chamar($token, 'sendMessage', [
            'chat_id'                  => $chatId,
            'text'                     => $texto,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    private function chamar(string $token, string $metodo, array $dados): array
    {
        try {
            $r = Http::timeout(15)->asJson()->post(self::API . $token . '/' . $metodo, $dados);
        } catch (\Throwable $e) {
            return ['ok' => false, 'resultado' => null, 'erro' => 'Sem resposta do Telegram', 'bloqueado' => false];
        }

        if ($r->successful() && $r->json('ok') === true) {
            return ['ok' => true, 'resultado' => $r->json('result'), 'erro' => null, 'bloqueado' => false];
        }

        $descricao = (string) ($r->json('description') ?? 'HTTP ' . $r->status());
        $bloqueado = $r->status() === 403
            || ($r->status() === 400 && str_contains(strtolower($descricao), 'chat not found'));

        return ['ok' => false, 'resultado' => null, 'erro' => mb_substr($descricao, 0, 250), 'bloqueado' => $bloqueado];
    }
}
