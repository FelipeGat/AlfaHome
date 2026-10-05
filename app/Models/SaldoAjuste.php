<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Saldo real informado de uma conta numa data: base do saldo calculado. */
class SaldoAjuste extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'banco_id', 'user_id', 'data', 'saldo', 'diferenca', 'observacao'];

    protected $casts = [
        'data'      => 'date',
        'saldo'     => 'decimal:2',
        'diferenca' => 'decimal:2',
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class);
    }
}
