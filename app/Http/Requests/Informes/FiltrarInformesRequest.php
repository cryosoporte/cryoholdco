<?php

namespace App\Http\Requests\Informes;

use App\Informes\AlcanceInformes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarInformesRequest extends FormRequest
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
            'envio' => ['nullable', Rule::in([...AlcanceInformes::ENVIOS, AlcanceInformes::ENVIO_TODOS])],
            'liberacion' => ['nullable', Rule::in(AlcanceInformes::LIBERACIONES)],
            'antiguedad' => ['nullable', Rule::in(array_keys(AlcanceInformes::ANTIGUEDADES))],
            'sangre' => ['nullable', Rule::in(AlcanceInformes::RESULTADOS)],
            'tejido' => ['nullable', Rule::in(AlcanceInformes::RESULTADOS)],
        ];
    }
}
