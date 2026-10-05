<?php

namespace Tests\Support;

use App\Models\Banco;
use App\Models\SaldoAjuste;

/**
 * O saldo calculado conta só o que acontece depois do saldo informado. Nos
 * testes que movimentam datas passadas, a conta precisa existir desde antes
 * delas: o saldo inicial passa a valer desde 2000.
 */
trait ContaDesdeSempre
{
    protected function desdeSempre(Banco $banco): Banco
    {
        SaldoAjuste::withoutGlobalScopes()->where('banco_id', $banco->id)->update(['data' => '2000-01-01']);

        return $banco;
    }
}
