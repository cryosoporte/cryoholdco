<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginLaboratorioRequest extends FormRequest
{
    /** Intentos fallidos permitidos por RUC e IP antes de bloquear. */
    public const INTENTOS_PERMITIDOS = 5;

    /** Segundos que dura el bloqueo tras agotar los intentos. */
    public const SEGUNDOS_BLOQUEO = 60;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ruc' => preg_replace('/\s+/', '', (string) $this->input('ruc')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ruc' => ['required', 'string', 'max:15'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Get the validation messages shown on the laboratory login.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc.required' => 'Escribe el RUC del laboratorio.',
            'ruc.max' => 'El RUC no puede tener más de 15 caracteres.',
            'password.required' => 'Escribe la contraseña.',
        ];
    }

    /**
     * Detiene el ingreso si ese RUC agotó sus intentos desde esta IP.
     *
     * @throws ValidationException
     */
    public function asegurarQueNoEsteBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->claveIntentos(), self::INTENTOS_PERMITIDOS)) {
            return;
        }

        $segundos = RateLimiter::availableIn($this->claveIntentos());

        throw ValidationException::withMessages([
            'ruc' => "Demasiados intentos. Intenta de nuevo en {$segundos} segundos.",
        ]);
    }

    public function registrarIntentoFallido(): void
    {
        RateLimiter::hit($this->claveIntentos(), self::SEGUNDOS_BLOQUEO);
    }

    public function limpiarIntentos(): void
    {
        RateLimiter::clear($this->claveIntentos());
    }

    private function claveIntentos(): string
    {
        return 'login-laboratorio|'.$this->input('ruc').'|'.$this->ip();
    }
}
