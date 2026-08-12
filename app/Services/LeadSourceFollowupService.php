<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Support\Str;

class LeadSourceFollowupService
{
    public function create(
        Lead $lead,
        string $source,
        string $message,
        array $context = []
    ): LeadFollowup {
        $source = strtoupper(trim($source));

        $note = $this->buildNote(
            $source,
            $message,
            $context
        );

        return LeadFollowup::create([
            'id' => (string) Str::uuid(),

            'lead_id' => $lead->id,

            /*
             * Existing executive remains owner.
             */
            'followed_by' =>
                $lead->representative_user_id,

            /*
             * Source lead requires prompt action.
             */
            'next_followup_date' =>
                now()->addMinutes(5),

            'followup_note' => $note,

            'status' => 1,
        ]);
    }

    private function buildNote(
        string $source,
        string $message,
        array $context
    ): string {
        $parts = [];

        $parts[] =
            'New customer enquiry received via '
            . $source . '.';

        if (!empty($context['service'])) {
            $parts[] =
                'Service: '
                . $context['service'];
        }

        if (!empty($context['phone'])) {
            $parts[] =
                'Phone: '
                . $context['phone'];
        }

        if (!empty($context['email'])) {
            $parts[] =
                'Email: '
                . $context['email'];
        }

        if (!empty($context['reference'])) {
            $parts[] =
                'Reference: '
                . $context['reference'];
        }

        if (trim($message) !== '') {
            $parts[] =
                'Message: '
                . trim($message);
        }

        return implode(PHP_EOL, $parts);
    }
}