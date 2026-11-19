<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\FuenteDatos;
use App\Reportes\Comisiones\DatosComisiones;
use App\Reportes\Comisiones\DemoComisiones;
use App\Reportes\Fuentes\FuenteFacturasOracle;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ComisionesController extends Controller
{
    private const FUENTE = FuenteFacturasOracle::CODIGO;

    public function index(DemoComisiones $demo, DatosComisiones $datos): Response
    {
        return Inertia::render('comisiones', [
            'fuente' => self::actualizacionFuente(),
            'liquidaciones' => $demo->liquidaciones(),
            'rol' => $demo->rol(),
            'periodos_disponibles' => $datos->periodosDisponibles(),
        ]);
    }

    /** @return array{codigo: string, nombre: string, fecha_actualizacion: ?string, actualizado_por: ?string}|null */
    public static function actualizacionFuente(): ?array
    {
        $fuente = FuenteDatos::porCodigo(self::FUENTE);

        return $fuente === null ? null : Arr::only(
            $fuente->datosActualizacion(),
            ['codigo', 'nombre', 'fecha_actualizacion', 'actualizado_por'],
        );
    }
}
