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

    public static function activeInternalRecipients()
    {
        $blockedEmails = Client::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->merge(
                Vendor::query()
                    ->whereNotNull('email')
                    ->pluck('email')
            )
            ->map(fn($email) => self::normalizeEmail($email))
            ->filter()
            ->unique()
            ->flip();

        $blockedPhones = Client::query()
            ->select('contact_number', 'alternate_number')
            ->get()
            ->flatMap(fn($client) => [$client->contact_number, $client->alternate_number])
            ->merge(
                Vendor::query()
                    ->whereNotNull('contact_number')
                    ->pluck('contact_number')
            )
            ->map(fn($phone) => self::normalizePhone($phone))
            ->filter()
            ->unique()
            ->flip();

        return self::where('status', 1)
            ->get()
            ->reject(function (self $recipient) use ($blockedEmails, $blockedPhones) {
                $email = self::normalizeEmail($recipient->email_id);
                $phoneWithCountry = self::normalizePhone(
                    ($recipient->contact_country_code ?? '') . ($recipient->mobile_number ?? '')
                );
                $phoneOnly = self::normalizePhone($recipient->mobile_number);

                return ($email !== '' && $blockedEmails->has($email))
                    || ($phoneWithCountry !== '' && $blockedPhones->has($phoneWithCountry))
                    || ($phoneOnly !== '' && $blockedPhones->has($phoneOnly));
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
}
