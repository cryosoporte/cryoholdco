<?php

namespace App\Mail;

use App\Reportes\Comisiones\DatosEjemploComisiones;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** @phpstan-import-type Liquidacion from DatosEjemploComisiones */
class ComisionesParaAprobacion extends Mailable
{
    /** @param Liquidacion $liquidacion */
    public function __construct(
        private readonly array $liquidacion,
        private readonly string $procesadoPor,
        private readonly string $fechaProceso,
    ) {}

    public function envelope(): Envelope
    {
        $periodo = $this->liquidacion['periodo'];
        $total = 'S/ '.number_format($this->liquidacion['total'], 2);

        return new Envelope(subject: "Comisiones $periodo · pendientes de su aprobación ($total)");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comisiones.gerencia',
            text: 'emails.comisiones.gerencia-texto',
            with: [
                'liquidacion' => $this->liquidacion,
                'procesadoPor' => $this->procesadoPor,
                'fechaProceso' => CarbonImmutable::parse($this->fechaProceso)
                    ->setTimezone(config('reportes.zona_horaria'))->format('d-m-Y \a \l\a\s H:i'),
                'enlaceDetalle' => route('reportes.comisiones.liquidaciones.mostrar', $this->liquidacion['id']),
            ],
        );
    }
}
