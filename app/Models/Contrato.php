<?php

namespace App\Models;

use App\Serologia\AlcanceSerologia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Contrato extends Model
{
    protected $table = 'ven_contratos';

    protected $primaryKey = 'numero_contrato';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'fecha_contrato' => 'date',
        'fecha_nacimiento' => 'date',
        'fecha_muestra' => 'date',
        'fecha_resultado' => 'date',
    ];

    public const DOC_DNI = 'DI';

    /** Memoria de tomaActiva(): la toma y si ya se fue a buscar. */
    protected ?Toma $tomaEnMemoria = null;

    protected bool $tomaBuscada = false;

    public static function buscarPorDni(string $dni): ?self
    {
        return static::query()
            ->from('ven_contratos as c')
            ->leftJoin('ven_clientes as cl', function ($join) {
                $join->on('cl.no_cia', '=', 'c.no_cia')
                    ->on('cl.identificacion', '=', 'c.identificacion1');
            })
            ->select([
                'c.no_cia',
                // El cliente ve la base y es lo que lleva el QR; numero_base
                // va además con su nombre, que es por donde busca la toma.
                'c.numero_base as numero_contrato',
                'c.numero_base',
                'c.identificacion1',
                'c.fecha_contrato',
                'c.fecha_nacimiento',
                'c.contrato_lp as ciudad_laboratorio',
                'cl.nombre as nombre_completo',
                'cl.nombre_corto as primer_nombre',
            ])
            ->where('c.codigo_documento1', self::DOC_DNI)
            ->where('c.identificacion1', $dni)
            ->whereIn('c.servicio', AlcanceSerologia::SERVICIOS)
            ->orderByDesc('c.fecha_contrato')
            ->first();
    }

    public static function buscarPorContratoDNI(string $texto): ?self
    {
        $texto = trim($texto);
        $digitos = preg_replace('/\D/', '', $texto);

        $query = static::query()
            ->from('ven_contratos as c')
            ->leftJoin('ven_clientes as cl', function ($join) {
                $join->on('cl.no_cia', '=', 'c.no_cia')
                    ->on('cl.identificacion', '=', 'c.identificacion1');
            })
            ->select([
                'c.no_cia',
                'c.numero_base',
                'c.numero_contrato',
                'c.servicio',
                'c.identificacion1',
                'c.fecha_contrato',
                'cl.nombre as nombre_completo',
                'cl.nombre_corto as primer_nombre',
            ])
            ->whereIn('c.servicio', AlcanceSerologia::SERVICIOS);

        if ($digitos === $texto && strlen($digitos) === 8) {
            $query->where('c.codigo_documento1', self::DOC_DNI)
                ->where('c.identificacion1', $digitos);
        } else {
            $numero = strtoupper($texto);

            $query->where(function ($q) use ($numero) {
                $q->where('c.numero_base', $numero)
                    ->orWhere('c.numero_contrato', $numero);
            });
        }

        return $query->orderByDesc('c.fecha_contrato')->first();
    }

    /**
     * La toma sobre la que hay que actuar: la primera pendiente y, si ya no
     * queda ninguna, la última hecha.
     *
     * Se guarda en memoria porque la ficha del cliente la pide varias veces
     * (el estado, la línea de tiempo, la sede) y sería la misma consulta.
     */
    public function tomaActiva(): ?Toma
    {
        if (! $this->tomaBuscada) {
            $tomas = Toma::query()
                ->where('no_cia', $this->no_cia)
                ->where('numero_contrato_base', $this->numero_base)
                ->orderBy('numero_toma')
                ->get();

            $this->tomaEnMemoria = $tomas->firstWhere('fecha_toma', null) ?? $tomas->last();
            $this->tomaBuscada = true;
        }

        return $this->tomaEnMemoria;
    }

    public function sedes()
    {
        $query = DB::table('lab_laboratorios_sedes as l')
            ->join('cxp_proveedores as cp', function ($join) {
                $join->on('cp.no_cia', '=', 'l.no_cia')
                    ->on('cp.identificacion', '=', 'l.identificacion');
            })
            ->select('cp.alias', 'l.codigo_sede', 'l.descripcion_sede', 'l.direccion_sede', 'l.ciudad')
            ->where('l.no_cia', $this->no_cia)
            ->where('l.estado', Sede::ESTADO_ACTIVA);

        $ciudad = $this->ciudad_laboratorio;

        $existeCiudad = (clone $query)
            ->where('ciudad', $ciudad)
            ->exists();

        if ($existeCiudad) {
            $query->where('ciudad', $ciudad);
        }

        return $query
            ->orderBy('codigo_sede')
            ->get()
            ->unique('descripcion_sede')
            ->values();
    }

    /**
     * El estado sale de OPE_TOMAS_SEROLOGICAS, no de este contrato:
     *
     *   pendiente ... la toma existe y FECHA_TOMA está en NULL
     *   tomada ...... hay FECHA_TOMA y todavía no FECHA_RESULTADO
     *   recibido .... hay FECHA_RESULTADO
     *
     * Y uno más, que no es un estado del proceso sino falta de información:
     *
     *   sin_registro. el contrato no tiene fila en la tabla de tomas
     *
     * No se puede tratar como "pendiente": a una clienta que ya dio su
     * muestra le estaríamos diciendo que vaya otra vez al laboratorio.
     */
    public function estado(): string
    {
        return $this->tomaActiva()?->estado() ?? 'sin_registro';
    }
}
