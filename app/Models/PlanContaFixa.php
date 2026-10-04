<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanContaFixa extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_contas_fixas';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'conta', 'categoria', 'dia_vencimento', 'valor_previsto', 'valor_realizado', 'forma', 'recorrente', 'status', 'status_planilha', 'mes_inicial', 'observacao'];

    protected $casts = [
        'valor_previsto' => 'decimal:2',
        'valor_realizado' => 'decimal:2',
        'recorrente' => 'boolean',
        'mes_inicial' => 'date',
    ];
}
