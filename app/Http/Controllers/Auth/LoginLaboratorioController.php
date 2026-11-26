<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginLaboratorioRequest;
use App\Models\UsuarioLaboratorio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inicio de sesión de los laboratorios en el mostrador de Serología, con su RUC.
 * Usa el guard «laboratorio», separado del panel interno.
 */
class LoginLaboratorioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('laboratorio-login');
    }

    public function store(LoginLaboratorioRequest $request): RedirectResponse
    {
        $request->asegurarQueNoEsteBloqueado();
        $datos = $request->validated();

        $acceso = UsuarioLaboratorio::query()->where('identificacion', $datos['ruc'])->first();

        if (! $acceso || ! Hash::check($datos['password'], $acceso->password)) {
            $request->registrarIntentoFallido();

            return to_route('laboratorio.login')
                ->withErrors(['ruc' => 'RUC o contraseña incorrectos.'])
                ->onlyInput('ruc');
        }

        $request->limpiarIntentos();

        if (! $acceso->puedeIngresar()) {
            return to_route('laboratorio.login')
                ->withErrors(['ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.'])
                ->onlyInput('ruc');
        }

        Auth::guard('laboratorio')->login($acceso);
        $request->session()->regenerate();
        // Cada sesión elige su sede: la del mostrador anterior no se hereda.
        $request->session()->forget(['laboratorio', 'consulta_laboratorio']);

        return to_route('laboratorio.index');
    }

    /**
     * Cierra solo la sesión del laboratorio: si en el mismo navegador hay una
     * sesión del panel interno, se conserva.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('laboratorio')->logout();

        $request->session()->forget(['laboratorio', 'consulta_laboratorio']);
        $request->session()->migrate(true);
        $request->session()->regenerateToken();

        return to_route('laboratorio.login');
    }
}
