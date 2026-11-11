<?php

namespace App\Http\Controllers\Serologia;

use App\Http\Controllers\Controller;
use App\Http\Requests\Serologia\EnviarRecordatorioRequest;
use App\Http\Requests\Serologia\FiltrarSerologiaRequest;
use App\Mail\RecordatorioSerologia;
use App\Serologia\PlazoSerologia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SerologiaController extends Controller
{
    public function __construct(private readonly PlazoSerologia $plazoSerologia) {}

    public function index(FiltrarSerologiaRequest $request): Response
    {
        $datos = $request->validated();

        $buscar = trim($datos['buscar'] ?? '');
        $estado = $datos['estado'] ?? null;
        $asesor = $datos['asesor'] ?? null;
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        $consulta = DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->leftJoin('LAB_COLECTAS as C3', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C3.NO_CIA')
                    ->on('C1.NUMERO_CONTRATO', '=', 'C3.NUMERO_CONTRATO');
            })
            ->leftJoin('OPE_TOMAS_SEROLOGICAS as C4', function ($join) {
                $join->on('C1.NO_CIA', '=', 'C4.NO_CIA')
                    ->on('C1.NUMERO_BASE', '=', 'C4.NUMERO_CONTRATO_BASE');
            })
            ->leftJoin('USERS as U', function ($join) {
                $join->on('C1.VENDEDOR', '=', 'U.IDENTIFICACION')
                    ->on('C1.NO_CIA', '=', 'U.EMPRESA');
            })
            ->where('C1.NO_CIA', $empresa)
            ->whereIn('C1.SERVICIO', ['S', 'T'])
            ->whereRaw(
                "C1.FECHA_CONTRATO >= TO_DATE(?, 'DD/MM/YYYY')",
                ['01/01/2026']
            )
            ->select([
                'C1.NO_CIA',
                'C1.NUMERO_BASE',
                'C2.IDENTIFICACION',
                'C2.NOMBRE',
                'C3.SEROLOGIA',
                'C4.NUMERO_TOMA',
                'C4.FECHA_TOMA',
                'C4.FECHA_RESULTADO',
                'U.NOMBRES as asesor_nombre',
            ])
            ->selectRaw('CAST(C1.VENDEDOR AS VARCHAR2(100)) AS asesor_dni')
            ->selectRaw("TO_CHAR(C1.FECHA_NACIMIENTO, 'YYYY-MM-DD') AS fecha_nacimiento")
            ->selectRaw("TO_CHAR(C4.FECHA_TOMA, 'YYYY-MM-DD') AS fecha_toma_dia")
            ->selectRaw("TO_CHAR(C4.FECHA_RESULTADO, 'YYYY-MM-DD') AS fecha_resultado_dia")
            ->distinct();

        $asesores = DB::query()
            ->fromSub($consulta, 'opciones_asesores')
            ->select(['asesor_dni as dni', 'asesor_nombre as nombre'])
            ->whereNotNull('asesor_dni')
            ->distinct()
            ->orderBy('nombre')
            ->orderBy('dni')
            ->get();

        $base = DB::query()
            ->fromSub($consulta, 'resultados')
            ->when($buscar !== '', function (Builder $query) use ($buscar): void {
                $texto = '%'.mb_strtoupper($buscar).'%';

                $query->where(function (Builder $filtro) use ($texto): void {
                    $filtro->whereRaw('UPPER(nombre) LIKE ?', [$texto])
                        ->orWhereRaw('UPPER(CAST(identificacion AS VARCHAR2(100))) LIKE ?', [$texto])
                        ->orWhereRaw('UPPER(CAST(numero_base AS VARCHAR2(100))) LIKE ?', [$texto]);
                });
            })
            ->when($asesor !== null, function (Builder $query) use ($asesor): void {
                $query->where('asesor_dni', $asesor);
            });

        $hoy = CarbonImmutable::today('America/Lima');
        $fecha_hace_7_dias = $hoy->subDays(7);

        $conteos = [
            'pendiente' => (clone $base)
                ->whereNull('serologia')
                ->whereNull('fecha_toma')
                ->count(),
            'tomada' => (clone $base)
                ->whereNull('serologia')
                ->whereNotNull('fecha_toma')
                ->count(),
            'recibido' => (clone $base)
                ->where('serologia', 'S')
                ->count(),
            'por_vencer' => (clone $base)
                ->whereNull('serologia')
                ->whereNull('fecha_toma')
                ->whereNotNull('fecha_nacimiento')
                ->whereBetween('fecha_nacimiento', [$fecha_hace_7_dias->toDateString(), $hoy->toDateString()])
                ->count(),
        ];

        $resultados = (clone $base)
            ->when($estado === 'pendiente', function (Builder $query): void {
                $query->whereNull('serologia')
                    ->whereNull('fecha_toma');
            })
            ->when($estado === 'tomada', function (Builder $query): void {
                $query->whereNull('serologia')
                    ->whereNotNull('fecha_toma');
            })
            ->when($estado === 'recibido', function (Builder $query): void {
                $query->where('serologia', 'S');
            })
            ->when($estado === 'por_vencer', function (Builder $query) use ($fecha_hace_7_dias, $hoy): void {
                $query->whereNull('serologia')
                    ->whereNull('fecha_toma')
                    ->whereNotNull('fecha_nacimiento')
                    ->whereBetween('fecha_nacimiento', [$fecha_hace_7_dias->toDateString(), $hoy->toDateString()]);
            })
            ->orderBy('nombre')
            ->orderBy('numero_base')
            ->orderBy('identificacion')
            ->orderBy('serologia')
            ->orderBy('numero_toma')
            ->orderBy('fecha_toma')
            ->orderBy('fecha_resultado')
            ->orderBy('asesor_dni')
            ->orderBy('asesor_nombre')
            ->orderBy('fecha_nacimiento')
            ->paginate(20)
            ->withQueryString();

        $detalles = $this->detallesDeLaPagina($resultados->getCollection(), $empresa);
        $resultados->through(function (object $fila) use ($hoy, $detalles): object {
            $fila->numero_toma = $fila->numero_toma === null ? null : (int) $fila->numero_toma;
            $fila->detalle = $this->formatearDetalle($detalles->get($this->claveDetalle($fila)), $hoy);
            $fila->plazo = $this->plazoSerologia->paraRegistro(
                serologia: $fila->serologia,
                fechaNacimiento: $fila->fecha_nacimiento,
                fechaToma: $fila->fecha_toma_dia,
                fechaResultado: $fila->fecha_resultado_dia,
                hoy: $hoy,
            );
            unset($fila->fecha_toma_dia, $fila->fecha_resultado_dia);

            return $fila;
        });

        return Inertia::render('serologia', [
            'resultados' => $resultados,
            'buscar' => $buscar,
            'estado' => $estado,
            'conteos' => $conteos,
            'asesor' => $asesor,
            'asesores' => $asesores,
        ]);
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return Collection<string, object>
     */
    private function detallesDeLaPagina(Collection $filas, string $empresa): Collection
    {
        $contratos = $filas->pluck('numero_base')->filter()->unique()->values();
        if ($contratos->isEmpty()) {
            return collect();
        }

        return DB::table('VEN_CONTRATOS as C')
            ->join('VEN_CLIENTES as CL', function (JoinClause $join): void {
                $join->on('CL.NO_CIA', '=', 'C.NO_CIA')
                    ->on('CL.IDENTIFICACION', '=', 'C.IDENTIFICACION1');
            })
            ->leftJoin('VEN_PROSPECTOS as P', function (JoinClause $join): void {
                $join->on('P.NO_CIA', '=', 'C.NO_CIA')
                    ->on('P.NUMERO_PROSPECTO', '=', 'C.NUMERO_PROSPECTO');
            })
            ->leftJoin('LAB_COLECTAS as L', function (JoinClause $join): void {
                $join->on('L.NO_CIA', '=', 'C.NO_CIA')
                    ->on('L.NUMERO_CONTRATO', '=', 'C.NUMERO_CONTRATO');
            })
            ->leftJoin('OPE_TOMAS_SEROLOGICAS as T', function (JoinClause $join): void {
                $join->on('T.NO_CIA', '=', 'C.NO_CIA')
                    ->on('T.NUMERO_CONTRATO_BASE', '=', 'C.NUMERO_BASE');
            })
            ->leftJoin('LAB_LABORATORIOS_SEDES as S', function (JoinClause $join): void {
                $join->on('S.NO_CIA', '=', 'T.NO_CIA')
                    ->on('S.IDENTIFICACION', '=', 'T.LABORATORIO_TOMA')
                    ->on('S.CODIGO_SEDE', '=', 'T.SEDE_TOMA');
            })
            ->where('C.NO_CIA', $empresa)
            ->whereIn('C.NUMERO_BASE', $contratos)
            ->whereIn('C.SERVICIO', ['S', 'T'])
            ->where('C.FECHA_CONTRATO', '>=', CarbonImmutable::create(2026, 1, 1, 0, 0, 0, 'America/Lima'))
            ->select([
                'C.NUMERO_BASE',
                'C.IDENTIFICACION1 as identificacion',
                'T.NUMERO_TOMA',
                'CL.CELULAR_CONTACTO as celular',
            ])
            ->selectRaw('MAX(P.FECHA_PARTO) AS fpp_prospecto')
            ->selectRaw('MAX(L.FECHA_NACIMIENTO) AS parto_colecta')
            ->selectRaw('MAX(CASE WHEN C.CONFIRM_NAC = 1 THEN C.FECHA_NACIMIENTO END) AS parto_confirmado')
            ->selectRaw('MAX(C.FECHA_NACIMIENTO) AS fecha_contrato_nacimiento')
            ->selectRaw('MAX(S.DESCRIPCION_SEDE) AS sede_toma')
            ->groupBy('C.NUMERO_BASE', 'C.IDENTIFICACION1', 'T.NUMERO_TOMA', 'CL.CELULAR_CONTACTO')
            ->get()
            ->keyBy($this->claveDetalle(...));
    }

    private function claveDetalle(object $fila): string
    {
        return serialize([$fila->numero_base, $fila->identificacion, (int) $fila->numero_toma]);
    }

    /**
     * @return array{celular: ?string, fpp: ?string, parto_real: ?string, sede_toma: ?string, plazo: array{situacion: string, fecha_apertura: ?string, fecha_limite: ?string, dias_restantes: ?int, porcentaje_transcurrido: ?int, texto: string}}
     */
    private function formatearDetalle(?object $cliente, CarbonImmutable $hoy): array
    {
        $partoReal = $cliente?->parto_colecta ?? $cliente?->parto_confirmado;
        $fpp = $cliente?->fpp_prospecto ?? ($partoReal === null ? $cliente?->fecha_contrato_nacimiento : null);
        $partoReal = $partoReal === null ? null : substr($partoReal, 0, 10);
        $fpp = $fpp === null ? null : substr($fpp, 0, 10);

        return [
            'celular' => trim($cliente?->celular ?? '') ?: null,
            'fpp' => $fpp,
            'parto_real' => $partoReal,
            'sede_toma' => trim($cliente?->sede_toma ?? '') ?: null,
            'plazo' => $this->plazoSerologia->calcular(
                $partoReal ?? $fpp,
                $hoy,
                diasAntes: 10,
                diasDespues: 30,
            ),
        ];
    }

    public function enviarRecordatorio(EnviarRecordatorioRequest $request): JsonResponse
    {
        $datos = $request->validated();

        abort_if(blank($request->user()->empresa), 403);

        $cliente = DB::table('VEN_CONTRATOS as C1')
            ->join('VEN_CLIENTES as C2', function ($join): void {
                $join->on('C1.NO_CIA', '=', 'C2.NO_CIA')
                    ->on('C1.IDENTIFICACION1', '=', 'C2.IDENTIFICACION');
            })
            ->where('C1.NO_CIA', $request->user()->empresa)
            ->where('C1.NUMERO_BASE', $datos['numero_base'])
            ->where('C2.IDENTIFICACION', $datos['identificacion'])
            ->whereIn('C1.SERVICIO', ['S', 'T'])
            ->where('C1.FECHA_CONTRATO', '>=', CarbonImmutable::create(2026, 1, 1, 0, 0, 0, 'America/Lima'))
            ->select(['C2.NOMBRE', 'C2.MAIL_CONTACTO'])
            ->first();

        abort_if($cliente === null, 404);

        $tieneMuestra = DB::table('OPE_TOMAS_SEROLOGICAS')
            ->where('NO_CIA', $request->user()->empresa)
            ->where('NUMERO_CONTRATO_BASE', $datos['numero_base'])
            ->where(function (Builder $query): void {
                $query->whereNotNull('FECHA_TOMA')->orWhereNotNull('FECHA_RESULTADO');
            })
            ->exists();

        $tieneSerologia = DB::table('LAB_COLECTAS as L')
            ->join('VEN_CONTRATOS as C', function ($join): void {
                $join->on('C.NO_CIA', '=', 'L.NO_CIA')
                    ->on('C.NUMERO_CONTRATO', '=', 'L.NUMERO_CONTRATO');
            })
            ->where('C.NO_CIA', $request->user()->empresa)
            ->where('C.NUMERO_BASE', $datos['numero_base'])
            ->whereNotNull('L.SEROLOGIA')
            ->exists();

        if ($tieneMuestra || $tieneSerologia) {
            throw ValidationException::withMessages([
                'recordatorio' => 'La muestra ya fue tomada o recibida. Actualiza los resultados.',
            ]);
        }

        $correo = trim($cliente->mail_contacto ?? '');

        if (Validator::make(['correo' => $correo], ['correo' => ['required', 'email']])->fails()) {
            throw ValidationException::withMessages([
                'recordatorio' => 'El cliente no tiene un correo de contacto válido registrado.',
            ]);
        }

        try {
            Mail::to($correo)->send(new RecordatorioSerologia($cliente->nombre));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo enviar el recordatorio. Intenta de nuevo más tarde.',
            ], 502);
        }

        return response()->json(['message' => 'Recordatorio enviado correctamente.']);
    }
}
