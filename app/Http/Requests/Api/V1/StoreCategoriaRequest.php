<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('categorias', 'criar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome'  => ['required', 'string', 'max:120'],
            'tipo'  => ['required', Rule::in(['DESPESA', 'RECEITA'])],
            'icone' => ['nullable', 'string', 'max:80'],
        ];
    }
}
