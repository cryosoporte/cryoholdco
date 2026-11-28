<?php

namespace App\Informes;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * El listado de Informes de una empresa: un registro por contrato base (sin repetidos), con la liberación
 * de sus servicios, su familia, su envío y la indicación de si laboratorio tiene carpeta de documentos.
 * Se arma sobre FilasInformes con las mismas reglas que las tarjetas (ClasificadorInformes).
 */
class ListadoInformes
{
    public const POR_PAGINA = 20;

    public function __construct(
        private readonly ContratosPorBase $contratos,
        private readonly CarpetaDocumentos $carpetas,
        private readonly FilasInformes $filas,
        private readonly ClasificadorInformes $clasificador,
    ) {}

    /**
     * @return array{resultados: LengthAwarePaginator, unidad_disponible: bool}
     */
    public function consultar(string $empresa, FiltrosInformes $filtros): array
    {
        $hoy = $this->clasificador->hoy();
        $activos = array_filter($this->clasificador->filtrosActivos($filtros), fn (?string $valor): bool => $valor !== null);
        $coincidentes = [];

        foreach ($this->filas->de($empresa) as $fila) {
            if (! $this->clasificador->coincideConLaBusqueda($fila, $filtros->buscar)) {
                continue;
            }

            $valores = $this->clasificador->valores($fila, $hoy);

            foreach ($activos as $dimension => $activo) {
                if ($valores[$dimension] !== $activo) {
                    continue 2;
                }
            }

            $coincidentes[] = $fila;
        }

        usort($coincidentes, $this->masRecientePrimero(...));

        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $enPagina = array_slice($coincidentes, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);

        $existen = $this->carpetas->existen($empresa, array_values(array_unique(array_column($enPagina, 'numero_base'))));

        $resultados = new LengthAwarePaginator(
            array_map(fn (array $fila): array => $this->presentar($fila, $existen), $enPagina),
            count($coincidentes),
            self::POR_PAGINA,
            $pagina,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()],
        );

        return ['resultados' => $resultados, 'unidad_disponible' => $existen !== null];
    }

    /**
     * Un contrato base del listado de la empresa con sus resultados, o null si no está en el listado.
     * Lee Oracle en el momento (sin caché): lo usa el envío, que necesita el resultado vigente.
     *
     * @return array<string, mixed>|null
     */
    public function contrato(string $empresa, string $numeroBase): ?array
    {
        $fila = DB::query()->fromSub($this->contratos->consulta($empresa, $numeroBase), 'resultados')->first();

        return $fila === null ? null : $this->presentar((array) $fila, null);
    }

    /**
     * Colecta más reciente primero (las que no tienen fecha, al final) y luego por contrato base.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function masRecientePrimero(array $a, array $b): int
    {
        if ($a['fecha_colecta'] !== $b['fecha_colecta']) {
            return match (true) {
                $a['fecha_colecta'] === null => 1,
                $b['fecha_colecta'] === null => -1,
                default => strcmp((string) $b['fecha_colecta'], (string) $a['fecha_colecta']),
            };
        }

        return strcmp((string) $a['numero_base'], (string) $b['numero_base']);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, bool>|null  $existen
     * @return array<string, mixed>
     */
    private function presentar(array $fila, ?array $existen): array
    {
        $tieneSangre = (int) $fila['tiene_sangre'] === 1;
        $tieneTejido = (int) $fila['tiene_tejido'] === 1;
        $liberaciones = array_values(array_filter([
            $tieneSangre ? $fila['liberacion_sangre'] : false,
            $tieneTejido ? $fila['liberacion_tejido'] : false,
        ], fn ($liberacion): bool => $liberacion !== false));

        return [
            'numero_base' => $fila['numero_base'],
            'estado' => LiberacionContrato::estado($liberaciones),
            'sangre' => LiberacionContrato::servicio($tieneSangre, (int) $fila['sangre_en_info'] === 1, $fila['liberacion_sangre']),
            'tejido' => LiberacionContrato::servicio($tieneTejido, (int) $fila['tejido_en_info'] === 1, $fila['liberacion_tejido']),
            'familia' => collect([$fila['apellido_titular2'], $fila['apellido_titular1']])
                ->map(fn (?string $apellido): string => trim((string) $apellido))
                ->filter()
                ->unique()
                ->implode(' ') ?: null,
            'fecha_colecta' => $fila['fecha_colecta'] === null ? null : substr((string) $fila['fecha_colecta'], 0, 10),
            'enviado' => (int) $fila['enviado'] === AlcanceInformes::FLAG_ENVIADO,
            'fecha_envio' => $fila['fecha_envio'] === null ? null : substr((string) $fila['fecha_envio'], 0, 10),
            'usuario_envio' => $fila['usuario_envio'],
            'identificacion' => $fila['identificacion'],
            'nombre' => $fila['nombre'],
            'mail_contacto' => $fila['mail_contacto'],
            'tiene_carpeta' => $existen === null ? null : ($existen[$fila['numero_base']] ?? false),
        ];
    }
}
