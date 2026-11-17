<?php

namespace App\Models;

use Database\Factories\FuenteCargaFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property int $id
 * @property string $codigo_fuente
 * @property string $estado
 * @property string $archivo
 * @property string|null $ruta_archivo
 * @property string $cargado_por
 * @property string $usuario_id
 * @property int|null $filas
 * @property string|null $mensaje
 */
class FuenteCarga extends Model
{
    /** @use HasFactory<FuenteCargaFactory> */
    use HasFactory;

    public const PENDIENTE = 'P';

    public const EN_PROCESO = 'E';

    public const COMPLETADA = 'C';

    public const FALLIDA = 'F';

    public const ACTIVAS = [self::PENDIENTE, self::EN_PROCESO];

    public const ERROR_IMPORTACION = 'No se pudo completar la importación. Intenta nuevamente.';

    public $timestamps = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'fuentes_cargas');
    }

    protected function casts(): array
    {
        return ['filas' => 'integer'];
    }

    /**
     * @param  list<string>  $codigos
     * @return Collection<string, self>
     */
    public static function ultimasPorFuente(array $codigos): Collection
    {
        $ultimosIds = static::query()->selectRaw('MAX(id)')
            ->whereIn('codigo_fuente', $codigos)
            ->groupBy('codigo_fuente');

        return static::query()->whereIn('id', $ultimosIds)->get()->keyBy('codigo_fuente');
    }

    /**
     * @return array{estado: string, filas: int|null, mensaje: string|null}
     */
    public function resultado(string $usuarioId): array
    {
        return [
            'estado' => $this->estado,
            'filas' => $this->filas,
            'mensaje' => $this->estado === self::FALLIDA
                ? ($this->usuario_id === $usuarioId ? $this->mensaje : self::ERROR_IMPORTACION)
                : null,
        ];
    }

    public function iniciar(): bool
    {
        return static::query()->whereKey($this->id)->where('estado', self::PENDIENTE)->update([
            'estado' => self::EN_PROCESO,
            'fecha_inicio' => now(),
        ]) === 1;
    }

    public function completar(int $filas): void
    {
        $actualizadas = static::query()->whereKey($this->id)->where('estado', self::EN_PROCESO)->update([
            'estado' => self::COMPLETADA,
            'filas' => $filas,
            'fecha_fin' => now(),
        ]);

        if ($actualizadas !== 1) {
            throw new RuntimeException('La carga dejó de estar activa antes de publicar los datos.');
        }
    }

    public function fallar(string $estadoAnterior, string $mensaje): bool
    {
        return static::query()->whereKey($this->id)->where('estado', $estadoAnterior)->update([
            'estado' => self::FALLIDA,
            'mensaje' => mb_substr($mensaje, 0, 1000),
            'fecha_fin' => now(),
        ]) === 1;
    }

    public function eliminarTemporal(): void
    {
        if ($this->ruta_archivo !== null && Storage::disk('local')->delete($this->ruta_archivo)) {
            static::query()->whereKey($this->id)->where('ruta_archivo', $this->ruta_archivo)
                ->update(['ruta_archivo' => null]);
        }
    }
}
