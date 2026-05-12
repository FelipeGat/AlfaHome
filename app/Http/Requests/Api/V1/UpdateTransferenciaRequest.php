<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('transferencias', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'valor'      => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'data'       => ['sometimes', 'required', 'date'],
            'origem_id'  => ['sometimes', 'required', 'integer', 'different:destino_id', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'destino_id' => ['sometimes', 'required', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'origem_id.different' => 'A conta de origem deve ser diferente da conta de destino.',
        ];
    }
}
