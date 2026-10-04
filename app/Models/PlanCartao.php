<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanCartao extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_cartoes';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'nome', 'banco', 'limite_total', 'limite_utilizado', 'dia_fechamento', 'dia_vencimento', 'fatura_atual', 'status_fatura', 'observacao'];

    protected $casts = [
        'limite_total' => 'decimal:2',
        'limite_utilizado' => 'decimal:2',
        'fatura_atual' => 'decimal:2',
    ];

    /** Limite total menos o utilizado; nulo quando a planilha não informa os dois. */
    public function getLimiteDisponivelAttribute(): ?float
    {
        if ($this->limite_total === null || $this->limite_utilizado === null) {
            return null;
        }

        return round((float) $this->limite_total - (float) $this->limite_utilizado, 2);
    }

    public function getUtilizadoPctAttribute(): ?float
    {
        if ($this->limite_total === null || $this->limite_utilizado === null) {
            return null;
        }

        return (float) $this->limite_total > 0
            ? round((float) $this->limite_utilizado / (float) $this->limite_total * 100, 2)
            : 0.0;
    }
}
