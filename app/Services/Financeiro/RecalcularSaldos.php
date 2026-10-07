<?php

namespace App\Services\Financeiro;

/**
 * Ponto único para pedir o recálculo dos saldos depois de qualquer mudança
 * (lançamento do sistema, transferência, importação da planilha, ajuste).
 *
 * Dentro de `emLote()` os pedidos são juntados e o recálculo roda uma vez por
 * família no fim (ex.: 60 parcelas de uma recorrência).
 */
final class RecalcularSaldos
{
    private static int $lote = 0;

    /** @var array<int, true> */
    private static array $pendentes = [];

    public static function para(?int $tenantId): void
    {
        if (! $tenantId) {
            return;
        }
        if (self::$lote > 0) {
            self::$pendentes[$tenantId] = true;

            return;
        }
        app(FinanceiroService::class)->recalcular($tenantId);
    }

    /** Executa `$fn` juntando os recálculos; recalcula cada família uma vez no fim. */
    public static function emLote(callable $fn): mixed
    {
        self::$lote++;
        try {
            $resultado = $fn();
        } finally {
            self::$lote--;
        }
        if (self::$lote === 0) {
            $tenants = array_keys(self::$pendentes);
            self::$pendentes = [];
            foreach ($tenants as $t) {
                app(FinanceiroService::class)->recalcular($t);
            }
        }

        return $resultado;
    }
}
