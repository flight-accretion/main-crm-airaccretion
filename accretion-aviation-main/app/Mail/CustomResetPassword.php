<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $resetUrl;

    public function __construct($resetUrl)
    {
        $this->resetUrl = $resetUrl;
    }

    public function build()
    {
        return $this->from('no-reply@accretionaviation.com', 'Accretion Aviation')
                    ->subject('Password Reset Request - Accretion Aviation')
                    ->view('admin.auth.email');
    }
}