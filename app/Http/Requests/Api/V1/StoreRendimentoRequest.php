<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreRendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('investimentos', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'data'        => ['required', 'date'],
            'valor_atual' => ['required', 'numeric', 'min:0'],
            'observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
