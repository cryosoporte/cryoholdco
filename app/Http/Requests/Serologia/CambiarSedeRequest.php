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
            'laboratorio' => ['required', 'string', 'max:15'],
            'sede' => ['nullable', 'string', 'max:2'],
        ];
    }
}
