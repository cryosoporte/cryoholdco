<?php

namespace App\Models;

use App\Reportes\Comisiones\DatosRealesComisiones;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;

/** @phpstan-import-type Liquidacion from DatosRealesComisiones */
class LiquidacionComision extends Model
{
    public const PROCESADA = 'PROCESADA';

    public const RECHAZADA = 'RECHAZADA';

    public const APROBADA = 'APROBADA';

    public const ENVIADA = 'ENVIADA';

    public $timestamps = false;

    public function getTable(): string
    {
        return strtolower(config('reportes.prefijo_tablas').'com_liq');
    }

    /** @param Liquidacion $foto */
    public static function guardarFoto(array $foto, ?int $cargaId, CarbonImmutable $fecha): self
    {
        $tabla = (new self)->getTable();
        $conexion = DB::connection();
        $oracle = $conexion->getDriverName() === 'oracle';
        $json = json_encode($foto, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $id = self::query()->insertGetId([
            'periodo' => $foto['id'],
            'tipo' => $foto['tipo_periodo'] === 'mensual' ? 'M' : 'T',
            'estado' => self::PROCESADA,
            'fecha_desde' => $foto['desde'].' 00:00:00',
            'fecha_hasta' => $foto['hasta'].' 00:00:00',
            'total' => $foto['total'],
            'carga_id' => $cargaId,
            'fecha_creacion' => $fecha->toDateTimeString(),
            'desglose' => $oracle ? DB::raw('EMPTY_CLOB()') : $json,
        ]);

        if ($oracle) {
            $tablaSql = $conexion->getQueryGrammar()->wrapTable($tabla);
            $sentencia = $conexion->getPdo()->prepare("update {$tablaSql} set DESGLOSE = :foto where ID = :id");
            $sentencia->bindParam(':foto', $json, SQLT_CLOB);
            $sentencia->bindValue(':id', $id, PDO::PARAM_INT);
            $sentencia->execute();
        }

        return self::query()->findOrFail($id);
    }

    public function estadoPantalla(): string
    {
        return match ($this->estado) {
            self::PROCESADA => 'enviada',
            self::APROBADA => 'aprobada',
            self::ENVIADA => 'enviada_personal',
            self::RECHAZADA => 'rechazada',
        };
    }

    /** @return Liquidacion */
    public function foto(): array
    {
        $json = $this->desglose;

        return json_decode($json instanceof \OCILob ? $json->load() : $json, true, flags: JSON_THROW_ON_ERROR);
    }

    /** Dentro de la transacción de la acción, bloquea la fila antes de validar su estado. */
    public static function bloquearEnEstado(int $id, string $estado): self
    {
        $liquidacion = self::query()->whereKey($id)->lockForUpdate()->get()->first();
        if ($liquidacion === null || $liquidacion->estado !== $estado) {
            throw self::accionNoDisponible();
        }

        return $liquidacion;
    }

    public static function accionNoDisponible(): ValidationException
    {
        return ValidationException::withMessages(['accion' => 'Esta acción no está disponible en el estado actual.']);
    }
}
