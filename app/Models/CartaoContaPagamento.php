<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Conta que paga a fatura de um cartão da planilha (ver migration). */
class CartaoContaPagamento extends Model
{
    use BelongsToTenant;

    protected $table = 'cartao_contas_pagamento';

    protected $fillable = ['tenant_id', 'cartao', 'banco_id'];

    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }
}
