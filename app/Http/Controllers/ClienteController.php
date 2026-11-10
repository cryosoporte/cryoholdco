<?php

namespace App\Http\Controllers;

use App\Models\Contrato;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index()
    {
        return view('serologia.clientes.index');
    }

    public function consultar(Request $request)
    {
        
        $datos = $request->validate(
            ['dni' => ['required', 'digits:8'],],
            ['dni.required' => 'Escribe tu DNI.','dni.digits' => 'El DNI debe tener 8 dígitos.',]
        );

        $contrato = Contrato::buscarPorDni($datos['dni']);

        if (! $contrato) {
            $mensaje = 'No encontramos ese DNI. Revísalo o escríbenos por WhatsApp.';

            if ($request->expectsJson()) {
                return response()->json(['errors' => ['dni' => [$mensaje]]], 422);
            }

            return back()->withInput()->withErrors(['dni' => $mensaje]);
        }

        $datosVista = [
            'contrato' => $contrato,
            'sedes' => $contrato->sedes(),
        ];

        return $request->expectsJson()
            ? view('serologia.clientes.partials.ficha', $datosVista)
            : view('serologia.clientes.index', $datosVista);
    }
}
