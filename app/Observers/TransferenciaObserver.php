<?php

namespace App\Observers;

use App\Models\Transferencia;
use App\Services\Financeiro\RecalcularSaldos;

/**
 * Transferência entre contas próprias: tira de uma conta e põe na outra, sem
 * ser entrada nem saída. O efeito nos saldos é calculado pelo FinanceiroService;
 * aqui só se pede o recálculo.
 */
class TransferenciaObserver
{
    public function saved(Transferencia $t): void
    {
        RecalcularSaldos::para($t->tenant_id);
    }

    public function deleted(Transferencia $t): void
    {
        RecalcularSaldos::para($t->tenant_id);
    }

    public function restored(Transferencia $t): void
    {
        RecalcularSaldos::para($t->tenant_id);
    }
}
