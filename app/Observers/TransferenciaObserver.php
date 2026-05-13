<?php

namespace App\Observers;

use App\Models\Banco;
use App\Models\Transferencia;

/**
 * Mantém saldos de banco em sincronia com transferências.
 *
 * Eventos cobertos:
 *  - created : debita origem, credita destino.
 *  - updating: reverte estado anterior, aplica o novo (valor / origem / destino).
 *  - deleting: estorna na hora do delete (soft OU force) — mas apenas uma vez,
 *              guardado pela flag `_saldo_estornado` evitando dupla execução.
 *  - restored: re-aplica o efeito original quando registro é restaurado.
 */
class TransferenciaObserver
{
    public function created(Transferencia $t): void
    {
        $this->aplicar($t->origem_id, $t->destino_id, (float) $t->valor);
    }

    public function updating(Transferencia $t): void
    {
        // Não mexer em saldos quando a única alteração é soft delete / restore;
        // esses são tratados em deleting/restored.
        if ($t->isDirty('deleted_at') && ! $t->isDirty(['valor', 'origem_id', 'destino_id'])) {
            return;
        }

        // Reverte estado anterior
        $this->aplicar(
            origemId: $t->getOriginal('destino_id'),
            destinoId: $t->getOriginal('origem_id'),
            valor: (float) $t->getOriginal('valor'),
        );

        // Aplica novo estado
        $this->aplicar($t->origem_id, $t->destino_id, (float) $t->valor);
    }

    public function deleting(Transferencia $t): void
    {
        // Já estornado em soft delete anterior? Não estornar de novo no force.
        if ($t->trashed()) {
            return;
        }

        // Estorna: credita origem, debita destino
        $this->aplicar(
            origemId: $t->destino_id,
            destinoId: $t->origem_id,
            valor: (float) $t->valor,
        );
    }

    public function restored(Transferencia $t): void
    {
        // Re-aplica o efeito da transferência ao restaurar
        $this->aplicar($t->origem_id, $t->destino_id, (float) $t->valor);
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
            $origem = Banco::withoutGlobalScope('tenant')->find($origemId);
            if ($origem) {
                $origem->decrement('saldo', $valor);
            }
        }

        if ($destinoId) {
            $destino = Banco::withoutGlobalScope('tenant')->find($destinoId);
            if ($destino) {
                $destino->increment('saldo', $valor);
            }
        }
    }
}
