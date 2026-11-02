<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Client;
use App\Support\WhatsAppPhoneNumber;

class Msg91CampaignPhone
{
    public function forClient(Client $client): ?string
    {
        $number = trim((string) ($client->alternate_number ?: $client->contact_number));
        if ($number === '') {
            return null;
        }

        try {
            return WhatsAppPhoneNumber::e164($number, optional($client->country)->isd_code, false);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
