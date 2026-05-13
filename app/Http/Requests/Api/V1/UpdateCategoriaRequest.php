<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('categorias', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome'  => ['sometimes', 'required', 'string', 'max:120'],
            'tipo'  => ['sometimes', 'required', Rule::in(['DESPESA', 'RECEITA'])],
            'icone' => ['nullable', 'string', 'max:80'],
        ];
    }
}
