<?php

namespace App\Support;

/**
 * Formatação única de dinheiro no site: "R$ 1.250,00" e "-R$ 98,15" (o sinal
 * vem antes do símbolo). Valor ausente vira traço, nunca zero.
 */
final class Dinheiro
{
    public static function brl(int|float|string|null $valor, string $nulo = '—'): string
    {
        if ($valor === null || $valor === '') {
            return $nulo;
        }

        $centavos = (int) round(((float) $valor) * 100);
        $texto    = 'R$ ' . number_format(abs($centavos) / 100, 2, ',', '.');

        return $centavos < 0 ? '-' . $texto : $texto;
    }
}
