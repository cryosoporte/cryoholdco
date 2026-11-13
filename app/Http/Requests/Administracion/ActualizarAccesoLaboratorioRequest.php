<?php

namespace App\Http\Requests\Administracion;

use App\Models\UsuarioLaboratorio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarAccesoLaboratorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The password is
     * optional: when it comes empty the current one is kept.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
            'estado' => ['required', Rule::in([UsuarioLaboratorio::ESTADO_ACTIVO, UsuarioLaboratorio::ESTADO_INACTIVO])],
        ];
    }

    /**
     * Get the validation messages shown when updating a laboratory access.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede tener más de 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'estado.required' => 'Elige si el acceso queda activo o inactivo.',
            'estado.in' => 'Elige si el acceso queda activo o inactivo.',
        ];
    }
}
