<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBancoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('bancos', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'nome'                  => ['sometimes', 'required', 'string', 'max:120'],
            'cor'                   => ['nullable', 'string', 'max:20'],
            'logo'                  => ['nullable', 'string', 'max:255'],
            'titular_id'            => ['nullable', 'integer', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'codigo_banco'          => ['nullable', 'string', 'max:10'],
            'agencia'               => ['nullable', 'string', 'max:20'],
            'conta'                 => ['nullable', 'string', 'max:30'],
            'tem_conta_corrente'    => ['sometimes', 'boolean'],
            'tem_poupanca'          => ['sometimes', 'boolean'],
            'tem_cartao_credito'    => ['sometimes', 'boolean'],
            'eh_dinheiro'           => ['sometimes', 'boolean'],
            'saldo'                 => ['sometimes', 'numeric'],
            'saldo_poupanca'        => ['sometimes', 'numeric'],
            'cheque_especial'       => ['sometimes', 'numeric', 'min:0'],
            'saldo_cheque'          => ['sometimes', 'numeric', 'min:0'],
            'limite_cartao'         => ['sometimes', 'numeric', 'min:0'],
            'saldo_cartao'          => ['sometimes', 'numeric', 'min:0'],
            'dia_fechamento_cartao' => ['nullable', 'integer', 'min:1', 'max:31'],
            'dia_vencimento_cartao' => ['nullable', 'integer', 'min:1', 'max:31'],
        ];
    }
}
