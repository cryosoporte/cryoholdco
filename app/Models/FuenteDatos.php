<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Tabla ICTC.(PRUEBA_)FUENTES_DATOS: una fila por fuente de datos, con su
 * nombre visible y los datos de la última carga exitosa.
 *
 * Las filas las crea el DBA. La aplicación solo actualiza la de una fuente
 * al terminar de cargarla (registrarCarga).
 *
 * FECHA_ACTUALIZACION se guarda en hora de Lima (reportes.zona_horaria),
 * aunque la aplicación trabaje en UTC.
 *
 * @property string $codigo
 * @property string $nombre
 * @property CarbonImmutable|null $fecha_actualizacion
 * @property string|null $actualizado_por
 * @property int|null $filas
 * @property string|null $archivo
 */
class FuenteDatos extends Model
{
    protected $primaryKey = 'codigo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'fuentes_datos');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filas' => 'integer',
        ];
    }

    /**
     * La fecha se lee en hora de Lima, que es como se guarda.
     *
     * @return Attribute<CarbonImmutable|null, never>
     */
    protected function fechaActualizacion(): Attribute
    {
        return Attribute::get(fn (?string $valor): ?CarbonImmutable => $valor === null
            ? null
            : CarbonImmutable::parse($valor, config('reportes.zona_horaria')));
    }

    /**
     * Lo que las pantallas muestran de la fuente. La fecha va en ISO 8601 con
     * la zona de Lima (2026-11-17T10:32:00-05:00).
     *
     * @return array{codigo: string, nombre: string, fecha_actualizacion: string|null, actualizado_por: string|null, filas: int|null, archivo: string|null}
     */
    public function datosActualizacion(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'fecha_actualizacion' => $this->fecha_actualizacion?->toIso8601String(),
            'actualizado_por' => $this->actualizado_por,
            'filas' => $this->filas,
            'archivo' => $this->archivo,
        ];
    }

    public static function porCodigo(string $codigo): ?self
    {
        return static::query()->find(strtoupper($codigo));
    }

    /**
     * Registra la carga exitosa de una fuente. Se llama dentro de la misma
     * transacción que reemplaza sus datos, para que la fecha nunca cambie si
     * la carga falla.
     *
     * @throws RuntimeException si la fuente no tiene fila en la tabla maestra
     */
    public static function registrarCarga(string $codigo, string $usuario, int $filas, string $archivo): void
    {
        $actualizadas = static::query()->whereKey(strtoupper($codigo))->update([
            'fecha_actualizacion' => CarbonImmutable::now(config('reportes.zona_horaria'))->format('Y-m-d H:i:s'),
            'actualizado_por' => mb_substr($usuario, 0, 100),
            'filas' => $filas,
            'archivo' => mb_substr($archivo, 0, 255),
        ]);

        if ($actualizadas === 0) {
            throw new RuntimeException("La fuente {$codigo} no está registrada en ".(new static)->getTable().'. El DBA debe crear su fila.');
        }
    }
}
