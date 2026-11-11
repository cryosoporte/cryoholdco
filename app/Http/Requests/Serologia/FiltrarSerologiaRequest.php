<?php

namespace App\Http\Requests\Serologia;

use App\Serologia\ListadoSerologia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'estado' => ['nullable', Rule::in(ListadoSerologia::ESTADOS)],
            'asesor' => ['nullable', 'string', 'max:100'],
        ];
    }
}
