<?php

namespace App\Http\Requests\Serologia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnviarRecordatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'numero_base' => ['required', 'string', 'max:100'],
            'identificacion' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Get the validation messages returned to the reminder action.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_base.required' => 'El cliente no tiene un número de contrato base.',
            'identificacion.required' => 'Falta la identificación del cliente.',
        ];
    }
}
