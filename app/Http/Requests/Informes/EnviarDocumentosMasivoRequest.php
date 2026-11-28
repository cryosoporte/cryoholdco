<?php

namespace App\Http\Requests\Informes;

use App\Informes\AlcanceInformes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EnviarDocumentosMasivoRequest extends FormRequest
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
            'contratos' => ['required', 'array', 'min:1', 'max:'.AlcanceInformes::MAXIMO_POR_ENVIO_MASIVO],
            'contratos.*.numero_base' => ['required', 'string', 'max:100'],
            'contratos.*.identificacion' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Get the validation messages returned to the bulk send action.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contratos.required' => 'Selecciona al menos un contrato.',
            'contratos.min' => 'Selecciona al menos un contrato.',
            'contratos.max' => 'No puedes enviar a más de '.AlcanceInformes::MAXIMO_POR_ENVIO_MASIVO.' contratos por petición.',
            'contratos.*.numero_base.required' => 'Un contrato seleccionado no tiene número base.',
            'contratos.*.identificacion.required' => 'Un contrato seleccionado no tiene identificación del cliente.',
        ];
    }
}
