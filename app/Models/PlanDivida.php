<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanDivida extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_dividas';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'nome', 'credor', 'saldo_inicial', 'saldo_atual', 'taxa_mensal', 'parcela_mensal', 'dia_vencimento', 'status', 'prioridade', 'previsao_quitacao', 'observacao'];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_atual' => 'decimal:2',
        'taxa_mensal' => 'decimal:6',
        'parcela_mensal' => 'decimal:2',
        'previsao_quitacao' => 'date',
    ];

    public function getAmortizadoAttribute(): ?float
    {
        if ($this->saldo_inicial === null || $this->saldo_atual === null) {
            return null;
        }

        return round((float) $this->saldo_inicial - (float) $this->saldo_atual, 2);
    }

    public function getAmortizadoPctAttribute(): ?float
    {
        if ($this->amortizado === null) {
            return null;
        }

        return (float) $this->saldo_inicial > 0
            ? round($this->amortizado / (float) $this->saldo_inicial * 100, 2)
            : 0.0;
    }
}
