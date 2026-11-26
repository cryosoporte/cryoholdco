<?php

namespace App\Administracion;

use App\Models\EventoSeguridad;
use Carbon\CarbonImmutable;

/** @phpstan-type LineaDetalle array{etiqueta: string, valor: string} */
class ListadoEventosSeguridad
{
    public const TIPOS = [
        EventoSeguridad::ROL_CREADO => 'Rol creado',
        EventoSeguridad::ROL_EDITADO => 'Rol editado',
        EventoSeguridad::ROL_ELIMINADO => 'Rol eliminado',
        EventoSeguridad::PERMISOS_ROL => 'Permisos de rol',
        EventoSeguridad::ROLES_USUARIO => 'Roles de usuario',
    ];

    /** @return list<array{id: int, fecha: string, autor: string, empresa: ?string, tipo: string, accion: string, referencia: ?string, detalle: list<LineaDetalle>}> */
    public function consultar(): array
    {
        return EventoSeguridad::query()->orderByDesc('fecha')->orderByDesc('id')->limit(200)->get()
            ->map(fn (EventoSeguridad $evento): array => $this->presentar($evento))->all();
    }

    /** @return array{id: int, fecha: string, autor: string, empresa: ?string, tipo: string, accion: string, referencia: ?string, detalle: list<LineaDetalle>} */
    private function presentar(EventoSeguridad $evento): array
    {
        $detalle = (array) json_decode($evento->detalle ?? '', true);
        $tipo = $evento->accion;
        $accion = self::TIPOS[$tipo] ?? 'Cambio de seguridad';
        $referencia = $evento->rol;

        if ($tipo === EventoSeguridad::PERMISOS_ROL) {
            $accion = 'Permisos de '.$evento->rol;
            $referencia = null;
        } elseif ($tipo === EventoSeguridad::ROLES_USUARIO) {
            $persona = (array) ($detalle['persona'] ?? []);
            $nombre = $persona['nombre'] ?? $this->textos($persona['correos'] ?? null)[0] ?? null;
            $accion = $nombre ? 'Roles de '.$nombre : 'Roles de usuario';
            $referencia = $nombre ? null : $evento->usuarios;
        }

        return [
            'id' => (int) $evento->id,
            'fecha' => CarbonImmutable::parse($evento->fecha, config('reportes.zona_horaria'))->toIso8601String(),
            'autor' => $evento->autor_nombre,
            'empresa' => $evento->autor_empresa,
            'tipo' => $tipo,
            'accion' => $accion,
            'referencia' => $referencia,
            'detalle' => match ($tipo) {
                EventoSeguridad::ROL_CREADO => $this->datosRol((array) ($detalle['despues'] ?? [])),
                EventoSeguridad::ROL_EDITADO => $this->cambiosRol(
                    (array) ($detalle['antes'] ?? []),
                    (array) ($detalle['despues'] ?? []),
                ),
                EventoSeguridad::ROL_ELIMINADO => $this->datosRol((array) ($detalle['antes'] ?? [])),
                EventoSeguridad::PERMISOS_ROL => $this->cambiosConjunto(
                    $this->nombresDePermisos($this->textos($detalle['agregados'] ?? null)),
                    $this->nombresDePermisos($this->textos($detalle['quitados'] ?? null)),
                ),
                EventoSeguridad::ROLES_USUARIO => $this->cambiosRolesUsuario($detalle),
                default => [],
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<LineaDetalle>
     */
    private function datosRol(array $datos): array
    {
        $lineas = [];
        foreach (['nombre' => 'Nombre', 'descripcion' => 'Descripción'] as $campo => $etiqueta) {
            $valor = $datos[$campo] ?? null;
            if (is_string($valor) && $valor !== '') {
                $lineas[] = compact('etiqueta', 'valor');
            }
        }
        $permisos = $this->textos($datos['permisos'] ?? null);
        if ($permisos !== []) {
            $lineas[] = ['etiqueta' => 'Permisos quitados', 'valor' => implode(', ', $this->nombresDePermisos($permisos))];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @return list<LineaDetalle>
     */
    private function cambiosRol(array $antes, array $despues): array
    {
        $lineas = [];
        foreach (['nombre' => 'Nombre', 'descripcion' => 'Descripción'] as $campo => $etiqueta) {
            $anterior = $antes[$campo] ?? null;
            $nuevo = $despues[$campo] ?? null;
            if ($anterior !== $nuevo) {
                $lineas[] = ['etiqueta' => $etiqueta, 'valor' => ($anterior ?? '—').' → '.($nuevo ?? '—')];
            }
        }

        return $lineas;
    }

    /**
     * @param  list<string>  $agregados
     * @param  list<string>  $quitados
     * @return list<LineaDetalle>
     */
    private function cambiosConjunto(array $agregados, array $quitados): array
    {
        $lineas = [];
        if ($agregados !== []) {
            $lineas[] = ['etiqueta' => 'Agregados', 'valor' => implode(', ', $agregados)];
        }
        if ($quitados !== []) {
            $lineas[] = ['etiqueta' => 'Quitados', 'valor' => implode(', ', $quitados)];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return list<LineaDetalle>
     */
    private function cambiosRolesUsuario(array $detalle): array
    {
        $porCuenta = (array) ($detalle['roles_anteriores'] ?? []);
        $antes = [];
        foreach ($porCuenta as $id => $roles) {
            $roles = $this->textos($roles);
            $porCuenta[$id] = implode(', ', $roles) ?: 'Sin rol';
            $antes = [...$antes, ...$roles];
        }
        $antes = array_values(array_unique($antes));
        $despues = $this->textos($detalle['roles_nuevos'] ?? null);
        $lineas = [];

        if ($detalle['roles_incompletos'] ?? false) {
            foreach ($porCuenta as $id => $roles) {
                $lineas[] = ['etiqueta' => "Antes en cuenta $id", 'valor' => $roles];
            }
        } else {
            $lineas[] = ['etiqueta' => 'Antes', 'valor' => implode(', ', $antes) ?: 'Sin rol'];
        }
        $lineas[] = ['etiqueta' => 'Después', 'valor' => implode(', ', $despues) ?: 'Sin rol'];

        return [
            ...$lineas,
            ...$this->cambiosConjunto(
                array_values(array_diff($despues, $antes)),
                array_values(array_diff($antes, $despues)),
            ),
        ];
    }

    /**
     * @param  list<string>  $permisos
     * @return list<string>
     */
    private function nombresDePermisos(array $permisos): array
    {
        return array_map(
            fn (string $codigo): string => CatalogoPermisos::PERMISOS[$codigo]['nombre'] ?? $codigo,
            $permisos,
        );
    }

    /** @return list<string> */
    private function textos(mixed $valores): array
    {
        return is_array($valores) ? array_values(array_filter($valores, 'is_string')) : [];
    }
}
