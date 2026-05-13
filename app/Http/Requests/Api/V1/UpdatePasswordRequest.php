<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'      => 'Informe a senha atual.',
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.required'              => 'Informe a nova senha.',
            'password.confirmed'             => 'A confirmação da nova senha não confere.',
        ];
    }
}
