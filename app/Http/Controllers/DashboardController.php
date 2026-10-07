<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\Financeiro\FinanceiroService;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        // Toda família vê o Início novo, com ou sem planilha: os números vêm
        // do FinanceiroService (planilha e lançamentos do sistema).
        return view('dashboard', $this->inicioNovo($request, $tenantId) + ['hoje' => ['planilha_importada' => true]]);
    }

    /**
     * Dados do Início: saldo, mês escolhido, últimas
     * movimentações, próximos pagamentos e resumo rápido.
     */
    private function inicioNovo(Request $request, int $tenantId): array
    {
        $visao    = app(FinanceiroService::class)->inicio($tenantId);

        // Mês do resumo (?mes=AAAA-MM). Saldo e movimentações são sempre de hoje.
        $mesVisao = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes'))
            ? Carbon::createFromFormat('!Y-m', $request->query('mes'))
            : now()->startOfMonth();
        if (! $mesVisao->isSameMonth(now())) {
            $visao['mes'] = app(FinanceiroService::class)->mes($tenantId, $mesVisao);
        }
        return [
            'visao'       => $visao,
            'mesVisao'    => $mesVisao,
            'mesesVisao'  => collect(range(0, 11))->map(fn ($n) => now()->startOfMonth()->subMonths($n)),
            'vencidas'    => $visao['vencidas'],
            'cartoesInfo' => $visao['cartoes'],
        ];
    }
}
