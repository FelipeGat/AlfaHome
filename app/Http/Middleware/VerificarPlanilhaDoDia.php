<?php

namespace App\Http\Middleware;

use App\Models\PlanilhaFonte;
use App\Services\Planejamento\PlanilhaFonteService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verificação diária da planilha sem depender do agendador do servidor: o
 * primeiro acesso do dia de cada família dispara a análise, depois de a
 * resposta já ter sido enviada.
 */
class VerificarPlanilhaDoDia
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $tenantId = $request->user()?->tenant_id;
        if ($tenantId && $this->reservarVerificacaoDeHoje($tenantId)) {
            $request->attributes->set('planilha.verificar', $tenantId);
        }

        return $resposta;
    }

    /** Roda depois de a resposta ter sido enviada: quem acessou não espera o OneDrive. */
    public function terminate(Request $request, Response $response): void
    {
        $tenantId = $request->attributes->get('planilha.verificar');
        $fonte    = $tenantId ? PlanilhaFonte::withoutGlobalScopes()->where('tenant_id', $tenantId)->first() : null;
        if (! $fonte) {
            return;
        }

        try {
            app(PlanilhaFonteService::class)->analisar($fonte, null, automatica: true);
        } catch (\Throwable $e) {
            Log::warning('Verificação diária da planilha falhou', ['tenant' => $tenantId, 'erro' => $e->getMessage()]);
        }
    }

    /**
     * Marca a verificação de hoje como iniciada. A atualização condicional
     * garante que, entre acessos simultâneos, só um dispare a análise.
     */
    private function reservarVerificacaoDeHoje(int $tenantId): bool
    {
        return PlanilhaFonte::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->whereNull('verificada_em')->orWhere('verificada_em', '<', today()))
            ->update(['verificada_em' => now()]) === 1;
    }
}
