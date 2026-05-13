<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Transferencia;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->temPermissao('transferencias', 'editar') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'valor'      => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'data'       => ['sometimes', 'required', 'date'],
            'origem_id'  => ['sometimes', 'required', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'destino_id' => ['sometimes', 'required', 'integer', Rule::exists('bancos', 'id')->where('tenant_id', $tenantId)],
            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Garante que origem != destino considerando o estado já persistido
     * quando o request manda só um dos lados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            /** @var Transferencia|null $current */
            $current = $this->route('transferencia');
            if (! $current instanceof Transferencia) {
                return;
            }

            $origem  = $this->input('origem_id',  $current->origem_id);
            $destino = $this->input('destino_id', $current->destino_id);

            if ((int) $origem === (int) $destino) {
                $v->errors()->add('origem_id', 'A conta de origem deve ser diferente da conta de destino.');
            }
        });
    }
}
