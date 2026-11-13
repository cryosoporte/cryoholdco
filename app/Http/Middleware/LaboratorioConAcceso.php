<?php

namespace App\Http\Middleware;

use App\Models\UsuarioLaboratorio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del mostrador al laboratorio que ya no puede ingresar: su acceso se desactivó
 * o se quedó sin sedes activas mientras tenía la sesión abierta.
 */
class LaboratorioConAcceso
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $acceso = $request->user('laboratorio');

        if ($acceso instanceof UsuarioLaboratorio && ! $acceso->puedeIngresar()) {
            Auth::guard('laboratorio')->logout();
            $request->session()->forget(['laboratorio', 'consulta_laboratorio']);

            return to_route('laboratorio.login')->withErrors([
                'ruc' => 'Tu acceso no está habilitado. Comunícate con Criocord.',
            ]);
        }

        return $next($request);
    }
}
