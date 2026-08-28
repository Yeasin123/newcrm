<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The lead welcome email. `$body` is plain text (from LeadWelcomeAgentService,
 * AI-drafted or the fixed fallback) rendered via a minimal blade wrapper.
 */
class NewLeadWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.leads.welcome',
            with: ['body' => $this->body],
        );
    }
}
