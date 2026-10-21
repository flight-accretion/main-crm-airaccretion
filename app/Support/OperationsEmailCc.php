<?php

namespace App\Support;

use App\Models\NotificationMaster;
use Illuminate\Mail\Mailable;

class OperationsEmailCc
{
    public static function apply(Mailable $mail): void
    {
        $existing = collect(array_merge($mail->to, $mail->cc, $mail->bcc))
            ->pluck('address')->map(fn ($email) => strtolower(trim((string) $email)))->all();

        foreach (NotificationMaster::activeInternalRecipients($existing) as $recipient) {
            $email = strtolower(trim((string) $recipient->email_id));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $existing, true)) {
                $mail->cc($email);
                $existing[] = $email;
            }
        }
    }
}
