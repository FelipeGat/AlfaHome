<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFamiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('familiares', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome'          => ['sometimes', 'required', 'string', 'max:120'],
            'foto'          => ['nullable', 'string', 'max:255'],
            'salario'       => ['nullable', 'numeric', 'min:0'],
            'limite_cartao' => ['nullable', 'numeric', 'min:0'],
            'limite_cheque' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
