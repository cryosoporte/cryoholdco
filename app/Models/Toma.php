<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Toma extends Model
{
    protected $table = 'ope_tomas_serologicas';

    protected $primaryKey = 'numero_contrato_base';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'fecha_toma' => 'datetime',
        'fecha_resultado' => 'datetime',
    ];

    /**
     * Servicio que muestra la lista "Tomas del día" del mostrador.
     * Por ahora solo Sangre (S), aunque el seguimiento incluye también Tejido.
     */
    public const SERVICIO_TOMAS_DEL_DIA = 'S';

    public function estado(): string
    {
        return match (true) {
            $this->fecha_resultado !== null => 'recibido',
            $this->fecha_toma !== null => 'tomada',
            default => 'pendiente',
        };
    }

    public function puedeTomarse(): bool
    {
        return $this->estado() === 'pendiente';
    }

    public function marcarTomada(string $laboratorio, string $sede): bool
    {
        $filas = DB::table($this->table)
            ->where('no_cia', $this->no_cia)
            ->where('numero_contrato_base', $this->numero_contrato_base)
            ->where('numero_toma', $this->numero_toma)
            ->whereNull('fecha_toma')
            ->update([
                'fecha_toma' => now(),
                'laboratorio_toma' => $laboratorio,
                'sede_toma' => $sede,
            ]);

        if ($filas === 1) {
            $this->fecha_toma = now();
            $this->laboratorio_toma = $laboratorio;
            $this->sede_toma = $sede;
        }

        return $filas === 1;
    }

    public function dondeSeTomo(): ?string
    {
        if (! $this->laboratorio_toma || ! $this->sede_toma) {
            return null;
        }

        $sede = Sede::nombre($this->laboratorio_toma, $this->sede_toma);
        $alias = Sede::alias($this->laboratorio_toma);

        return trim(implode(' · ', array_filter([$alias, $sede]))) ?: null;
    }

    public static function tomadasHoy(string $laboratorio, string $sede)
    {
        return static::query()
            ->from('ope_tomas_serologicas as t')
            ->join('ven_contratos as c', function ($join) {
                $join->on('c.no_cia', '=', 't.no_cia')
                    ->on('c.numero_base', '=', 't.numero_contrato_base')
                    ->where('c.servicio', '=', self::SERVICIO_TOMAS_DEL_DIA);
            })
            ->leftJoin('ven_clientes as cl', function ($join) {
                $join->on('cl.no_cia', '=', 'c.no_cia')
                    ->on('cl.identificacion', '=', 'c.identificacion1');
            })
            ->select([
                't.numero_contrato_base',
                't.numero_toma',
                't.fecha_toma',
                'cl.nombre as nombre_completo',
            ])
            ->where('t.laboratorio_toma', $laboratorio)
            ->where('t.sede_toma', $sede)
            ->whereRaw('TRUNC(t.fecha_toma) = TRUNC(SYSDATE)')
            ->orderByDesc('t.fecha_toma')
            ->get();
    }
}
