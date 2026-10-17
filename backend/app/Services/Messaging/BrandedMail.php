<?php

namespace App\Services\Messaging;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BrandedMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, array{label: string, value: string}>  $table
     */
    public function __construct(
        public string $subjectLine,
        public string $greeting,
        public array $lines = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public array $table = [],
        public array $afterLines = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.branded');
    }
}
