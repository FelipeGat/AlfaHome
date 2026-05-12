<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'valor'      => (float) $this->valor,
            'data'       => $this->data?->format('Y-m-d'),
            'origem_id'  => $this->origem_id,
            'destino_id' => $this->destino_id,
            'observacao' => $this->observacao,

            'origem'  => $this->whenLoaded('origem',  fn() => $this->origem ? [
                'id'   => $this->origem->id,
                'nome' => $this->origem->nome,
                'cor'  => $this->origem->cor,
            ] : null),
            'destino' => $this->whenLoaded('destino', fn() => $this->destino ? [
                'id'   => $this->destino->id,
                'nome' => $this->destino->nome,
                'cor'  => $this->destino->cor,
            ] : null),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
