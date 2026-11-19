<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Contracts\Support\Stringable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RechazarLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule|Stringable>> */
    public function rules(): array
    {
        return ['comentario' => ['required', 'string', 'min:10', 'max:500']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'comentario.required' => 'Escribe un comentario para devolver la liquidación.',
            'comentario.string' => 'Escribe el comentario como texto.',
            'comentario.min' => 'El comentario debe tener al menos 10 caracteres.',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres.',
        ];
    }
}
