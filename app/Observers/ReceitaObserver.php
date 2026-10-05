<?php

namespace App\Observers;

use App\Models\Receita;
use App\Services\Financeiro\RecalcularSaldos;

/**
 * O saldo da conta é calculado pelo FinanceiroService (último ajuste +
 * movimentos realizados); receita criada, alterada, excluída ou restaurada só
 * pede o recálculo.
 */
class ReceitaObserver
{
    public function saved(Receita $receita): void
    {
        RecalcularSaldos::para($receita->tenant_id);
    }

    public function deleted(Receita $receita): void
    {
        RecalcularSaldos::para($receita->tenant_id);
    }

    public function restored(Receita $receita): void
    {
        RecalcularSaldos::para($receita->tenant_id);
    }
}
