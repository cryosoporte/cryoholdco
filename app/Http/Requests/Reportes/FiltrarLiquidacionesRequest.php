<?php

namespace App\Http\Requests\Reportes;

use App\Reportes\Comisiones\DatosRealesComisiones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltrarLiquidacionesRequest extends FormRequest
{
    public const TODOS = 'todos';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(DatosRealesComisiones $datos): array
    {
        $opciones = [...array_map('strval', $datos->aniosDisponibles()), self::TODOS];

        return ['anio' => ['nullable', 'string', Rule::in($opciones)]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'anio.string' => 'Elige uno de los años disponibles.',
            'anio.in' => 'Elige uno de los años disponibles.',
        ];
    }

    /** Año elegido, el actual si no se eligió ninguno o null para «Todos». */
    public function anio(DatosRealesComisiones $datos): ?int
    {
        $anio = $this->validated('anio') ?? (string) $datos->anioActual();

        return $anio === self::TODOS ? null : (int) $anio;
    }
}
