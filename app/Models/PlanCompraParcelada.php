<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Espelho somente leitura de uma aba da planilha de planejamento. */
class PlanCompraParcelada extends Model
{
    use BelongsToTenant;

    protected $table = 'plan_compras_parceladas';

    protected $fillable = ['tenant_id', 'importacao_id', 'chave', 'conteudo_hash', 'linha', 'compra', 'cartao', 'plan_cartao_id', 'data', 'valor_total', 'parcelas', 'parcela_atual', 'parcelas_pagas', 'proximo_vencimento', 'observacao'];

    protected $casts = [
        'data' => 'date',
        'valor_total' => 'decimal:2',
        'proximo_vencimento' => 'date',
    ];

    public function cartaoModel()
    {
        return $this->belongsTo(PlanCartao::class, 'plan_cartao_id');
    }

    public function getValorParcelaAttribute(): ?float
    {
        if ($this->valor_total === null || ! $this->parcelas) {
            return null;
        }

        return round((float) $this->valor_total / $this->parcelas, 2);
    }

    public function getParcelasRestantesAttribute(): ?int
    {
        if ($this->parcelas === null) {
            return null;
        }

        return max($this->parcelas - (int) $this->parcelas_pagas, 0);
    }

    public function getSaldoAttribute(): ?float
    {
        if ($this->valor_total === null || ! $this->parcelas) {
            return null;
        }

        // Como na planilha: valor da parcela (sem arredondar) x parcelas restantes.
        return round((float) $this->valor_total / $this->parcelas * $this->parcelas_restantes, 2);
    }
}
