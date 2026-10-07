<?php

namespace App\Http\Requests\Concerns;

/** Aceita "96,85" e "1.234,56" no valor, como se digita no Brasil. */
trait AceitaValorComVirgula
{
    protected function prepareForValidation(): void
    {
        $v = $this->input('valor');
        if (is_string($v) && str_contains($v, ',')) {
            $this->merge(['valor' => str_replace(',', '.', str_replace('.', '', trim($v)))]);
        }
    }
}
