<?php

namespace App\Reportes\Comisiones;

use App\Models\EnvioLiquidacion;
use App\Models\EventoLiquidacion;
use App\Models\LiquidacionComision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class LiquidacionesComisiones
{
    public function activa(string $periodo): ?LiquidacionComision
    {
        return LiquidacionComision::query()->where('periodo', $periodo)
            ->where('estado', '<>', LiquidacionComision::RECHAZADA)->first();
    }

    public function ultimaRechazada(string $periodo): ?LiquidacionComision
    {
        return LiquidacionComision::query()->where('periodo', $periodo)
            ->where('estado', LiquidacionComision::RECHAZADA)->latest('id')->first();
    }

    /** @return Collection<int, EventoLiquidacion> */
    public function eventos(int $id): Collection
    {
        return EventoLiquidacion::query()->where('liq_id', $id)->orderBy('id')->get();
    }

    public function fueProcesadaPor(int $id, User $usuario): bool
    {
        $autorId = EventoLiquidacion::query()
            ->where('liq_id', $id)
            ->where('accion', 'PROCESO')
            ->latest('id')
            ->value('user_id');

        if ($autorId === null) {
            return false;
        }

        if ((string) $autorId === (string) $usuario->getKey()) {
            return true;
        }

        $autor = User::query()->find($autorId, ['id', 'identificacion', 'email']);

        return $autor?->clavePersona() === $usuario->clavePersona();
    }

    /** @return Collection<int, EnvioLiquidacion> */
    public function envios(int $id): Collection
    {
        return EnvioLiquidacion::query()->where('liq_id', $id)->orderBy('user_id')->get();
    }

    /** @return array<string, int> período mensual => id de la liquidación aprobada o enviada */
    public function mesesAprobados(PeriodoComision $trimestre): array
    {
        return LiquidacionComision::query()->where('tipo', 'M')->whereIn('periodo', $trimestre->meses())
            ->whereIn('estado', [LiquidacionComision::APROBADA, LiquidacionComision::ENVIADA])
            ->orderBy('periodo')->pluck('id', 'periodo')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return list<array{id: string, periodo: string, tipo_periodo: string, desde: string, hasta: string, estado: string, total: ?float, colaboradores: ?int, colaboradores_con_comision: ?int, actualizada_en: ?string}>
     */
    public function historial(DatosRealesComisiones $datos): array
    {
        $ultimosIds = LiquidacionComision::query()->selectRaw('max(id)')->groupBy('periodo');
        $guardadas = LiquidacionComision::query()->whereIn('id', $ultimosIds)->get()->keyBy('periodo');
        $actualizaciones = EventoLiquidacion::query()->whereIn('liq_id', $ultimosIds)
            ->selectRaw('liq_id, max(fecha) as ultima_fecha')->groupBy('liq_id')->pluck('ultima_fecha', 'liq_id');
        $resumenes = [];
        foreach ($datos->periodosDisponibles() as $opcion) {
            $id = $opcion['id'];
            $periodo = $datos->periodo($id);
            $fila = $guardadas->get($id);
            $estado = $fila?->estadoPantalla() ?? 'borrador';
            $foto = $fila && $estado !== 'rechazada' ? $fila->foto() : null;
            $fecha = $fila ? $actualizaciones->get($fila->id, $fila->fecha_creacion) : null;
            $resumenes[] = [
                'id' => $id, 'periodo' => $opcion['etiqueta'],
                'tipo_periodo' => $periodo->periodicidad === Periodicidad::Mensual ? 'mensual' : 'trimestral',
                'desde' => $periodo->desde->toDateString(), 'hasta' => $periodo->hasta->toDateString(),
                'estado' => $estado, 'total' => $foto['total'] ?? null,
                'colaboradores' => $foto['colaboradores'] ?? null,
                'colaboradores_con_comision' => $foto['colaboradores_con_comision'] ?? null,
                'actualizada_en' => $fecha === null ? null : CarbonImmutable::parse($fecha, config('reportes.zona_horaria'))->toIso8601String(),
            ];
        }

        return $resumenes;
    }
}
