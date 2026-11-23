<?php

namespace App\Http\Requests\Administracion;

use App\Administracion\RolSistema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarRolUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rol' => ['present', 'nullable', Rule::enum(RolSistema::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rol.present' => 'Elige un rol válido.',
            'rol.enum' => 'Elige un rol válido.',
        ];
    }
}
