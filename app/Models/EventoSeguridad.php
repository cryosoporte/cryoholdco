<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EventoSeguridad extends Model
{
    public const ROL_CREADO = 'ROL_CREADO';

    public const ROL_EDITADO = 'ROL_EDITADO';

    public const ROL_ELIMINADO = 'ROL_ELIMINADO';

    public const PERMISOS_ROL = 'PERMISOS_ROL';

    public const ROLES_USUARIO = 'ROLES_USUARIO';

    public $timestamps = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'seguridad_evento');
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @param  list<int>  $usuarios
     */
    public static function registrar(string $accion, User $autor, array $detalle, ?string $rol = null, array $usuarios = []): void
    {
        $detalleJson = json_encode($detalle, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ids = implode(',', $usuarios);

        if (strlen($detalleJson) > 4000 || strlen($ids) > 200) {
            $campo = $accion === self::ROLES_USUARIO ? 'roles' : 'rol';
            throw ValidationException::withMessages([$campo => 'El cambio supera el tamaño permitido para el historial.']);
        }

        self::query()->insert([
            'accion' => $accion,
            'rol' => $rol,
            'usuarios' => $ids === '' ? null : $ids,
            'detalle' => $detalleJson,
            'autor_id' => $autor->getKey(),
            'autor_nombre' => $autor->name,
            'autor_empresa' => $autor->empresa,
            'fecha' => CarbonImmutable::now(config('reportes.zona_horaria'))->toDateTimeString(),
        ]);
    }
}
