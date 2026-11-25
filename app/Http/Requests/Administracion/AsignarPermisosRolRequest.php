<?php

namespace App\Http\Requests\Administracion;

use App\Administracion\CatalogoPermisos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class AsignarPermisosRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array{permisos: list<string>, 'permisos.*': list<string|In>} */
    public function rules(): array
    {
        return [
            'permisos' => ['present', 'array', 'list'],
            'permisos.*' => ['bail', 'required', 'string', Rule::in(array_keys(CatalogoPermisos::PERMISOS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'permisos.present' => 'Envía los permisos del rol.',
            'permisos.array' => 'Envía los permisos como una lista.',
            'permisos.list' => 'Envía los permisos como una lista.',
            'permisos.*' => 'Elige un permiso del catálogo.',
        ];
    }
}
