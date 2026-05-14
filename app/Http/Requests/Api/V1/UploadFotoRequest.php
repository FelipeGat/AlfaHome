<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.required' => 'Anexe um arquivo de imagem no campo "foto".',
            'foto.image'    => 'O arquivo deve ser uma imagem.',
            'foto.mimes'    => 'Formatos aceitos: jpg, jpeg, png, gif, webp.',
            'foto.max'      => 'A imagem deve ter no máximo 2 MB.',
        ];
    }
}
