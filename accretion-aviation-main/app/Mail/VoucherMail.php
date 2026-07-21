<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class VoucherMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $subject;
    public $template;
    public $filePath;

    public function __construct($template, $subject, $data, $filePath = null)
    {
        $this->data = $data;
        $this->subject = $subject;
        $this->template = $template;
        $this->filePath = $filePath;
    }

    public function build()
    {
        // Resolve from address and name (fallback to app.name)
        $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', 'noreply@example.com'));
        $fromName = config('mail.from.name', config('app.name', 'Application'));

        $email = $this->from($fromAddress, $fromName)
            ->subject($this->subject)
            ->markdown($this->template)
            ->with('data', $this->data);

        if ($this->filePath) {
            $email->attach($this->filePath);
        }

        return $email;
    }
}
