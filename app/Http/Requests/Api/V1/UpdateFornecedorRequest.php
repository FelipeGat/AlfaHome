<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('fornecedores', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome'        => ['sometimes', 'required', 'string', 'max:150'],
            'grupo'       => ['nullable', 'string', 'max:80'],
            'icone'       => ['nullable', 'string', 'max:80'],
            'telefone'    => ['nullable', 'string', 'max:40'],
            'cnpj'        => ['nullable', 'string', 'max:20'],
            'contato'     => ['nullable', 'string', 'max:120'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
