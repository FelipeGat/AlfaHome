<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Conta que paga a fatura de um cartão da planilha. */
class ContaPagamentoCartaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('bancos', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'cartao'   => ['required', 'string', 'max:120'],
            'banco_id' => ['nullable', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $this->user()->tenant_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'cartao.required' => 'Informe o cartão.',
            'banco_id.exists' => 'Conta não encontrada.',
        ];
    }
}
