<?php

namespace App\Http\Requests\Informes;

use App\Informes\AlcanceInformes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnviarDocumentosRequest extends FormRequest
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
            'identificacion' => ['required', 'string', 'max:100'],
            'archivos' => ['required', 'array', 'min:1', 'max:20'],
            'archivos.*' => ['required', 'string', 'max:255'],
            'motivo_sangre' => ['nullable', Rule::in(array_keys(AlcanceInformes::MOTIVOS_NO_APTO))],
            'motivo_tejido' => ['nullable', Rule::in(array_keys(AlcanceInformes::MOTIVOS_NO_APTO))],
        ];
    }

    /**
     * Get the validation messages returned to the document send action.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_base.required' => 'El cliente no tiene un número de contrato base.',
            'identificacion.required' => 'Falta la identificación del cliente.',
            'archivos.required' => 'Selecciona al menos un archivo para enviar.',
            'archivos.min' => 'Selecciona al menos un archivo para enviar.',
            'archivos.max' => 'No puedes enviar más de 20 archivos a la vez.',
        ];
    }
}
