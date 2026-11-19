<?php

namespace App\Mail;

use App\Reportes\Comisiones\DatosEjemploComisiones;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** @phpstan-import-type Asesor from DatosEjemploComisiones */
class DetalleComisionAsesor extends Mailable
{
    /** @param Asesor $asesor */
    public function __construct(
        private readonly array $asesor,
        private readonly string $periodo,
        private readonly string $fechaAprobacion,
    ) {}

    public function envelope(): Envelope
    {
        $periodo = mb_strtolower($this->periodo);
        $total = 'S/ '.number_format($this->asesor['total'], 2);

        return new Envelope(subject: "Tu comisión de $periodo: $total");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comisiones.asesor',
            text: 'emails.comisiones.asesor-texto',
            with: [
                'asesor' => $this->asesor,
                'periodo' => $this->periodo,
                'fechaAprobacion' => CarbonImmutable::parse($this->fechaAprobacion)
                    ->setTimezone(config('reportes.zona_horaria'))->format('d-m-Y'),
            ],
        );
    }
}
