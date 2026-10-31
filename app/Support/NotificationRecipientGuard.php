<?php

namespace App\Support;

final class NotificationRecipientGuard
{
    public static function overlaps(
        array $recipientEmails,
        array $recipientPhones,
        array $otherEmails,
        array $otherPhones
    ): bool {
        $blockedEmails = [];

        foreach ($otherEmails as $email) {
            $email = self::normalizeEmail($email);

            if ($email !== '') {
                $blockedEmails[$email] = true;
            }
        }

        foreach ($recipientEmails as $email) {
            $email = self::normalizeEmail($email);

            if ($email !== '' && isset($blockedEmails[$email])) {
                return true;
            }
        }

        $blockedPhones = [];

        foreach ($otherPhones as $phone) {
            foreach (self::phoneKeys($phone) as $key) {
                $blockedPhones[$key] = true;
            }
        }

        foreach ($recipientPhones as $phone) {
            foreach (self::phoneKeys($phone) as $key) {
                if (isset($blockedPhones[$key])) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function excludeEmails(array $emails, array $blockedEmails): array
    {
        $blocked = [];

        foreach ($blockedEmails as $email) {
            $email = self::normalizeEmail($email);

            if ($email !== '') {
                $blocked[$email] = true;
            }
        }

        $safe = [];

        foreach ($emails as $email) {
            $email = self::normalizeEmail($email);

            if ($email !== '' && !isset($blocked[$email])) {
                $safe[$email] = $email;
            }
        }

        return array_values($safe);
    }

    private static function normalizeEmail($email): string
    {
        return strtolower(trim((string) $email));
    }

    private static function phoneKeys($phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return [];
        }

        $keys = [$digits];

        // Treat a country-code difference as a possible match.
        if (strlen($digits) > 10) {
            $keys[] = substr($digits, -10);
        }

        return array_values(array_unique($keys));
    }
}