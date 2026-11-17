<?php

namespace App\Http\Requests\Reportes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CargarDataTestRequest extends FormRequest
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
            'archivo' => ['required', 'file', 'extensions:csv', 'max:10240'],
        ];
    }

    /**
     * Get the validation messages shown when uploading the commissions file.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Elige un archivo para cargar',
            'archivo.file' => 'No se pudo recibir el archivo. Intenta de nuevo',
            'archivo.extensions' => 'El archivo debe ser .csv',
            'archivo.max' => 'El archivo no puede pesar más de 10 MB',
        ];
    }
}
