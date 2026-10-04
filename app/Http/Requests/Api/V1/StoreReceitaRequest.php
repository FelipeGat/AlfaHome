<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Receita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceitaRequest extends FormRequest
{
    public const TIPOS_PAGAMENTO = Receita::TIPOS_PAGAMENTO;
    public const FREQUENCIAS     = ['diaria', 'semanal', 'quinzenal', 'mensal', 'trimestral', 'semestral', 'anual'];

    public function authorize(): bool
    {
        return $this->user()?->temPermissao('receitas', 'criar') ?? false;
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
            'tipo_pagamento'            => ['nullable', Rule::in(self::TIPOS_PAGAMENTO)],
            'parcelas'                  => ['nullable', 'integer', 'min:0', 'max:360'],
            'frequencia'                => ['nullable', Rule::in(self::FREQUENCIAS)],
            'recorrente'                => ['nullable', 'boolean'],
            'observacoes'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor.required'                     => 'Informe o valor.',
            'valor.min'                          => 'O valor deve ser maior que zero.',
            'data_prevista_recebimento.required' => 'Informe a data prevista.',
            'tipo_pagamento.in'                  => 'Tipo de pagamento inválido.',
            'frequencia.in'                      => 'Frequência inválida.',
        ];
    }
}
