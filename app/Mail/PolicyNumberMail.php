<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent after a successful public checkout. Legacy `emails.policy_number_email`
 * (Mail::send from PlanCheckoutController::storeData) — the customer's policy
 * number is also their portal password, so it must reach them.
 */
class PolicyNumberMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Client $client) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to American Home Coverage! Your Policy Number and Account Details',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.checkout.policy-number', with: [
            'client' => $this->client,
        ]);
    }
}
