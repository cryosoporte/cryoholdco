<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('clientes', [
            'cliente' => $request->session()->get('consulta_cliente'),
        ]);
    }

    public function consultar(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            ['dni' => ['required', 'digits:8']],
            ['dni.required' => 'Escribe tu DNI.', 'dni.digits' => 'El DNI debe tener 8 dígitos.']
        );

        $contrato = Contrato::buscarPorDni($datos['dni']);

        if (! $contrato) {
            return to_route('clientes.index')->withErrors([
                'dni' => 'No encontramos ese DNI. Revísalo o escríbenos por WhatsApp.',
            ]);
        }

        $toma = $contrato->tomaActiva();

        return to_route('clientes.index')->with('consulta_cliente', [
            'dni' => $contrato->identificacion1,
            'nombre' => $contrato->nombre_completo,
            'primer_nombre' => $contrato->primer_nombre,
            'numero_contrato' => $contrato->numero_contrato,
            'estado' => $contrato->estado(),
            'fecha_contrato' => $contrato->fecha_contrato?->toDateString(),
            'fecha_nacimiento' => $contrato->fecha_nacimiento?->toDateString(),
            'fecha_toma' => $toma?->fecha_toma?->toDateString(),
            'fecha_resultado' => $toma?->fecha_resultado?->toDateString(),
            'sede_toma' => $toma?->dondeSeTomo(),
            'sedes' => $contrato->sedes()->map(fn (object $sede): array => [
                'laboratorio' => $sede->alias,
                'codigo' => $sede->codigo_sede,
                'nombre' => $sede->descripcion_sede,
            ])->all(),
        ]);
    }
}
