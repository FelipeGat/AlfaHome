<?php

namespace App\Services\Lancamentos;

use App\Models\Banco;
use App\Models\Despesa;
use App\Models\Receita;
use App\Models\Transferencia;
use App\Models\User;
use App\Services\Financeiro\RecalcularSaldos;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Lançamentos feitos no sistema (sem planilha): criar, editar, marcar pago,
 * excluir e pagar fatura. Site e API do app chamam daqui — uma regra só.
 *
 * Compra no cartão é a saída: cai na fatura do vencimento e fica pendente até
 * a fatura ser paga. Pagar a fatura marca as compras como pagas na data e na
 * conta escolhidas; o pagamento não é uma saída a mais.
 */
class LancamentoService
{
    /**
     * Saída (despesa). Retorna quantos lançamentos foram criados e, se a compra
     * no cartão não tem fechamento/vencimento cadastrados, um aviso.
     *
     * @return array{total: int, aviso: ?string}
     */
    public function criarSaida(array $data, User $user): array
    {
        $aviso = null;
        if (($data['tipo_pagamento'] ?? null) === 'credito' && ! empty($data['forma_pagamento'])) {
            $cartao = Banco::withoutGlobalScopes()->where('tenant_id', $user->tenant_id)->find($data['forma_pagamento']);
            if ($cartao && $cartao->dia_fechamento_cartao && $cartao->dia_vencimento_cartao) {
                $data['dia_fechamento_cartao'] = $cartao->dia_fechamento_cartao;
                $data['dia_vencimento_cartao'] = $cartao->dia_vencimento_cartao;
            } else {
                $aviso = 'Cadastre o fechamento e o vencimento do cartão para a compra cair na fatura certa.';
            }
            // Compra no cartão só é paga junto com a fatura.
            $data['data_pagamento'] = null;
        }

        $total = RecalcularSaldos::emLote(fn () => Despesa::criarComRecorrencia($data, $user->id));

        return ['total' => $total, 'aviso' => $aviso];
    }

    /** Entrada (receita). Retorna quantos lançamentos foram criados. */
    public function criarEntrada(array $data, User $user): int
    {
        return RecalcularSaldos::emLote(fn () => Receita::criarComRecorrencia($data, $user->id));
    }

    public function criarTransferencia(array $data, User $user): Transferencia
    {
        return Transferencia::create($data + ['user_id' => $user->id]);
    }

    /** `escopo`: apenas_esta (padrão) | esta_e_futuras (mantém as datas das próximas). */
    public function atualizarSaida(Despesa $despesa, array $data): Despesa
    {
        $payload = collect($data)->only([
            'quem_comprou', 'onde_comprou', 'categoria_id', 'forma_pagamento', 'tipo_pagamento',
            'valor', 'data_compra', 'data_pagamento', 'observacoes',
        ])->all();
        if (array_key_exists('data_pagamento', $payload) && $payload['data_pagamento'] === '') {
            $payload['data_pagamento'] = null;
        }
        if (array_key_exists('data_pagamento', $payload) && $payload['data_pagamento'] === null) {
            $payload['pago_com_banco_id'] = null;
        }

        return $this->naSerie($despesa, $data['escopo'] ?? 'apenas_esta', 'data_compra', $payload);
    }

    public function atualizarEntrada(Receita $receita, array $data): Receita
    {
        $payload = collect($data)->only([
            'quem_recebeu', 'categoria_id', 'forma_recebimento', 'tipo_pagamento', 'valor',
            'data_prevista_recebimento', 'data_recebimento', 'observacoes',
        ])->all();
        if (array_key_exists('data_recebimento', $payload) && $payload['data_recebimento'] === '') {
            $payload['data_recebimento'] = null;
        }

        return $this->naSerie($receita, $data['escopo'] ?? 'apenas_esta', 'data_prevista_recebimento', $payload);
    }

    /** Marca como pago (saída) ou recebido (entrada) na data; `null` desfaz. */
    public function marcarPago(Despesa|Receita $lancamento, ?CarbonInterface $data): void
    {
        if ($lancamento instanceof Despesa) {
            $lancamento->update(['data_pagamento' => $data?->toDateString(), 'pago_com_banco_id' => $data ? $lancamento->pago_com_banco_id : null]);
        } else {
            $lancamento->update(['data_recebimento' => $data?->toDateString()]);
        }
    }

    /** Exclui o lançamento, ou ele e as próximas da série. Retorna quantos saíram. */
    public function excluir(Despesa|Receita $lancamento, string $escopo = 'apenas_esta'): int
    {
        $campoData = $lancamento instanceof Despesa ? 'data_compra' : 'data_prevista_recebimento';

        return RecalcularSaldos::emLote(function () use ($lancamento, $escopo, $campoData) {
            if ($escopo !== 'esta_e_futuras' || ! $lancamento->grupo_recorrencia_id) {
                $lancamento->delete();

                return 1;
            }

            return $this->serie($lancamento, $campoData)->get()->each->delete()->count();
        });
    }

    /**
     * Paga a fatura do cartão que vence no mês de `$vencimento`: as compras
     * pendentes dela ficam pagas na data, saindo da conta escolhida. Retorna
     * quantas compras foram pagas.
     */
    public function pagarFatura(Banco $cartao, CarbonInterface $vencimento, Banco $conta, CarbonInterface $data): int
    {
        return RecalcularSaldos::emLote(fn () => DB::transaction(function () use ($cartao, $vencimento, $conta, $data) {
            $compras = Despesa::withoutGlobalScope('tenant')->where('tenant_id', $cartao->tenant_id)
                ->where('forma_pagamento', $cartao->id)->where('tipo_pagamento', 'credito')->whereNull('data_pagamento')
                ->whereBetween('data_compra', [$vencimento->copy()->startOfMonth()->toDateString(), $vencimento->copy()->endOfMonth()->toDateString()])
                ->get();
            foreach ($compras as $c) {
                $c->update(['data_pagamento' => $data->toDateString(), 'pago_com_banco_id' => $conta->id]);
            }

            return $compras->count();
        }));
    }

    // ─── Apoio ───────────────────────────────────────────────────────────────

    private function naSerie(Despesa|Receita $lancamento, string $escopo, string $campoData, array $payload): Despesa|Receita
    {
        RecalcularSaldos::emLote(function () use ($lancamento, $escopo, $campoData, $payload) {
            if ($escopo === 'esta_e_futuras' && $lancamento->grupo_recorrencia_id) {
                // Um por um, para os observers de saldo e fatura rodarem em cada item.
                $semData = collect($payload)->except($campoData)->all();
                $this->serie($lancamento, $campoData)->chunkById(100, fn ($itens) => $itens->each->update($semData));
                $lancamento->refresh();
            } else {
                $lancamento->update($payload);
            }
        });

        return $lancamento;
    }

    private function serie(Despesa|Receita $lancamento, string $campoData)
    {
        return $lancamento::query()->withoutGlobalScope('tenant')
            ->where('tenant_id', $lancamento->tenant_id)
            ->where('grupo_recorrencia_id', $lancamento->grupo_recorrencia_id)
            ->where($campoData, '>=', $lancamento->{$campoData});
    }
}
