<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDespesaRequest extends FormRequest
{
    public const TIPOS_PAGAMENTO = ['dinheiro', 'pix', 'debito', 'credito', 'transferencia', 'boleto'];
    public const FREQUENCIAS     = ['diaria', 'semanal', 'quinzenal', 'mensal', 'trimestral', 'semestral', 'anual'];

    public function authorize(): bool
    {
        return $this->user()?->temPermissao('despesas', 'criar') ?? false;
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
                // Quando é cartão de crédito, o banco/cartão é obrigatório
                // (sem banco o observer não consegue computar saldo_cartao).
                'required_if:tipo_pagamento,credito',
                Rule::exists('bancos', 'id')->where('tenant_id', $tenantId),
            ],
            'tipo_pagamento'  => ['nullable', Rule::in(self::TIPOS_PAGAMENTO)],
            'parcelas'        => ['nullable', 'integer', 'min:0', 'max:360'],
            'frequencia'      => ['nullable', Rule::in(self::FREQUENCIAS)],
            'recorrente'      => ['nullable', 'boolean'],
            'observacoes'     => ['nullable', 'string', 'max:2000'],
            'numero_documento'=> ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor.required'             => 'Informe o valor.',
            'valor.min'                  => 'O valor deve ser maior que zero.',
            'data_compra.required'       => 'Informe a data da compra.',
            'tipo_pagamento.in'          => 'Tipo de pagamento inválido.',
            'frequencia.in'              => 'Frequência inválida.',
            'forma_pagamento.required_if'=> 'Selecione o cartão/banco quando o pagamento é em crédito.',
        ];
    }
}
