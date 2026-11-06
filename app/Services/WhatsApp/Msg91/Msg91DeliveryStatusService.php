<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Msg91WhatsAppCampaignEvent;
use App\Models\Msg91WhatsAppCampaignRecipient;
use Illuminate\Support\Facades\DB;

class Msg91DeliveryStatusService
{
    private const RANK = [
        'accepted' => 1,
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
    ];

    public function apply(
        string $recipientId,
        string $status,
        array $providerEvent = []
    ): bool {
        if (!in_array(
            $status,
            ['accepted', 'sent', 'delivered', 'read', 'failed'],
            true
        )) {
            return false;
        }

        return DB::transaction(function () use (
            $recipientId,
            $status,
            $providerEvent
        ) {
            $recipient = Msg91WhatsAppCampaignRecipient::query()
                ->lockForUpdate()
                ->find($recipientId);

            if (!$recipient) {
                return false;
            }

           $current = (string) $recipient->status;

            $identifierUpdated = false;

            if (
                !empty($providerEvent['request_id'])
                && empty($recipient->provider_request_id)
            ) {
                $recipient->provider_request_id =
                    (string) $providerEvent['request_id'];

                $identifierUpdated = true;
            }

            if (
                !empty($providerEvent['message_id'])
                && empty($recipient->provider_message_id)
            ) {
                $recipient->provider_message_id =
                    (string) $providerEvent['message_id'];

                $identifierUpdated = true;
            }

            /*
            |--------------------------------------------------------------------------
            | Do not move delivery status backward.
            | But still save a newly received provider message ID, because later
            | delivery/read webhooks may use only that message ID.
            |--------------------------------------------------------------------------
            */
            if (
                $current === $status
                || in_array($current, ['read', 'cancelled'], true)
            ) {
                if ($identifierUpdated) {
                    $recipient->save();
                }

                return false;
            }

            if ($status === 'failed') {
                if (!in_array($current, ['accepted', 'sent'], true)) {
                    if ($identifierUpdated) {
                        $recipient->save();
                    }

                    return false;
                }
            } else {
                $currentRank = self::RANK[$current] ?? 0;
                $newRank = self::RANK[$status];

                if ($newRank <= $currentRank) {
                    if ($identifierUpdated) {
                        $recipient->save();
                    }

                    return false;
                }
            }

            $recipient->status = $status;

            if (in_array(
                $status,
                ['accepted', 'sent', 'delivered', 'read'],
                true
            )) {
                $recipient->{$status . '_at'} = now();
            }

            $recipient->save();

            $eventId = !empty($providerEvent['event_id'])
                ? (string) $providerEvent['event_id']
                : sha1(json_encode([
                    $recipient->id,
                    $status,
                    $providerEvent,
                ]));

            Msg91WhatsAppCampaignEvent::firstOrCreate(
                [
                    'provider_event_id' => $eventId,
                    'event_type' => 'webhook',
                ],
                [
                    'campaign_id' => $recipient->campaign_id,
                    'campaign_recipient_id' => $recipient->id,
                    'status' => $status,
                    'payload' => $providerEvent,
                    'occurred_at' => now(),
                ]
            );

            $campaign = $recipient->campaign()->firstOrFail();

            app(Msg91CampaignService::class)
                ->refreshCounters($campaign);

            return true;
        });
    }
}