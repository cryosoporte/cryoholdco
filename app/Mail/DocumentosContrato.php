<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentosContrato extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{nombre: string, ruta: string}>  $archivos
     * @param  array{plantilla: string, familia: ?string, numero_base: string, servicios: list<array{nombre: string, resultado: string}>}  $cuerpo
     */
    public function __construct(
        public string $empresa,
        public string $numeroBase,
        public array $archivos,
        public array $cuerpo,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Documentos de tu contrato N.º {$this->numeroBase}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.documentos-contrato',
            text: 'emails.documentos-contrato-texto',
            with: [
                'marca' => config("laboratorio.marcas.{$this->empresa}", config('laboratorio.marcas.001')),
                'cuerpo' => $this->cuerpo,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $archivo): Attachment => Attachment::fromPath($archivo['ruta'])->as($archivo['nombre']),
            $this->archivos,
        );
    }
}
