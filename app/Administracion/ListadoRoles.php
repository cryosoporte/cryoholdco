<?php

namespace App\Administracion;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * @phpstan-type RolResumen array{codigo: string, nombre: string, descripcion: ?string, es_sistema: bool, personas: int, permisos: int, motivo_no_eliminar: ?string}
 * @phpstan-type RolDetalle array{codigo: string, nombre: string, descripcion: ?string, es_sistema: bool, personas: int, permisos: list<string>, obsoletos: list<string>, motivo_no_eliminar: ?string}
 * @phpstan-type ModuloPermisos array{modulo: string, permisos: list<array{codigo: string, nombre: string, descripcion: string, fijo: bool, exclusivo: bool}>}
 */
class ListadoRoles
{
    /** @return list<RolResumen> */
    public function consultar(): array
    {
        return Rol::query()
            ->with(['permisos', 'asignaciones.usuario:id,name,email,identificacion'])
            ->orderByRaw('LOWER(nombre)')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Rol $rol): array => $this->resumen($rol, $this->personasAgrupadas($rol)->count()))
            ->all();
    }

    /** @return array{rol: RolDetalle, catalogo: list<ModuloPermisos>, personas: list<array{nombre: string, correos: list<string>}>} */
    public function mostrar(Rol $rol): array
    {
        $rol->loadMissing(['permisos', 'asignaciones.usuario:id,name,email,identificacion']);
        $personas = $this->personasAgrupadas($rol)->map(fn (Collection $cuentas): array => [
            'nombre' => $cuentas->first()->name,
            'correos' => $cuentas->map(fn (User $cuenta): string => mb_strtolower(trim($cuenta->email)))
                ->filter()->uniqueStrict()->values()->all(),
        ])->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'rol' => [
                ...$this->resumen($rol, count($personas)),
                'permisos' => $this->permisosVigentes($rol),
                'obsoletos' => $rol->permisos->pluck('permiso')
                    ->diff(array_keys(CatalogoPermisos::PERMISOS))->sort()->values()->all(),
            ],
            'catalogo' => $this->catalogo($rol),
            'personas' => $personas,
        ];
    }

    /** @return RolResumen */
    private function resumen(Rol $rol, int $personas): array
    {
        return [
            'codigo' => $rol->codigo,
            'nombre' => $rol->nombre,
            'descripcion' => $rol->descripcion,
            'es_sistema' => $rol->esSistema(),
            'personas' => $personas,
            'permisos' => count($this->permisosVigentes($rol)),
            'motivo_no_eliminar' => $rol->impedimentoParaEliminar(),
        ];
    }

    /** @return Collection<string, Collection<int, User>> */
    private function personasAgrupadas(Rol $rol): Collection
    {
        return $rol->asignaciones->pluck('usuario')
            ->filter()
            ->sortBy('id')
            ->groupBy(fn (User $usuario): string => $usuario->clavePersona());
    }

    /** @return list<string> */
    private function permisosVigentes(Rol $rol): array
    {
        return array_values(array_intersect(
            array_keys(CatalogoPermisos::PERMISOS),
            [...$rol->permisosPermitidos($rol->permisos->pluck('permiso')->all()), ...$rol->permisosFijos()],
        ));
    }

    /** @return list<ModuloPermisos> */
    private function catalogo(Rol $rol): array
    {
        $modulos = [];
        $fijos = $rol->permisosFijos();
        $exclusivos = $rol->permisosExclusivosAjenos();
        foreach (CatalogoPermisos::PERMISOS as $codigo => $permiso) {
            $modulos[$permiso['modulo']][] = [
                'codigo' => $codigo,
                'nombre' => $permiso['nombre'],
                'descripcion' => $permiso['descripcion'],
                'fijo' => in_array($codigo, $fijos, true),
                'exclusivo' => in_array($codigo, $exclusivos, true),
            ];
        }

        $catalogo = [];
        foreach ($modulos as $modulo => $permisos) {
            $catalogo[] = compact('modulo', 'permisos');
        }

        return $catalogo;
    }
}
