<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transferencia extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'transferencias';

    protected $fillable = [
        'tenant_id', 'user_id', 'valor', 'data',
        'origem_id', 'destino_id', 'observacao',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data'  => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function origem()
    {
        return $this->belongsTo(Banco::class, 'origem_id');
    }

    public function destino()
    {
        return $this->belongsTo(Banco::class, 'destino_id');
    }
}
