<?php

namespace App\Http\Requests\Serologia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConsultarClienteRequest extends FormRequest
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
            'dni' => ['required', 'string', 'alpha_num:ascii', 'max:20'],
        ];
    }

    /**
     * Get the validation messages shown on the client lookup form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dni.required' => 'Escribe tu DNI.',
            'dni.string' => 'El DNI solo puede tener letras y números.',
            'dni.alpha_num' => 'El DNI solo puede tener letras y números.',
            'dni.max' => 'El DNI puede tener hasta 20 caracteres.',
        ];
    }
}
