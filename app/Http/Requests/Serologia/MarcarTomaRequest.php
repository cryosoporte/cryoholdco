<?php

namespace App\Http\Requests\Serologia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarcarTomaRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:40'],
            'numero_toma' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the validation messages shown when registering a sample.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo.required' => 'Falta el código del contrato.',
            'numero_toma.required' => 'Vuelve a buscar el contrato antes de registrar la muestra.',
        ];
    }
}
