<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestimentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $valorAportado = (float) $this->valor_aportado;
        $ultimoRendimento = $this->relationLoaded('rendimentos')
            ? $this->rendimentos->last()
            : null;

        $valorAtual = $ultimoRendimento
            ? (float) $ultimoRendimento->valor_atual
            : $valorAportado;

        $ganhoReais = $valorAtual - $valorAportado;
        $ganhoPercentual = $valorAportado > 0 ? ($ganhoReais / $valorAportado) * 100 : 0;

        return [
            'id'                => $this->id,
            'nome_ativo'        => $this->nome_ativo,
            'tipo_investimento' => $this->tipo_investimento,
            'data_aporte'       => $this->data_aporte?->format('Y-m-d'),
            'valor_aportado'    => $valorAportado,
            'quantidade_cotas'  => (float) $this->quantidade_cotas,
            'percentual_mensal' => $this->percentual_mensal !== null ? (float) $this->percentual_mensal : null,
            'percentual_anual'  => $this->percentual_anual !== null ? (float) $this->percentual_anual : null,
            'banco_id'          => $this->banco_id,
            'observacoes'       => $this->observacoes,

            'banco' => $this->whenLoaded('banco', fn() => $this->banco ? [
                'id'   => $this->banco->id,
                'nome' => $this->banco->nome,
                'cor'  => $this->banco->cor,
            ] : null),

            'rendimentos' => InvestimentoRendimentoResource::collection(
                $this->whenLoaded('rendimentos')
            ),

            'valor_atual'      => $valorAtual,
            'ganho_reais'      => $ganhoReais,
            'ganho_percentual' => round($ganhoPercentual, 2),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
