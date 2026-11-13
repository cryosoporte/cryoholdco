<?php

namespace App\Http\Requests\Administracion;

use App\Models\Sede;
use App\Models\UsuarioLaboratorio;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearAccesoLaboratorioRequest extends FormRequest
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
            'identificacion' => [
                'required', 'string', 'max:15',
                Rule::exists('lab_laboratorios_sedes', 'identificacion')->where('estado', Sede::ESTADO_ACTIVA),
                Rule::unique('lab_laboratorios_usuarios', 'identificacion'),
            ],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'estado' => ['required', Rule::in([UsuarioLaboratorio::ESTADO_ACTIVO, UsuarioLaboratorio::ESTADO_INACTIVO])],
        ];
    }

    /**
     * Get the validation messages shown when creating a laboratory access.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identificacion.required' => 'Falta el RUC del laboratorio.',
            'identificacion.exists' => 'Ese laboratorio no tiene sedes activas.',
            'identificacion.unique' => 'Ese laboratorio ya tiene un acceso. Edítalo para cambiar su contraseña.',
            'password.required' => 'Escribe una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede tener más de 72 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'estado.required' => 'Elige si el acceso queda activo o inactivo.',
            'estado.in' => 'Elige si el acceso queda activo o inactivo.',
        ];
    }
}
