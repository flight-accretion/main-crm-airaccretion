<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMaster extends Model
{
    protected $fillable = [
        'mobile_number',
        'email_id',
        'status',
        'contact_country_code',
        'country_id',
    ];
    use HasFactory;

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class, 'country_id');
    }

    public static function activeInternalRecipients(array $excludeEmails = [], array $excludePhones = [])
    {
        $excludedEmails = collect($excludeEmails)
            ->map(fn($email) => self::normalizeEmail($email))
            ->filter()
            ->unique()
            ->flip();

        $excludedPhones = collect($excludePhones)
            ->flatMap(fn($phone) => self::phoneSearchKeys($phone))
            ->filter()
            ->unique()
            ->flip();

        return self::where('status', 1)
            ->get()
            ->filter(function (self $recipient) {
                return self::normalizeEmail($recipient->email_id) !== ''
                    || self::normalizePhone($recipient->mobile_number) !== '';
            })
            ->reject(function (self $recipient) use ($excludedEmails, $excludedPhones) {
                $email = self::normalizeEmail($recipient->email_id);
                $phoneKeys = self::recipientPhoneSearchKeys(
                    $recipient->mobile_number,
                    $recipient->contact_country_code
                );

                return ($email !== '' && $excludedEmails->has($email))
                    || collect($phoneKeys)->contains(fn($phone) => $excludedPhones->has($phone));
            })
            ->unique(function (self $recipient) {
                $email = self::normalizeEmail($recipient->email_id);

                if ($email !== '') {
                    return 'email:' . $email;
                }

                $phoneKeys = self::recipientPhoneSearchKeys(
                    $recipient->mobile_number,
                    $recipient->contact_country_code
                );

                return 'phone:' . ($phoneKeys[0] ?? $recipient->getKey());
            })
            ->values();
    }

    private static function normalizeEmail($email): string
    {
        return strtolower(trim((string) $email));
    }

    private static function normalizePhone($phone): string
    {
        return preg_replace('/\D/', '', (string) $phone) ?: '';
    }

    private static function phoneSearchKeys($phone): array
    {
        $normalized = self::normalizePhone($phone);

        if ($normalized === '') {
            return [];
        }

        $keys = [$normalized];

        if (strlen($normalized) > 10) {
            $keys[] = substr($normalized, -10);
        }

        return array_values(array_unique($keys));
    }

    private static function recipientPhoneSearchKeys($phone, $countryCode): array
    {
        return array_values(array_unique(array_merge(
            self::phoneSearchKeys($phone),
            self::phoneSearchKeys(($countryCode ?? '') . ($phone ?? ''))
        )));
    }
}
