<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Factura o recibo adjunto en PDF para el cliente. */
class ComprobanteMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $cliente,
        public string $tipo,
        public string $numero,
        public string $monto,
        public string $nombreArchivo,
        private string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ucfirst($this->tipo)." {$this->numero} · ".config('sofia.emisor.nombre_comercial'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.comprobante');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->nombreArchivo)->withMime('application/pdf')];
    }
}
