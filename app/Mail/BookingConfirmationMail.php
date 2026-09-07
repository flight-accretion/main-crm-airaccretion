<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $subjectLine;

    public string $body;

    public ?string $replyToAddress;

    public ?string $replyToName;

    public function __construct(
        string $subjectLine,
        string $body,
        ?string $replyToAddress = null,
        ?string $replyToName = null
    ) {
        $this->subjectLine = $subjectLine;
        $this->body = $body;
        $this->replyToAddress = $replyToAddress;
        $this->replyToName = $replyToName;
    }

    public function build()
    {
        $fromAddress = config(
            'mail.from.address',
            env('MAIL_FROM_ADDRESS', 'noreply@example.com')
        );
        $fromName = config(
            'mail.from.name',
            config('app.name', 'Accretion Aviation')
        );

        $email = $this
            ->from($fromAddress, $fromName)
            ->subject($this->subjectLine)
            ->markdown('emails.booking-confirmation')
            ->with([
                'body' => $this->body,
            ]);

        if (
            $this->replyToAddress
            && filter_var($this->replyToAddress, FILTER_VALIDATE_EMAIL)
        ) {
            $email->replyTo($this->replyToAddress, $this->replyToName);
        }

        return $email;
    }
}
