<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banco extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'titular_id', 'nome', 'logo', 'cor',
        'codigo_banco', 'agencia', 'conta',
        'tem_conta_corrente', 'tem_poupanca', 'tem_cartao_credito', 'eh_dinheiro',
        'saldo', 'saldo_poupanca',
        'cheque_especial', 'saldo_cheque',
        'limite_cartao', 'saldo_cartao',
        'dia_vencimento_cartao', 'dia_fechamento_cartao',
    ];

    protected $casts = [
        'saldo'              => 'decimal:2',
        'saldo_poupanca'     => 'decimal:2',
        'cheque_especial'    => 'decimal:2',
        'saldo_cheque'       => 'decimal:2',
        'limite_cartao'      => 'decimal:2',
        'saldo_cartao'       => 'decimal:2',
        'tem_conta_corrente' => 'boolean',
        'tem_poupanca'       => 'boolean',
        'tem_cartao_credito' => 'boolean',
        'eh_dinheiro'        => 'boolean',
        'dia_vencimento_cartao' => 'integer',
        'dia_fechamento_cartao' => 'integer',
    ];

    /**
     * O saldo digitado no cadastro (conta nova ou saldo editado) vira um ajuste:
     * é a base do saldo calculado pelo FinanceiroService. O recálculo grava a
     * coluna direto no banco, sem passar por aqui.
     */
    protected static function booted(): void
    {
        static::saved(function (Banco $banco) {
            if ($banco->wasRecentlyCreated || $banco->wasChanged('saldo')) {
                SaldoAjuste::withoutGlobalScopes()->create([
                    'tenant_id'  => $banco->tenant_id,
                    'banco_id'   => $banco->id,
                    'user_id'    => auth()->id(),
                    'data'       => now()->toDateString(),
                    'saldo'      => $banco->saldo ?? 0,
                    'diferenca'  => $banco->wasRecentlyCreated ? 0 : round((float) $banco->saldo - (float) $banco->getOriginal('saldo'), 2),
                    'observacao' => $banco->wasRecentlyCreated ? 'Saldo inicial (cadastro da conta)' : 'Saldo editado no cadastro',
                ]);
            }
        });
    }

    public function ajustes()
    {
        return $this->hasMany(SaldoAjuste::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function titular()
    {
        return $this->belongsTo(Familiar::class, 'titular_id');
    }

    public function investimentos()
    {
        return $this->hasMany(Investimento::class);
    }

    public function getTiposAtivosAttribute(): string
    {
        $tipos = [];
        if ($this->eh_dinheiro) $tipos[] = 'Dinheiro';
        if ($this->tem_conta_corrente) $tipos[] = 'Conta Corrente';
        if ($this->tem_poupanca) $tipos[] = 'Poupança';
        if ($this->tem_cartao_credito) $tipos[] = 'Cartão de Crédito';
        return implode(', ', $tipos) ?: 'Nenhum';
    }

    public function getSaldoDisponivelAttribute(): float
    {
        return (float) $this->saldo + (float) $this->cheque_especial - (float) $this->saldo_cheque;
    }

    public function getLimiteCartaoDisponivelAttribute(): float
    {
        return (float) $this->limite_cartao - (float) $this->saldo_cartao;
    }

    public function getSaldoTotalAttribute(): float
    {
        return (float) $this->saldo + (float) $this->saldo_poupanca;
    }

    /**
     * Melhor dia de compra = dia seguinte ao fechamento.
     * Compras feitas nesse dia caem na próxima fatura, dando o prazo máximo.
     */
    public function getMelhorDiaCompraAttribute(): ?int
    {
        if (!$this->dia_fechamento_cartao) return null;
        return $this->dia_fechamento_cartao >= 28 ? 1 : $this->dia_fechamento_cartao + 1;
    }
}
