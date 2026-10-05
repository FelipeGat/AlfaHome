<?php

namespace App\Http\Controllers;

use App\Models\NotificacaoConfig;
use App\Services\Notificacoes\NotificacaoService;

/**
 * Relógio dos avisos: o servidor de produção não tem agendador, então um
 * agendamento externo (GitHub Actions) chama este endereço a cada 15 minutos.
 * A chave é derivada da APP_KEY e só aparece para o dono da conta.
 */
class RelogioController extends Controller
{
    public function __invoke(int $tenant, string $chave, NotificacaoService $servico)
    {
        abort_unless(hash_equals(NotificacaoConfig::chaveRelogio($tenant), $chave), 403);

        return response()->json($servico->relogio($tenant));
    }
}
