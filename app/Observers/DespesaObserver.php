<?php

namespace App\Observers;

use App\Models\Banco;
use App\Models\Despesa;

/**
 * Mantém saldos de banco em sincronia com despesas.
 *
 * Dois eixos independentes:
 *
 *  A) Saldo da conta corrente (bancos.saldo) — só é afetado quando
 *     uma despesa NÃO-crédito é marcada como paga / desmarcada / excluída.
 *
 *  B) Saldo do cartão de crédito / fatura aberta (bancos.saldo_cartao)
 *     — é afetado por qualquer despesa de crédito EM ABERTO. Quando a
 *     despesa é paga (sai da fatura) ou estornada, o saldo_cartao recua.
 *     Quando uma despesa de crédito é criada/editada, o saldo_cartao
 *     reflete a contribuição daquela despesa para a fatura aberta.
 *
 * Padrão usado para updating: "reverter estado antigo + aplicar novo",
 * cobrindo mudanças simultâneas de valor / forma_pagamento / tipo /
 * data_pagamento sem casos especiais por campo.
 */
class DespesaObserver
{
    public function created(Despesa $despesa): void
    {
        // A) Conta corrente: despesa criada já como paga → debitar
        if ($despesa->data_pagamento && $despesa->tipo_pagamento !== 'credito') {
            $this->debitarBanco($despesa);
        }

        // B) Cartão: se entra na fatura aberta, soma ao saldo_cartao
        $this->aplicarFatura($despesa, +1);
    }

    public function updating(Despesa $despesa): void
    {
        // A) Conta corrente — só muda quando data_pagamento muda E não é crédito
        if ($despesa->isDirty('data_pagamento') && $despesa->tipo_pagamento !== 'credito') {
            $antigo = $despesa->getOriginal('data_pagamento');
            $novo   = $despesa->data_pagamento;

            if (is_null($antigo) && ! is_null($novo)) {
                $this->debitarBanco($despesa);
            } elseif (! is_null($antigo) && is_null($novo)) {
                $this->creditarBanco($despesa);
            }
        }

        // B) Cartão — reverter contribuição antiga e aplicar a nova.
        //    Cobre mudanças de valor, forma_pagamento, tipo_pagamento e
        //    data_pagamento de forma uniforme.
        if ($despesa->isDirty(['tipo_pagamento', 'forma_pagamento', 'valor', 'data_pagamento'])) {
            $this->reverterFaturaOriginal($despesa);
            $this->aplicarFatura($despesa, +1);
        }
    }

    public function deleted(Despesa $despesa): void
    {
        // A) Conta corrente: se estava paga, devolver o saldo
        if ($despesa->data_pagamento && $despesa->tipo_pagamento !== 'credito') {
            $this->creditarBanco($despesa);
        }

        // B) Cartão: se contribuía para a fatura aberta, recuar
        $this->aplicarFatura($despesa, -1);
    }

    // ─── Conta corrente ────────────────────────────────────────────────────

    private function debitarBanco(Despesa $despesa): void
    {
        $banco = Banco::find($despesa->forma_pagamento);
        if ($banco) {
            $banco->decrement('saldo', (float) $despesa->valor);
        }
    }

    private function creditarBanco(Despesa $despesa): void
    {
        $banco = Banco::find($despesa->forma_pagamento);
        if ($banco) {
            $banco->increment('saldo', (float) $despesa->valor);
        }
    }

    // ─── Cartão de crédito (fatura aberta) ─────────────────────────────────

    /**
     * Aplica a contribuição atual da despesa ao saldo_cartao do banco
     * em `forma_pagamento`, multiplicada por $sign (+1 soma, -1 subtrai).
     *
     * Uma despesa contribui para a fatura quando:
     *   - tipo_pagamento === 'credito'
     *   - forma_pagamento aponta para um banco existente
     *   - data_pagamento é null (ainda não foi paga)
     */
    private function aplicarFatura(Despesa $despesa, int $sign): void
    {
        $contribuicao = $this->contribuicaoFatura(
            tipoPagamento: $despesa->tipo_pagamento,
            bancoId: $despesa->forma_pagamento,
            dataPagamento: $despesa->data_pagamento,
            valor: (float) $despesa->valor,
        );

        if ($contribuicao <= 0) {
            return;
        }

        $banco = Banco::find($despesa->forma_pagamento);
        if (! $banco) {
            return;
        }

        if ($sign > 0) {
            $banco->increment('saldo_cartao', $contribuicao);
        } else {
            $banco->decrement('saldo_cartao', $contribuicao);
        }
    }

    /**
     * Reverte a contribuição que a despesa tinha ANTES do update — usa
     * `getOriginal()` para inspecionar valores anteriores ao isDirty.
     */
    private function reverterFaturaOriginal(Despesa $despesa): void
    {
        $contribuicao = $this->contribuicaoFatura(
            tipoPagamento: $despesa->getOriginal('tipo_pagamento'),
            bancoId: $despesa->getOriginal('forma_pagamento'),
            dataPagamento: $despesa->getOriginal('data_pagamento'),
            valor: (float) $despesa->getOriginal('valor'),
        );

        if ($contribuicao <= 0) {
            return;
        }

        $bancoOriginal = Banco::find($despesa->getOriginal('forma_pagamento'));
        if ($bancoOriginal) {
            $bancoOriginal->decrement('saldo_cartao', $contribuicao);
        }
    }

    private function contribuicaoFatura(
        ?string $tipoPagamento,
        ?int $bancoId,
        $dataPagamento,
        float $valor,
    ): float {
        if ($tipoPagamento !== 'credito') return 0.0;
        if ($bancoId === null)            return 0.0;
        if ($dataPagamento !== null)      return 0.0;

        return $valor;
    }
}
