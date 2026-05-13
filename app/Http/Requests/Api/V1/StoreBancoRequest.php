<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBancoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('bancos', 'criar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'nome'                  => ['required', 'string', 'max:120'],
            'cor'                   => ['nullable', 'string', 'max:20'],
            'logo'                  => ['nullable', 'string', 'max:255'],
            'titular_id'            => ['nullable', 'integer', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'codigo_banco'          => ['nullable', 'string', 'max:10'],
            'agencia'               => ['nullable', 'string', 'max:20'],
            'conta'                 => ['nullable', 'string', 'max:30'],
            'tem_conta_corrente'    => ['nullable', 'boolean'],
            'tem_poupanca'          => ['nullable', 'boolean'],
            'tem_cartao_credito'    => ['nullable', 'boolean'],
            'eh_dinheiro'           => ['nullable', 'boolean'],
            'saldo'                 => ['nullable', 'numeric'],
            'saldo_poupanca'        => ['nullable', 'numeric'],
            'cheque_especial'       => ['nullable', 'numeric', 'min:0'],
            'saldo_cheque'          => ['nullable', 'numeric', 'min:0'],
            'limite_cartao'         => ['nullable', 'numeric', 'min:0'],
            'saldo_cartao'          => ['nullable', 'numeric', 'min:0'],
            'dia_fechamento_cartao' => ['nullable', 'integer', 'min:1', 'max:31'],
            'dia_vencimento_cartao' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
