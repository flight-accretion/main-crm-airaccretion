<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class WhatsAppLeadFollowupService
{
    public function createForIncomingMessage(
        Lead $lead,
        WhatsAppMessage $message,
        array $data
    ): ?LeadFollowup {
        if (
            !empty($message->lead_followup_id)
        ) {
            return null;
        }

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now(),
            'followup_note' => $this->note($data),
            'followed_by' => $lead->representative_user_id ?: null,
            'status' => 1,
        ]);

        $message->lead_followup_id = $followup->id;
        $message->save();

        return $followup;
    }

    private function note(array $data): string
    {
        $messageAt = $data['message_at'] ?? now();

        return implode(PHP_EOL, [
            'WhatsApp message received.',
            'Customer: ' . ($data['customer_name'] ?: '-'),
            'Phone: ' . (
                $data['normalized_phone']
                ?: ($data['raw_phone'] ?: '-')
            ),
            'Message: ' . ($data['body'] ?: '-'),
            'Received: ' . $messageAt->format('d-m-Y h:i A'),
            'Source: WhatsApp / WhatCRM',
        ]);
    }
}
