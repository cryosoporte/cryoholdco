<?php

namespace App\Informes;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Los datos del cuerpo del correo con los informes de un contrato base.
 *
 * Si todos los servicios son APTO se usa la plantilla «aptos». Si alguno es NO APTO se usa «no_aptos» y
 * hace falta el motivo de cada servicio NO APTO. Un servicio sin liberar (NO LIBERADO) impide el envío.
 */
class CuerpoCorreoContrato
{
    private const SERVICIOS = [
        'sangre' => 'Sangre de cordón umbilical',
        'tejido' => 'Tejido de cordón umbilical',
    ];

    private const RESULTADO_APTO = 'Apto para criopreservación';

    public function __construct(private readonly ListadoInformes $listado) {}

    /**
     * @param  array{sangre?: ?string, tejido?: ?string}  $motivos  clave de AlcanceInformes::MOTIVOS_NO_APTO por servicio
     * @return array{plantilla: string, familia: ?string, numero_base: string, servicios: list<array{nombre: string, resultado: string}>}
     *
     * @throws HttpException 404 si el contrato no está en el listado de Informes de la empresa.
     * @throws ValidationException si un servicio no está liberado o falta el motivo de un NO APTO.
     */
    public function para(string $empresa, string $numeroBase, array $motivos = []): array
    {
        $contrato = $this->listado->contrato($empresa, $numeroBase);

        abort_if($contrato === null, 404, 'El contrato no está en el listado de Informes de tu empresa.');

        $servicios = [];
        $hayNoApto = false;

        foreach (self::SERVICIOS as $clave => $nombre) {
            $resultado = $contrato[$clave];

            if ($resultado === null) {
                continue;
            }

            if ($resultado === LiberacionContrato::NO_LIBERADO) {
                throw ValidationException::withMessages([
                    'documentos' => 'Un servicio del contrato aún no está liberado; no se puede enviar el informe.',
                ]);
            }

            if ($resultado === LiberacionContrato::APTO) {
                $servicios[] = ['nombre' => $nombre, 'resultado' => self::RESULTADO_APTO];

                continue;
            }

            $motivo = $motivos[$clave] ?? null;

            if (! isset(AlcanceInformes::MOTIVOS_NO_APTO[$motivo])) {
                throw ValidationException::withMessages([
                    'documentos' => 'Elige el motivo del resultado NO APTO desde «Ver documentos».',
                ]);
            }

            $hayNoApto = true;
            $servicios[] = ['nombre' => $nombre, 'resultado' => AlcanceInformes::MOTIVOS_NO_APTO[$motivo]];
        }

        return [
            'plantilla' => $hayNoApto ? 'no_aptos' : 'aptos',
            'familia' => $contrato['familia'],
            'numero_base' => $numeroBase,
            'servicios' => $servicios,
        ];
    }
}
