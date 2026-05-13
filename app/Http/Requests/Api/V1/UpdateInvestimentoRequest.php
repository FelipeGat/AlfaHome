<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('investimentos', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'nome_ativo'        => ['sometimes', 'required', 'string', 'max:150'],
            'tipo_investimento' => ['sometimes', 'required', 'string', 'max:100'],
            'data_aporte'       => ['sometimes', 'required', 'date'],
            'valor_aportado'    => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'quantidade_cotas'  => ['nullable', 'numeric', 'min:0'],
            'percentual_mensal' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_anual'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'banco_id'          => ['nullable', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'observacoes'       => ['nullable', 'string', 'max:2000'],
        ];
    }
}
