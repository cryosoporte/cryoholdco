<?php

namespace App\Http\Requests\Reportes;

use App\Reportes\Comisiones\DatosRealesComisiones;
use Illuminate\Contracts\Support\Stringable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NuevaLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule|Stringable>> */
    public function rules(DatosRealesComisiones $datos): array
    {
        return ['periodo' => ['required', 'string', Rule::in(array_column($datos->periodosDisponibles(), 'id'))]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'periodo.required' => 'Elige un período.',
            'periodo.string' => 'Elige uno de los períodos disponibles.',
            'periodo.in' => 'Elige uno de los períodos disponibles.',
        ];
    }
}
