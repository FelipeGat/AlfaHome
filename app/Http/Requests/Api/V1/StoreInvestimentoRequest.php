<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('investimentos', 'criar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'nome_ativo'        => ['required', 'string', 'max:150'],
            'tipo_investimento' => ['required', 'string', 'max:100'],
            'data_aporte'       => ['required', 'date'],
            'valor_aportado'    => ['required', 'numeric', 'min:0.01'],
            'quantidade_cotas'  => ['nullable', 'numeric', 'min:0'],
            'percentual_mensal' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_anual'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'banco_id'          => ['nullable', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'observacoes'       => ['nullable', 'string', 'max:2000'],
        ];
    }
}
