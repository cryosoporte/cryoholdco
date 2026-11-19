<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Contracts\Support\Stringable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarRolDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule|Stringable>> */
    public function rules(): array
    {
        return ['rol' => ['required', 'string', Rule::in(['analista', 'gerenta'])]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rol.required' => 'Elige un rol para la demo.',
            'rol.string' => 'Elige Analista o Gerenta.',
            'rol.in' => 'Elige Analista o Gerenta.',
        ];
    }
}
