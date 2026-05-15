<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDespesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('despesas', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'valor'           => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'data_compra'     => ['required', 'date'],
            'data_pagamento'  => ['nullable', 'date'],
            'categoria_id'    => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'quem_comprou'    => ['nullable', 'integer', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'onde_comprou'    => ['nullable', 'integer', Rule::exists('fornecedores', 'id')->where('tenant_id', $tenantId)],
            'forma_pagamento' => [
                'nullable',
                'integer',
                'required_if:tipo_pagamento,credito',
                Rule::exists('bancos', 'id')->where('tenant_id', $tenantId),
            ],
            'tipo_pagamento'  => ['nullable', Rule::in(StoreDespesaRequest::TIPOS_PAGAMENTO)],
            'observacoes'     => ['nullable', 'string', 'max:2000'],
            'escopo'          => ['nullable', Rule::in(['apenas_esta', 'esta_e_futuras'])],
        ];
    }
}
