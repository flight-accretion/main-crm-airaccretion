<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class BookingTemplateTestMail extends Mailable
{
    public function __construct(public string $subjectLine, public string $body)
    {
    }

    public function build()
    {
        // Test sends intentionally bypass the customer mail's Operations CC.
        return $this->subject('[TEST] '.str_replace(["\r", "\n"], ' ', $this->subjectLine))
            ->markdown('emails.booking-confirmation')->with(['body'=>$this->body]);
    }
}
