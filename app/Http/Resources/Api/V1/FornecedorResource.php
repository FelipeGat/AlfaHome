<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FornecedorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nome'        => $this->nome,
            'icone'       => $this->icone,
            'grupo'       => $this->grupo,
            'contato'     => $this->contato,
            'cnpj'        => $this->cnpj,
            'telefone'    => $this->telefone,
            'observacoes' => $this->observacoes,
        ];
    }
}
