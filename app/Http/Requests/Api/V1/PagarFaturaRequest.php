<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PagarFaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('despesas', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'vencimento' => ['required', 'date'],
            'conta_id'   => ['required', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $this->user()->tenant_id)],
            'data'       => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'vencimento.required' => 'Informe a fatura.',
            'conta_id.required'   => 'Escolha a conta que pagou.',
            'conta_id.exists'     => 'Conta não encontrada.',
        ];
    }
}
