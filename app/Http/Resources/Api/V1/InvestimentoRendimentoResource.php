<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvestimentoRendimentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'investimento_id' => $this->investimento_id,
            'data'            => $this->data?->format('Y-m-d'),
            'valor_atual'     => (float) $this->valor_atual,
            'observacoes'     => $this->observacoes,
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
