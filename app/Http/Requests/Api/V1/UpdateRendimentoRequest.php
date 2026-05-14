<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('investimentos', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'data'        => ['sometimes', 'required', 'date'],
            'valor_atual' => ['sometimes', 'required', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
