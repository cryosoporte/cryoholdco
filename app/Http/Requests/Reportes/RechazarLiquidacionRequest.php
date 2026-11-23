<?php

namespace App\Http\Requests\Reportes;

use App\Reportes\Comisiones\RechazarLiquidacion;
use Illuminate\Contracts\Support\Stringable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RechazarLiquidacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|ValidationRule|Stringable>> */
    public function rules(): array
    {
        return RechazarLiquidacion::REGLAS_COMENTARIO;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return RechazarLiquidacion::MENSAJES_COMENTARIO;
    }
}
