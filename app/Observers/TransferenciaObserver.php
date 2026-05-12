<?php

namespace App\Observers;

use App\Models\Banco;
use App\Models\Transferencia;

/**
 * Mantém saldos de banco em sincronia com transferências.
 *
 * created  → debita origem, credita destino.
 * updating → reverte estado antigo e aplica o novo (valor / origem / destino).
 * deleted  → estorna (credita origem, debita destino).
 */
class TransferenciaObserver
{
    public function created(Transferencia $t): void
    {
        $this->aplicar($t->origem_id, $t->destino_id, (float) $t->valor);
    }

    public function updating(Transferencia $t): void
    {
        // Reverte estado anterior
        $this->aplicar(
            origemId: $t->getOriginal('destino_id'),
            destinoId: $t->getOriginal('origem_id'),
            valor: (float) $t->getOriginal('valor'),
        );

        // Aplica novo estado
        $this->aplicar($t->origem_id, $t->destino_id, (float) $t->valor);
    }

    public function deleted(Transferencia $t): void
    {
        if ($t->isForceDeleting()) {
            return; // already stripped by hard delete; saldos foram tratados no soft delete
        }

        // Estorna: credita origem, debita destino
        $this->aplicar(
            origemId: $t->destino_id,
            destinoId: $t->origem_id,
            valor: (float) $t->valor,
        );
    }

    /**
     * Debita `valor` de `origemId` e credita `destinoId`.
     */
    private function aplicar(?int $origemId, ?int $destinoId, float $valor): void
    {
        if ($valor <= 0) {
            return;
        }

        if ($origemId) {
            $origem = Banco::find($origemId);
            if ($origem) {
                $origem->decrement('saldo', $valor);
            }
        }

        if ($destinoId) {
            $destino = Banco::find($destinoId);
            if ($destino) {
                $destino->increment('saldo', $valor);
            }
        }
    }
}
