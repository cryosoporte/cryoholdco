<?php

namespace App\Serologia;

use Carbon\CarbonImmutable;

class PlazoSerologia
{
    /**
     * @return array{situacion: string, fecha_apertura: ?string, fecha_limite: ?string, dias_restantes: ?int, porcentaje_transcurrido: ?int, texto: string}
     */
    public function paraRegistro(
        ?string $serologia,
        ?string $fechaNacimiento,
        ?string $fechaToma,
        ?string $fechaResultado,
        CarbonImmutable $hoy
    ): array {
        if ($serologia === 'S') {
            $situacion = 'recibido';
            $fecha = $fechaResultado;
            $prefijo = 'Resultado recibido el ';
        } elseif ($fechaToma !== null) {
            $situacion = 'tomada';
            $fecha = $fechaToma;
            $prefijo = 'Tomada el ';
        } else {
            return $this->calcular($fechaNacimiento, $hoy);
        }

        $texto = $fecha === null
            ? 'Fecha de resultado no registrada'
            : $prefijo.CarbonImmutable::createFromFormat('!Y-m-d', $fecha, 'America/Lima')
                ->locale('es')->translatedFormat('j M Y');

        return [
            'situacion' => $situacion,
            'fecha_apertura' => null,
            'fecha_limite' => null,
            'dias_restantes' => null,
            'porcentaje_transcurrido' => null,
            'texto' => $texto,
        ];
    }

    /**
     * @return array{situacion: string, fecha_apertura: ?string, fecha_limite: ?string, dias_restantes: ?int, porcentaje_transcurrido: ?int, texto: string}
     */
    public function calcular(
        ?string $fechaNacimiento,
        CarbonImmutable $hoy,
        int $diasAntes = 30,
        int $diasDespues = 7,
    ): array {
        if ($fechaNacimiento === null) {
            return [
                'situacion' => 'sin_fecha',
                'fecha_apertura' => null,
                'fecha_limite' => null,
                'dias_restantes' => null,
                'porcentaje_transcurrido' => null,
                'texto' => 'Sin fecha de nacimiento',
            ];
        }

        $hoy = $hoy->setTimezone('America/Lima')->startOfDay();
        $nacimiento = CarbonImmutable::createFromFormat('!Y-m-d', $fechaNacimiento, 'America/Lima');
        $apertura = $nacimiento->subDays($diasAntes);
        $limite = $nacimiento->addDays($diasDespues);
        $diasRestantes = (int) $hoy->diffInDays($limite, false);
        $duracion = (int) $apertura->diffInDays($limite, false);
        $diasTranscurridos = (int) $apertura->diffInDays($hoy, false);
        $porcentaje = (int) round(max(0, min($duracion, $diasTranscurridos)) / $duracion * 100);
        $fechaLimite = $limite->locale('es')->translatedFormat('j M Y');

        if ($hoy->lessThan($apertura)) {
            $situacion = 'aun_no_abre';
            $texto = 'Abre el '.$apertura->locale('es')->translatedFormat('j M Y');
        } elseif ($hoy->greaterThan($limite)) {
            $situacion = 'fuera_de_plazo';
            $texto = 'Venció el '.$fechaLimite;
        } else {
            $situacion = $diasRestantes <= 7 ? 'por_vencer' : 'en_plazo';
            $texto = match ($diasRestantes) {
                0 => 'Vence hoy · '.$fechaLimite,
                1 => 'Queda 1 día · '.$fechaLimite,
                default => 'Quedan '.$diasRestantes.' días · '.$fechaLimite,
            };
        }

        return [
            'situacion' => $situacion,
            'fecha_apertura' => $apertura->toDateString(),
            'fecha_limite' => $limite->toDateString(),
            'dias_restantes' => $diasRestantes,
            'porcentaje_transcurrido' => $porcentaje,
            'texto' => $texto,
        ];
    }
}
