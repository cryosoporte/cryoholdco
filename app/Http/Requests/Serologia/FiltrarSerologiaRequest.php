<?php

namespace App\Http\Requests\Serologia;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FiltrarSerologiaRequest extends FormRequest
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
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:pendiente,tomada,recibido,por_vencer'],
            'asesor' => ['nullable', 'string', 'max:100'],
        ];
    }
}
