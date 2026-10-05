<?php

namespace App\Http\Controllers;

use App\Models\NotificacaoConfig;
use App\Models\NotificacaoEnvio;
use App\Models\TelegramDestinatario;
use App\Services\Notificacoes\NotificacaoService;
use App\Services\Notificacoes\TelegramClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Tela Notificações: bot do Telegram, quem recebe, tipos de aviso e relógio. Só o dono da conta. */
class NotificacaoController extends Controller
{
    public function index()
    {
        $this->somenteDono();
        $tenantId = Auth::user()->tenant_id;
        $config   = NotificacaoConfig::where('tenant_id', $tenantId)->first();

        return view('notificacoes.index', [
            'config'        => $config,
            'link'          => $config?->telegram_bot && $config->codigo_vinculo && $config->codigo_vinculo_ate?->isFuture()
                ? "https://t.me/{$config->telegram_bot}?start={$config->codigo_vinculo}" : null,
            'destinatarios' => TelegramDestinatario::where('tenant_id', $tenantId)->orderBy('created_at')->get(),
            'envios'        => NotificacaoEnvio::where('tenant_id', $tenantId)->with('destinatario')->latest('id')->limit(15)->get(),
            'relogio'       => $config?->telegram_token
                ? route('notificacoes.relogio', ['tenant' => $tenantId, 'chave' => NotificacaoConfig::chaveRelogio($tenantId)]) : null,
        ]);
    }

    public function salvarToken(Request $request, TelegramClient $telegram)
    {
        $this->somenteDono();
        $request->validate(
            ['token' => ['required', 'string', 'max:100', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/']],
            ['token.required' => 'Cole o token que o BotFather mandou.', 'token.regex' => 'Isso não parece um token de bot (formato 123456:ABC...).']
        );

        $token = trim($request->input('token'));
        $me    = $telegram->getMe($token);
        if (! $me['ok']) {
            return back()->withErrors(['token' => 'O Telegram não reconheceu esse token.']);
        }

        $tenantId = Auth::user()->tenant_id;
        $config   = NotificacaoConfig::firstOrNew(['tenant_id' => $tenantId]);
        $config->fill([
            'telegram_token'     => $token,
            'telegram_bot'       => $me['resultado']['username'] ?? null,
            'codigo_vinculo'     => Str::random(24),
            'codigo_vinculo_ate' => now()->addDays(7),
        ]);
        $config->ligado_em ??= now();
        $config->save();

        $webhook = $telegram->setWebhook($token, route('telegram.webhook', ['tenant' => $tenantId]), NotificacaoConfig::segredoWebhook($tenantId));
        if (! $webhook['ok']) {
            return redirect()->route('notificacoes.index')
                ->with('warning', "Bot @{$config->telegram_bot} salvo, mas o Telegram não aceitou o endereço de retorno ({$webhook['erro']}). O vínculo pelo link só funciona no servidor de produção.");
        }

        return redirect()->route('notificacoes.index')->with('success', "Bot @{$config->telegram_bot} salvo. Abra o link abaixo no Telegram e toque em Iniciar.");
    }

    public function gerarLink()
    {
        $this->somenteDono();
        $config = $this->configComBot();
        $config->update(['codigo_vinculo' => Str::random(24), 'codigo_vinculo_ate' => now()->addDays(7)]);

        return redirect()->route('notificacoes.index')->with('success', 'Link novo gerado; o anterior deixou de valer.');
    }

    public function tipos(Request $request)
    {
        $this->somenteDono();
        $config  = $this->configComBot();
        $ligados = array_keys(array_filter((array) $request->input('tipos', [])));
        $config->update(['tipos_desligados' => array_values(array_diff(array_keys(NotificacaoConfig::TIPOS), $ligados))]);

        return redirect()->route('notificacoes.index')->with('success', 'Preferências salvas.');
    }

    public function teste(NotificacaoService $servico)
    {
        $this->somenteDono();
        $this->configComBot();
        $r = $servico->enviarTeste(Auth::user()->tenant_id);

        return redirect()->route('notificacoes.index')->with(
            $r['falhas'] ? 'warning' : 'success',
            $r['enviados'] || $r['falhas']
                ? "Teste: {$r['enviados']} enviado(s), {$r['falhas']} com falha."
                : 'Ninguém vinculado ainda: abra o link no Telegram primeiro.'
        );
    }

    public function removerDestinatario(TelegramDestinatario $destinatario)
    {
        $this->somenteDono();
        abort_unless($destinatario->tenant_id === Auth::user()->tenant_id, 404);
        $destinatario->delete();

        return redirect()->route('notificacoes.index')->with('success', 'Pessoa removida dos avisos.');
    }

    private function configComBot(): NotificacaoConfig
    {
        $config = NotificacaoConfig::where('tenant_id', Auth::user()->tenant_id)->first();
        abort_unless($config?->telegram_token, 404);

        return $config;
    }

    private function somenteDono(): void
    {
        abort_unless(Auth::user()->role === 'master', 403, 'Somente o dono da conta configura os avisos.');
    }
}
