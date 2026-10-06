<?php

use App\Support\Dinheiro;

if (! function_exists('brl')) {
    /** "R$ 1.250,00" / "-R$ 98,15" — ver App\Support\Dinheiro. */
    function brl(int|float|string|null $valor, string $nulo = '—'): string
    {
        return Dinheiro::brl($valor, $nulo);
    }
}
