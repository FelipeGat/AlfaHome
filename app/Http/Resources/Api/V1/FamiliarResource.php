<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FamiliarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'nome'          => $this->nome,
            'foto'          => $this->foto,
            'foto_url'      => $this->foto ? Storage::disk('public')->url($this->foto) : null,
            'salario'       => $this->salario !== null ? (float) $this->salario : null,
            'limite_cartao' => $this->limite_cartao !== null ? (float) $this->limite_cartao : null,
            'limite_cheque' => $this->limite_cheque !== null ? (float) $this->limite_cheque : null,
            // Cobertura: app usa is_master para badge "titular" e bloqueio de exclusão.
            // True quando existe um User com este familiar_id e role=master.
            'is_master'     => $this->isMaster(),
        ];
    }
}
