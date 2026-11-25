<?php

namespace App\Models;

use App\Administracion\CatalogoPermisos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    public const ADMINISTRADOR = 'ADMINISTRADOR';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'rol');
    }

    /** @return HasMany<RolPermiso, $this> */
    public function permisos(): HasMany
    {
        return $this->hasMany(RolPermiso::class, 'rol', 'codigo');
    }

    /** @return HasMany<UsuarioRol, $this> */
    public function asignaciones(): HasMany
    {
        return $this->hasMany(UsuarioRol::class, 'rol', 'codigo');
    }

    public function esSistema(): bool
    {
        return $this->codigo === self::ADMINISTRADOR || $this->es_sistema === 'S';
    }

    /** @return list<string> */
    public function permisosFijos(): array
    {
        return $this->codigo === self::ADMINISTRADOR ? CatalogoPermisos::codigosAdministracion() : [];
    }

    /**
     * Descarta los permisos exclusivos de ADMINISTRADOR que tenga guardados otro rol.
     *
     * @param  list<string>  $permisos
     * @return list<string>
     */
    public function permisosPermitidos(array $permisos): array
    {
        if ($this->codigo === self::ADMINISTRADOR) {
            return $permisos;
        }

        return array_values(array_diff($permisos, CatalogoPermisos::EXCLUSIVOS_ADMINISTRADOR));
    }

    /** @return list<string> */
    public function permisosExclusivosAjenos(): array
    {
        return $this->codigo === self::ADMINISTRADOR ? [] : CatalogoPermisos::EXCLUSIVOS_ADMINISTRADOR;
    }

    public function impedimentoParaEliminar(): ?string
    {
        if ($this->esSistema()) {
            return 'No puedes eliminar un rol de sistema.';
        }

        $tieneAsignaciones = $this->relationLoaded('asignaciones')
            ? $this->asignaciones->isNotEmpty()
            : $this->asignaciones()->exists();

        return $tieneAsignaciones ? 'Quita el rol de las personas asignadas antes de eliminarlo.' : null;
    }

    public static function bloquear(string $codigo): self
    {
        $rol = self::query()->whereKey($codigo)->lockForUpdate()->get()->first();

        if ($rol === null) {
            throw (new ModelNotFoundException)->setModel(self::class, [$codigo]);
        }

        return $rol;
    }
}
