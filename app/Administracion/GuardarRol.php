<?php

namespace App\Administracion;

use App\Models\EventoSeguridad;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarRol
{
    public function ejecutar(User $autor, string $nombre, ?string $descripcion, ?Rol $rol = null): Rol
    {
        return (new Rol)->getConnection()->transaction(function () use ($autor, $nombre, $descripcion, $rol): Rol {
            // La fila de sistema serializa las altas y los cambios de nombre entre sesiones.
            $administrador = Rol::bloquear(Rol::ADMINISTRADOR);
            $actual = match ($rol?->codigo) {
                null => new Rol,
                Rol::ADMINISTRADOR => $administrador,
                default => Rol::bloquear($rol->codigo),
            };

            $roles = Rol::query()->get(['codigo', 'nombre']);
            $nombreNormalizado = mb_strtolower($nombre);
            if ($roles->contains(fn (Rol $otro): bool => $otro->codigo !== $actual->getKey() && mb_strtolower($otro->nombre) === $nombreNormalizado)) {
                throw ValidationException::withMessages(['nombre' => 'Ya existe un rol con ese nombre.']);
            }

            $antes = $actual->exists ? $actual->only(['nombre', 'descripcion']) : null;
            $despues = ['nombre' => $nombre, 'descripcion' => $descripcion];

            if ($antes === $despues) {
                return $actual;
            }

            if (! $actual->exists) {
                $actual->codigo = $this->codigoDisponible($nombre, $roles->pluck('codigo')->all());
            }
            $actual->forceFill($despues)->save();

            EventoSeguridad::registrar(
                $antes === null ? EventoSeguridad::ROL_CREADO : EventoSeguridad::ROL_EDITADO,
                $autor,
                compact('antes', 'despues'),
                $actual->codigo,
            );

            return $actual;
        });
    }

    /** @param list<string> $codigos */
    private function codigoDisponible(string $nombre, array $codigos): string
    {
        $base = trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper(Str::ascii($nombre, 'es'))), '_');
        if ($base === '') {
            throw ValidationException::withMessages(['nombre' => 'Escribe un nombre con letras o números que permitan generar el código del rol.']);
        }

        $base = substr($base, 0, 30);
        $codigo = $base;
        $usados = array_fill_keys($codigos, true);
        for ($numero = 2; isset($usados[$codigo]); $numero++) {
            $sufijo = '_'.$numero;
            $codigo = substr($base, 0, 30 - strlen($sufijo)).$sufijo;
        }

        return $codigo;
    }
}
