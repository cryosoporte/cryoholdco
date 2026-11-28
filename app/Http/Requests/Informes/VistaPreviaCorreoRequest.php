<?php

namespace App\Http\Requests\Informes;

use App\Informes\AlcanceInformes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VistaPreviaCorreoRequest extends FormRequest
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
            'numero_base' => ['required', 'string', 'max:100'],
            'motivo_sangre' => ['nullable', Rule::in(array_keys(AlcanceInformes::MOTIVOS_NO_APTO))],
            'motivo_tejido' => ['nullable', Rule::in(array_keys(AlcanceInformes::MOTIVOS_NO_APTO))],
        ];
    }

    /**
     * Get the validation messages returned to the email preview.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_base.required' => 'El cliente no tiene un número de contrato base.',
            'motivo_sangre.in' => 'El motivo elegido para sangre no es válido.',
            'motivo_tejido.in' => 'El motivo elegido para tejido no es válido.',
        ];
    }
}
