<?php

namespace App\Informes;

/**
 * Los filtros del listado de Informes: la búsqueda y las tarjetas del resumen.
 */
final readonly class FiltrosInformes
{
    public function __construct(
        public string $buscar = '',
        public ?string $envio = null,
        public ?string $antiguedad = null,
        public ?string $sangre = null,
        public ?string $tejido = null,
        public ?string $liberacion = null,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  los datos validados de FiltrarInformesRequest
     */
    public static function desde(array $datos): self
    {
        return new self(
            buscar: trim($datos['buscar'] ?? ''),
            envio: self::envio($datos),
            antiguedad: $datos['antiguedad'] ?? null,
            sangre: $datos['sangre'] ?? null,
            tejido: $datos['tejido'] ?? null,
            liberacion: $datos['liberacion'] ?? null,
        );
    }

    /**
     * Sin «envio» se muestran los pendientes de envío; «todos» quita el filtro.
     *
     * @param  array<string, mixed>  $datos
     */
    private static function envio(array $datos): ?string
    {
        if (! array_key_exists('envio', $datos)) {
            return 'pendiente';
        }

        return $datos['envio'] === AlcanceInformes::ENVIO_TODOS ? null : $datos['envio'];
    }
}
