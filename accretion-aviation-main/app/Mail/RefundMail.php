<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;

class RefundMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $emailSubject;
    public $template;
    public $filePath;

    /**
     * Create a new message instance.
     *
     * @param string      $template  Blade view path e.g. 'emails.refund-customer'
     * @param string      $subject   Email subject line
     * @param array       $data      Template variables
     * @param string|null $filePath  Local path to refund proof screenshot/file to attach
     */
    public function __construct(string $template, string $subject, array $data, ?string $filePath = null)
    {
        $this->template     = $template;
        $this->emailSubject = $subject;
        $this->data         = $data;
        $this->filePath     = $filePath;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $fromAddress = config('mail.from.address', env('MAIL_FROM_ADDRESS', 'noreply@example.com'));
        $fromName    = config('mail.from.name', config('app.name', 'Application'));

        $email = $this->from($fromAddress, $fromName)
            ->subject($this->emailSubject)
            ->view($this->template)          // plain HTML blade (not markdown)
            ->with('data', $this->data);

        // Attach the refund proof screenshot/PDF if provided and file exists on disk
        if ($this->filePath && file_exists($this->filePath)) {
            $email->attach($this->filePath);
        } elseif ($this->filePath) {
            Log::warning('RefundMail: attachment file not found on disk', ['path' => $this->filePath]);
        }

        return $email;
    }
}