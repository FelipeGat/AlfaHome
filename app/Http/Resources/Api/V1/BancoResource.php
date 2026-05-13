<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BancoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'nome'                      => $this->nome,
            'cor'                       => $this->cor,
            'logo'                      => $this->logo,
            'titular_id'                => $this->titular_id,
            'codigo_banco'              => $this->codigo_banco,
            'agencia'                   => $this->agencia,
            'conta'                     => $this->conta,
            'tem_conta_corrente'        => (bool) $this->tem_conta_corrente,
            'tem_poupanca'              => (bool) $this->tem_poupanca,
            'tem_cartao_credito'        => (bool) $this->tem_cartao_credito,
            'eh_dinheiro'               => (bool) $this->eh_dinheiro,
            'saldo'                     => (float) $this->saldo,
            'saldo_poupanca'            => (float) $this->saldo_poupanca,
            'cheque_especial'           => (float) $this->cheque_especial,
            'saldo_cheque'              => (float) $this->saldo_cheque,
            'limite_cartao'             => (float) $this->limite_cartao,
            'saldo_cartao'              => (float) $this->saldo_cartao,
            'dia_fechamento_cartao'     => $this->dia_fechamento_cartao,
            'dia_vencimento_cartao'     => $this->dia_vencimento_cartao,
        ];
    }
}
