<?php

namespace App\Models;

use Database\Factories\UsuarioLaboratorioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tabla ICTC.LAB_LABORATORIOS_USUARIOS: el acceso de un laboratorio al mostrador de Serología.
 *
 * Hay un único acceso por laboratorio (RUC), no por sede ni por compañía. El laboratorio
 * entra con su RUC y la contraseña que le asigna Criocord desde Administración.
 */
class UsuarioLaboratorio extends Authenticatable
{
    /** @use HasFactory<UsuarioLaboratorioFactory> */
    use HasFactory;

    protected $table = 'lab_laboratorios_usuarios';

    public const ESTADO_ACTIVO = 'A';

    public const ESTADO_INACTIVO = 'I';

    /** La tabla no guarda la opción «recordarme». */
    protected $rememberTokenName = '';

    protected $fillable = ['identificacion', 'password', 'estado'];

    protected $hidden = ['password'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ESTADO_ACTIVO;
    }

    /**
     * Si el laboratorio puede entrar al mostrador: el acceso está activo y
     * le queda al menos una sede activa.
     */
    public function puedeIngresar(): bool
    {
        return $this->estaActivo() && Sede::deLaboratorio($this->identificacion)->isNotEmpty();
    }
}
