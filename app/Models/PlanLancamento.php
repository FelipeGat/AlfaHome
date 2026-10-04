<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanLancamento extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_lancamentos';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'data', 'tipo', 'descricao', 'categoria', 'categoria_id', 'forma', 'conta', 'valor_previsto', 'valor_realizado', 'status', 'status_planilha', 'observacao', 'id_planilha'];

    protected $casts = [
        'data' => 'date',
        'valor_previsto' => 'decimal:2',
        'valor_realizado' => 'decimal:2',
    ];

    public function categoriaModel()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
