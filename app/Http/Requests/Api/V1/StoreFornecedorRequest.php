<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('fornecedores', 'criar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome'        => ['required', 'string', 'max:150'],
            'grupo'       => ['nullable', 'string', 'max:80'],
            'icone'       => ['nullable', 'string', 'max:80'],
            'telefone'    => ['nullable', 'string', 'max:40'],
            'cnpj'        => ['nullable', 'string', 'max:20'],
            'contato'     => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
