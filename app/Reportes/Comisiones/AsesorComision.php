<?php

namespace App\Reportes\Comisiones;

/**
 * Un colaborador que comisiona: un usuario con CODIGO_NETSUITE y ROL_NETSUITE.
 */
final readonly class AsesorComision
{
    public function __construct(
        public int $id,
        public string $nombre,
        public ?string $correo,
        public string $rol,
        public string $codigoNetsuite,
    ) {}
}
