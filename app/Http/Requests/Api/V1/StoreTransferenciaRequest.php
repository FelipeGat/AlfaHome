<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('transferencias', 'criar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'valor'      => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'data'       => ['required', 'date'],
            'origem_id'  => ['required', 'integer', 'different:destino_id', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'destino_id' => ['required', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'origem_id.different' => 'A conta de origem deve ser diferente da conta de destino.',
            'valor.min'           => 'O valor da transferência deve ser maior que zero.',
        ];
    }
}
