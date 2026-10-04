<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Receita extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    /**
     * Formas de recebimento aceitas — a mesma lista na web e na API.
     * 'debito' e 'credito' ficam por compatibilidade com o app já publicado.
     */
    public const TIPOS_PAGAMENTO = ['dinheiro', 'pix', 'transferencia', 'deposito', 'boleto', 'debito', 'credito', 'outros'];

    protected $fillable = [
        'tenant_id', 'user_id', 'quem_recebeu', 'categoria_id',
        'forma_recebimento', 'tipo_pagamento',
        'valor', 'valor_previsto', 'data_prevista_recebimento', 'data_recebimento', 'observacoes',
        'recorrente', 'parcelas', 'frequencia', 'grupo_recorrencia_id',
    ];

    protected $casts = [
        'valor_previsto' => 'decimal:2',
        'valor'                      => 'decimal:2',
        'data_prevista_recebimento'  => 'date',
        'data_recebimento'           => 'date',
        'recorrente'                 => 'boolean',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function familiar()
    {
        return $this->belongsTo(Familiar::class, 'quem_recebeu');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'forma_recebimento');
    }

    // ─── Atributos computados ─────────────────────────────────────────────────

    /**
     * Status financeiro da receita:
     *   'recebido'  → data_recebimento preenchida
     *   'vencido'   → sem recebimento e data prevista já passou
     *   'a_receber' → sem recebimento, data prevista hoje ou futura
     */
    public function getStatusAttribute(): string
    {
        if ($this->data_recebimento) {
            return 'recebido';
        }

        if ($this->data_prevista_recebimento->isPast() && ! $this->data_prevista_recebimento->isToday()) {
            return 'vencido';
        }

        return 'a_receber';
    }

    // ─── Lógica de recorrência ────────────────────────────────────────────────

    /**
     * Cria uma ou múltiplas receitas dentro de uma transação atômica.
     * Se qualquer parcela falhar, nenhuma é persistida.
     *
     * parcelas = 1 → receita única
     * parcelas > 1 → cria N lançamentos mensais com o mesmo valor
     * parcelas = 0 → recorrência infinita (60 meses)
     */
    public static function criarComRecorrencia(array $data, int $userId): int
    {
        $parcelas      = isset($data['parcelas']) ? (int) $data['parcelas'] : 1;
        $frequencia    = $data['frequencia'] ?? 'mensal';
        $tipoPagamento = $data['tipo_pagamento'] ?? null;

        // Ativa criação múltipla sempre que parcelas != 1 OU checkbox recorrente marcado
        $isRecorrente = ! empty($data['recorrente']) || $parcelas === 0 || $parcelas > 1;

        $total = 1;
        if ($isRecorrente) {
            $total = ($parcelas === 0) ? 60 : max(1, $parcelas);
        }

        $grupoId     = ($total > 1) ? Str::uuid()->toString() : null;
        $dataInicial = Carbon::parse($data['data_prevista_recebimento']);

        DB::transaction(function () use ($data, $userId, $total, $grupoId, $dataInicial, $frequencia, $isRecorrente, $tipoPagamento) {
            for ($i = 0; $i < $total; $i++) {
                $dataAtual = self::calcularData($dataInicial, $frequencia, $i);

                self::create([
                    'user_id'                   => $userId,
                    'quem_recebeu'              => $data['quem_recebeu'] ?? null,
                    'categoria_id'              => $data['categoria_id'] ?? null,
                    'forma_recebimento'         => $data['forma_recebimento'] ?? null,
                    'tipo_pagamento'            => $tipoPagamento,
                    'valor'                     => $data['valor'],
                    'data_prevista_recebimento' => $dataAtual->format('Y-m-d'),
                    'data_recebimento'          => ! empty($data['data_recebimento']) ? $data['data_recebimento'] : null,
                    'recorrente'                => $isRecorrente,
                    'parcelas'                  => $total,
                    'frequencia'                => $frequencia,
                    'observacoes'               => $data['observacoes'] ?? null,
                    'grupo_recorrencia_id'      => $grupoId,
                ]);
            }
        });

        return $total;
    }

    /**
     * Calcula a data de uma parcela usando Carbon nativo (evita ambiguidade do modify()).
     */
    private static function calcularData(Carbon $base, string $frequencia, int $offset): Carbon
    {
        $data = $base->copy();

        return match ($frequencia) {
            'diaria'     => $data->addDays($offset),
            'semanal'    => $data->addWeeks($offset),
            'quinzenal'  => $data->addWeeks($offset * 2),
            'mensal'     => $data->addMonths($offset),
            'trimestral' => $data->addMonths($offset * 3),
            'semestral'  => $data->addMonths($offset * 6),
            'anual'      => $data->addYears($offset),
            default      => $data->addMonths($offset),
        };
    }
}
