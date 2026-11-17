<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CargarFuenteRequest extends FormRequest
{
    /**
     * Por ahora cualquier usuario autenticado puede cargar una fuente. Cuando
     * exista el rol, la restricción va aquí.
     */
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
            'archivo' => ['required', 'file', 'extensions:xlsx', 'max:20480'],
        ];
    }

    /**
     * Get the validation messages shown when uploading a data source file.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige un archivo para cargar.',
            'archivo.file' => 'No se pudo recibir el archivo. Intenta de nuevo.',
            'archivo.extensions' => 'El archivo debe ser .xlsx. Si NetSuite lo exportó como .xls, ábrelo y guárdalo como .xlsx.',
            'archivo.max' => 'El archivo no puede pesar más de 20 MB.',
        ];
    }
}
