<?php

namespace App\Http\Middleware;

use App\Serologia\SedeActivaLaboratorio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El mostrador trabaja siempre desde una sede concreta. Al laboratorio con varias
 * sedes activas se le pide elegirla antes de dejarlo entrar: hasta que lo haga,
 * cualquier ruta del mostrador lo lleva a la pantalla de elección.
 */
class SedeLaboratorioElegida
{
    public function __construct(private readonly SedeActivaLaboratorio $sedeActiva) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->sedeActiva->debeElegir()) {
            return to_route('laboratorio.sede.elegir');
        }

        return $next($request);
    }
}
