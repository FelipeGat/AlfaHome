<?php

namespace App\Services\Financeiro;

/**
 * Ponto único para pedir o recálculo dos saldos depois de qualquer mudança
 * (lançamento do sistema, transferência, importação da planilha, ajuste).
 */
final class RecalcularSaldos
{
    public static function para(?int $tenantId): void
    {
        if ($tenantId) {
            app(FinanceiroService::class)->recalcular($tenantId);
        }
    }
}
