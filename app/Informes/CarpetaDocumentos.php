<?php

namespace App\Informes;

use Illuminate\Validation\ValidationException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Ubica la carpeta de documentos que laboratorio guarda por contrato y expone sus archivos.
 *
 * La carpeta se busca solo dentro de la raíz de la empresa y se reconoce porque su nombre
 * empieza por el número de contrato base ("15280-ST-MUÑOZ CASTRO"), sin que le siga otra
 * letra o número: el contrato 9504 no coincide con "95040-..." ni con "19504-...".
 * Nunca se arma una ruta con datos de la petición: los archivos se eligen por nombre
 * entre los que existen en la carpeta.
 */
class CarpetaDocumentos
{
    /** Archivos que el explorador de Windows crea solos y no son documentos. */
    private const IGNORADOS = ['Thumbs.db', 'desktop.ini', '~$*'];

    /**
     * @return list<array{nombre: string, tamano: int}>
     */
    public function listar(string $empresa, string $numeroBase): array
    {
        return array_values(array_map(
            fn (SplFileInfo $archivo): array => [
                'nombre' => $archivo->getFilename(),
                'tamano' => (int) $archivo->getSize(),
            ],
            $this->archivos($empresa, $numeroBase),
        ));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<array{nombre: string, tamano: int, ruta: string}>
     *
     * @throws ValidationException si algún nombre no existe en la carpeta del contrato.
     */
    public function seleccionar(string $empresa, string $numeroBase, array $nombres): array
    {
        $disponibles = $this->archivos($empresa, $numeroBase);
        $seleccion = [];

        foreach (array_unique($nombres) as $nombre) {
            if (! isset($disponibles[$nombre])) {
                throw ValidationException::withMessages([
                    'archivos' => 'Uno de los archivos ya no está en la carpeta del contrato. Actualiza la lista.',
                ]);
            }

            $seleccion[] = [
                'nombre' => $nombre,
                'tamano' => (int) $disponibles[$nombre]->getSize(),
                'ruta' => $disponibles[$nombre]->getRealPath(),
            ];
        }

        return $seleccion;
    }

    /**
     * Indica con una sola lectura de la raíz qué contratos tienen carpeta.
     *
     * @param  list<string>  $numerosBase
     * @return array<string, bool>|null null si la unidad de documentos no está disponible
     */
    public function existen(string $empresa, array $numerosBase): ?array
    {
        $raiz = config("laboratorio.raices.{$empresa}");

        if (blank($raiz) || ! is_dir($raiz)) {
            return null;
        }

        $nombres = [];
        foreach (Finder::create()->directories()->depth(0)->in($raiz) as $carpeta) {
            $nombres[] = $this->normalizar($carpeta->getFilename());
        }

        $existen = [];
        foreach ($numerosBase as $numeroBase) {
            $base = $this->normalizar($numeroBase);
            $existen[$numeroBase] = false;

            foreach ($nombres as $nombre) {
                if ($this->coincide($nombre, $base)) {
                    $existen[$numeroBase] = true;
                    break;
                }
            }
        }

        return $existen;
    }

    /**
     * @return array<string, SplFileInfo> archivos de la carpeta indexados por nombre
     */
    private function archivos(string $empresa, string $numeroBase): array
    {
        $archivos = [];

        foreach (Finder::create()->files()->depth(0)->in($this->carpeta($empresa, $numeroBase))->notName(self::IGNORADOS)->sortByName(true) as $archivo) {
            $archivos[$archivo->getFilename()] = $archivo;
        }

        return $archivos;
    }

    private function carpeta(string $empresa, string $numeroBase): string
    {
        $raiz = config("laboratorio.raices.{$empresa}");

        abort_if(blank($raiz) || ! is_dir($raiz), 502, 'No se pudo acceder a la unidad de documentos.');

        $base = $this->normalizar($numeroBase);
        $coincidencias = [];

        foreach (Finder::create()->directories()->depth(0)->in($raiz) as $carpeta) {
            if ($this->coincide($this->normalizar($carpeta->getFilename()), $base)) {
                $coincidencias[] = $carpeta->getRealPath();
            }
        }

        abort_if($coincidencias === [], 404, 'Este contrato no tiene carpeta de documentos.');

        if (count($coincidencias) > 1) {
            $conArchivos = array_values(array_filter($coincidencias, $this->tieneArchivos(...)));

            abort_if(count($conArchivos) > 1, 409, 'Hay más de una carpeta para este contrato. Avisa a laboratorio.');

            $coincidencias = $conArchivos === [] ? [$coincidencias[0]] : $conArchivos;
        }

        return $coincidencias[0];
    }

    private function tieneArchivos(string $carpeta): bool
    {
        return Finder::create()->files()->depth(0)->in($carpeta)->notName(self::IGNORADOS)->hasResults();
    }

    /**
     * El nombre de la carpeta ya normalizado empieza por el contrato y no le sigue otra letra o número.
     */
    private function coincide(string $nombre, string $base): bool
    {
        return $base !== ''
            && str_starts_with($nombre, $base)
            && preg_match('/^[\p{L}\p{N}]/u', mb_substr($nombre, mb_strlen($base))) !== 1;
    }

    /**
     * Laboratorio escribe a mano los nombres de carpeta: sobran espacios al inicio (a veces el
     * invisible U+00A0), junto a los guiones o en lugar de ellos ("PE-CR-2026- 022089-1",
     * "16236 ST - NOMBRE"). Se comparan sin distinguir mayúsculas ni esos espacios.
     */
    private function normalizar(string $nombre): string
    {
        $sinBordes = (string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $nombre);

        return mb_strtoupper((string) preg_replace('/[\s\p{Z}]*-[\s\p{Z}]*/u', '-', $sinBordes));
    }
}
