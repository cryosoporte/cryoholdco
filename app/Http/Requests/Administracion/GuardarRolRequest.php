<?php

namespace App\Http\Requests\Administracion;

use Illuminate\Foundation\Http\FormRequest;

class GuardarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{nombre: list<string>, descripcion: list<string>} */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:60'],
            'descripcion' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del rol.',
            'nombre.string' => 'Escribe el nombre como texto.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede superar los 60 caracteres.',
            'descripcion.string' => 'Escribe la descripción como texto.',
            'descripcion.max' => 'La descripción no puede superar los 300 caracteres.',
        ];
    }
}
