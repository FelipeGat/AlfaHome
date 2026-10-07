<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AceitaValorComVirgula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReceitaRequest extends FormRequest
{
    use AceitaValorComVirgula;

    public function authorize(): bool
    {
        return $this->user()?->temPermissao('receitas', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'valor'                     => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'data_prevista_recebimento' => ['required', 'date'],
            'data_recebimento'          => ['nullable', 'date'],
            'categoria_id'              => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('tenant_id', $tenantId)],
            'quem_recebeu'              => ['nullable', 'integer', Rule::exists('familiares', 'id')->where('tenant_id', $tenantId)],
            'forma_recebimento'         => ['nullable', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'tipo_pagamento'            => ['nullable', Rule::in(StoreReceitaRequest::TIPOS_PAGAMENTO)],
            'observacoes'               => ['nullable', 'string', 'max:2000'],
            'escopo'                    => ['nullable', Rule::in(['apenas_esta', 'esta_e_futuras'])],
        ];
    }
}
