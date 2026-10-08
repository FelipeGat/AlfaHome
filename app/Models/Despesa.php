<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Despesa extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'quem_comprou', 'onde_comprou', 'categoria_id',
        'forma_pagamento', 'pago_com_banco_id', 'tipo_pagamento',
        'valor', 'valor_previsto', 'data_compra', 'data_pagamento', 'observacoes',
        'recorrente', 'recorrencia_sem_fim', 'parcelas', 'frequencia', 'grupo_recorrencia_id',
        'origem', 'numero_documento',
    ];

    protected $casts = [
        'valor_previsto' => 'decimal:2',
        'valor'          => 'decimal:2',
        'data_compra'    => 'date',
        'data_pagamento' => 'date',
        'recorrente'     => 'boolean',
        'recorrencia_sem_fim' => 'boolean',
    ];

    // ─── Relacionamentos ──────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function familiar()
    {
        return $this->belongsTo(Familiar::class, 'quem_comprou');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'onde_comprou');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'forma_pagamento');
    }

    /** Conta de onde saiu o dinheiro ao pagar a fatura (compra no cartão). */
    public function pagoCom()
    {
        return $this->belongsTo(Banco::class, 'pago_com_banco_id');
    }

    // ─── Escopos ──────────────────────────────────────────────────────────────

    /**
     * Fatura aberta de cartão: compras no crédito ainda não pagas. É a mesma
     * regra do DespesaObserver (que mantém bancos.saldo_cartao) — painel, tela
     * de bancos e validação de limite usam este escopo para não divergir.
     */
    public function scopeFaturaAberta($query)
    {
        return $query->where('tipo_pagamento', 'credito')->whereNull('data_pagamento');
    }

    // ─── Atributos computados ─────────────────────────────────────────────────

    /**
     * Status financeiro da despesa:
     *   'pago'    → data_pagamento preenchida
     *   'vencido' → sem pagamento e data_compra já passou
     *   'a_pagar' → sem pagamento, data_compra hoje ou futura
     */
    public function getStatusAttribute(): string
    {
        if ($this->data_pagamento) {
            return 'pago';
        }

        if ($this->data_compra->isPast() && ! $this->data_compra->isToday()) {
            return 'vencido';
        }

        return 'a_pagar';
    }

    // ─── Lógica de criação com recorrência / parcelamento ────────────────────

    /**
     * Cria uma ou múltiplas despesas dentro de uma transação atômica.
     *
     * Regras gerais:
     *   parcelas = 1  → despesa única
     *   parcelas > 1  → cria N lançamentos mensais (ou na frequência escolhida)
     *   parcelas = 0  → recorrência infinita (cria 60 lançamentos)
     *
     * Regras extras para Cartão de Crédito (tipo_pagamento = 'credito'):
     *   - O valor é DIVIDIDO entre as parcelas (ex: R$1.500 / 3 = R$500/parcela)
     *   - A data de cada parcela = data de VENCIMENTO da fatura, não a data da compra
     *   - Se a compra ocorreu ANTES ou NO dia de fechamento → 1ª parcela vence no mês seguinte
     *   - Se a compra ocorreu APÓS o dia de fechamento → compra cai na fatura do mês seguinte,
     *     portanto 1ª parcela vence apenas em 2 meses
     *   - Para funcionar, o banco precisa ter dia_fechamento_cartao e dia_vencimento_cartao
     *     configurados. O controller injeta esses valores em $data['dia_fechamento_cartao']
     *     e $data['dia_vencimento_cartao'].
     *
     * @param array $data   Dados do formulário + extras injetados pelo controller
     * @param int   $userId ID do usuário autenticado
     * @return int          Número de registros criados
     */
    public static function criarComRecorrencia(array $data, int $userId): int
    {
        $parcelas      = isset($data['parcelas']) ? (int) $data['parcelas'] : 1;
        $tipoPagamento = $data['tipo_pagamento'] ?? null;

        // Criação múltipla é ativada quando: parcelas > 1, parcelas = 0 (infinito), ou checkbox recorrente
        $isRecorrente = ! empty($data['recorrente']) || $parcelas === 0 || $parcelas > 1;

        $total = 1;
        if ($isRecorrente) {
            $total = ($parcelas === 0) ? 60 : max(1, $parcelas);
        }

        // ── Valor por parcela ─────────────────────────────────────────────────
        // Cartão de crédito divide o valor total entre as parcelas APENAS quando
        // parcelas > 1 (parcelamento explícito). Quando parcelas = 0 (recorrente
        // mensal sem fim, ex: anuidade cobrada todo mês), o valor cheio repete.
        $valorTotal   = (float) $data['valor'];
        $valorParcela = ($tipoPagamento === 'credito' && $parcelas > 1)
            ? round($valorTotal / $total, 2)
            : $valorTotal;

        $grupoId    = ($total > 1) ? Str::uuid()->toString() : null;
        $dataCompra = Carbon::parse($data['data_compra']);

        // ── Data inicial: normal ou data de vencimento do cartão ─────────────
        // Para cartão: não usamos a data da compra como data do lançamento.
        // Usamos a data de VENCIMENTO de cada fatura, calculada a partir do
        // dia de fechamento do cartão.
        $frequencia = $data['frequencia'] ?? 'mensal';
        $dataInicial = $dataCompra->copy();

        $isCartaoComFatura = (
            $tipoPagamento === 'credito'
            && ! empty($data['dia_fechamento_cartao'])
            && ! empty($data['dia_vencimento_cartao'])
        );

        if ($isCartaoComFatura) {
            $dataInicial = self::calcularPrimeiroVencimentoCartao(
                $dataCompra,
                (int) $data['dia_fechamento_cartao'],
                (int) $data['dia_vencimento_cartao']
            );
            $frequencia = 'mensal'; // fatura sempre é mensal
        }

        $obsBase = $data['observacoes'] ?? null;

        DB::transaction(function () use (
            $data, $userId, $total, $parcelas, $grupoId,
            $dataInicial, $dataCompra, $frequencia,
            $isRecorrente, $valorParcela, $tipoPagamento,
            $obsBase, $isCartaoComFatura
        ) {
            for ($i = 0; $i < $total; $i++) {
                $dataAtual = self::calcularData($dataInicial, $frequencia, $i);

                // Observações: numera parcelas do cartão (apenas parcelamento explícito,
                // não recorrência infinita onde parcelas = 0)
                $obs = $obsBase;
                if ($tipoPagamento === 'credito' && $parcelas > 1) {
                    $prefixo = 'Parcela ' . ($i + 1) . '/' . $total;
                    $obs     = $obsBase ? "{$prefixo} — {$obsBase}" : $prefixo;
                }

                self::create([
                    'user_id'              => $userId,
                    'quem_comprou'         => $data['quem_comprou'] ?? null,
                    'onde_comprou'         => $data['onde_comprou'] ?? null,
                    'categoria_id'         => $data['categoria_id'] ?? null,
                    'forma_pagamento'      => $data['forma_pagamento'] ?? null,
                    'tipo_pagamento'       => $tipoPagamento,
                    'valor'                => $valorParcela,
                    // Para cartão: data_compra = data de vencimento da fatura
                    // Para outros: data_compra = data real da transação
                    'data_compra'          => $dataAtual->format('Y-m-d'),
                    // Parcelas de cartão não têm data de pagamento no momento da criação
                    'data_pagamento'       => ($isCartaoComFatura)
                        ? null
                        : (! empty($data['data_pagamento']) ? $data['data_pagamento'] : null),
                    'recorrente'           => $isRecorrente,
                    // "Todo mês" (parcelas = 0): no cartão só a do mês consome limite.
                    'recorrencia_sem_fim'  => $parcelas === 0,
                    'parcelas'             => $total,
                    'frequencia'           => $frequencia,
                    'observacoes'          => $obs,
                    'grupo_recorrencia_id' => $grupoId,
                    'origem'               => $data['origem'] ?? 'manual',
                    'numero_documento'     => $data['numero_documento'] ?? null,
                ]);
            }
        });

        return $total;
    }

    /**
     * Calcula a data de vencimento da primeira parcela do cartão.
     *
     * Regra brasileira padrão:
     *   Determina em quantos meses a fatura vence a partir do mês da compra.
     *
     *   Caso A — vencimento > fechamento (ex: fecha dia 02, paga dia 12):
     *     O pagamento ocorre no MESMO mês do fechamento.
     *     compra_dia <= fechamento → fatura fecha este mês → paga este mês  (+0 meses)
     *     compra_dia >  fechamento → fatura fecha no mês seguinte → paga mês seguinte (+1 mês)
     *
     *   Caso B — vencimento <= fechamento (ex: fecha dia 20, paga dia 05):
     *     O pagamento ocorre no mês SEGUINTE ao fechamento.
     *     compra_dia <= fechamento → fatura fecha este mês → paga mês seguinte (+1 mês)
     *     compra_dia >  fechamento → fatura fecha no mês seguinte → paga em 2 meses (+2 meses)
     *
     * Exemplos:
     *   Fechamento 02, Vencimento 12 — compra 11/03 → 12/04  (11 > 2 → +1 mês)
     *   Fechamento 02, Vencimento 12 — compra 01/03 → 12/03  (1 ≤ 2  → +0 meses)
     *   Fechamento 20, Vencimento 05 — compra 15/03 → 05/04  (15 ≤ 20 → +1 mês)
     *   Fechamento 20, Vencimento 05 — compra 25/03 → 05/05  (25 > 20 → +2 meses)
     */
    public static function calcularPrimeiroVencimentoCartao(
        Carbon $dataCompra,
        int $diaFechamento,
        int $diaVencimento
    ): Carbon {
        // Se vencimento > fechamento: pagamento ocorre no mesmo mês do fechamento (offset base = 0)
        // Se vencimento <= fechamento: pagamento ocorre no mês seguinte ao fechamento (offset base = 1)
        $mesesBase = ($diaVencimento > $diaFechamento) ? 0 : 1;

        if ($dataCompra->day <= $diaFechamento) {
            // Compra dentro do ciclo atual → vence com offset base
            return $dataCompra->copy()->addMonthsNoOverflow($mesesBase)->setDay($diaVencimento);
        }

        // Compra após o fechamento → cai no próximo ciclo → vence com offset base + 1
        return $dataCompra->copy()->addMonthsNoOverflow($mesesBase + 1)->setDay($diaVencimento);
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
