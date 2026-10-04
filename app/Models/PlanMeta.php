<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanMeta extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_metas';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'nome', 'objetivo', 'valor_alvo', 'valor_atual', 'prazo', 'prioridade', 'aporte_mensal', 'observacao'];

    protected $casts = [
        'valor_alvo' => 'decimal:2',
        'valor_atual' => 'decimal:2',
        'prazo' => 'date',
        'aporte_mensal' => 'decimal:2',
    ];

    public function getFaltaAttribute(): ?float
    {
        if ($this->valor_alvo === null) {
            return null;
        }

        return round(max((float) $this->valor_alvo - (float) $this->valor_atual, 0), 2);
    }

    /** Fração concluída em pontos percentuais; alvo zero ou vazio resulta em 0. */
    public function getConcluidoPctAttribute(): float
    {
        return (float) $this->valor_alvo > 0
            ? round((float) $this->valor_atual / (float) $this->valor_alvo * 100, 2)
            : 0.0;
    }
}
