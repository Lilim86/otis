<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Aviso corto al cliente: visita próxima, cuota por vencer, pago confirmado, etc. */
class AvisoClienteMail extends Mailable
{
    use Queueable;

    /** @param  list<string>  $lineas */
    public function __construct(public string $asunto, public string $titulo, public array $lineas, public ?string $enlace = null, public ?string $textoEnlace = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.aviso-cliente');
    }
}
