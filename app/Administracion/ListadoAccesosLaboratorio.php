<?php

namespace App\Administracion;

use App\Models\Sede;
use App\Models\UsuarioLaboratorio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Los laboratorios que pueden tener acceso al mostrador: los que tienen sedes activas
 * y los que ya tienen un acceso aunque se hayan quedado sin sedes, para poder desactivarlo.
 *
 * Un laboratorio se identifica solo por su RUC, sin importar la compañía.
 */
class ListadoAccesosLaboratorio
{
    /**
     * @return Collection<int, array{identificacion: string, nombre: ?string, sedes_activas: int, acceso: ?array{id: int, estado: string}}>
     */
    public function consultar(): Collection
    {
        $sedesActivas = DB::table('lab_laboratorios_sedes')
            ->select('identificacion', DB::raw('count(*) as sedes_activas'))
            ->where('estado', Sede::ESTADO_ACTIVA)
            ->groupBy('identificacion')
            ->pluck('sedes_activas', 'identificacion');

        $accesos = UsuarioLaboratorio::query()->get()->keyBy('identificacion');

        $rucs = $sedesActivas->keys()->merge($accesos->keys())->unique()->values();

        $nombres = $rucs->isEmpty() ? collect() : DB::table('cxp_proveedores')
            ->select('identificacion', DB::raw('max(alias) as alias'))
            ->whereIn('identificacion', $rucs->all())
            ->groupBy('identificacion')
            ->pluck('alias', 'identificacion');

        return $rucs
            ->map(function (string $ruc) use ($sedesActivas, $accesos, $nombres): array {
                $acceso = $accesos->get($ruc);

                return [
                    'identificacion' => $ruc,
                    'nombre' => $nombres->get($ruc),
                    'sedes_activas' => (int) $sedesActivas->get($ruc, 0),
                    'acceso' => $acceso === null ? null : [
                        'id' => (int) $acceso->id,
                        'estado' => $acceso->estado,
                    ],
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => ($a['nombre'] === null) <=> ($b['nombre'] === null),
                fn (array $a, array $b): int => strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? ''),
                fn (array $a, array $b): int => strcmp($a['identificacion'], $b['identificacion']),
            ])
            ->values();
    }
}
