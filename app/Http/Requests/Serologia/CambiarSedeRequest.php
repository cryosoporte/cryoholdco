<?php

namespace App\Http\Requests\Serologia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CambiarSedeRequest extends FormRequest
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
            'sede' => ['required', 'string', 'max:2'],
        ];
    }

    /**
     * Get the validation messages shown when changing the active site.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sede.required' => 'Elige una sede.',
            'sede.max' => 'Esa sede no está activa para tu laboratorio.',
        ];
    }
}
