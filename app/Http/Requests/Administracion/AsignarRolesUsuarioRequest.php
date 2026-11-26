<?php

namespace App\Http\Requests\Administracion;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class AsignarRolesUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{roles: list<string>, 'roles.*': list<string|Exists>}
     */
    public function rules(): array
    {
        return [
            'roles' => ['bail', 'present', 'array', 'list'],
            'roles.*' => ['bail', 'required', 'string', 'max:30', Rule::exists(Rol::class, 'codigo')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'roles.present' => 'Envía los roles de la persona.',
            'roles.array' => 'Envía los roles como una lista.',
            'roles.list' => 'Envía los roles como una lista.',
            'roles.*' => 'Elige un rol válido.',
        ];
    }
}
