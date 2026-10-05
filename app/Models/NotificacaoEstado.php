<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Último estado conhecido de um alerta contínuo (saldo negativo, limite acima de 80%). */
class NotificacaoEstado extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'chave', 'em_alerta', 'vezes'];

    protected $casts = ['em_alerta' => 'boolean'];
}
