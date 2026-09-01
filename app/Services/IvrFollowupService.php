<?php

namespace App\Services;

use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Support\Str;

class IvrFollowupService
{
    public function createIfNeeded(
        Lead $lead,
        IvrCallLog $callLog,
        bool $repeat = false,
        bool $allowUnassigned = false
    ): void
    {
        if (!empty($callLog->initial_followup_created_at)) {
            $this->attachRepresentativeToOpenFollowup($lead);

            return;
        }

        if (
            empty($lead->representative_user_id)
            && !$allowUnassigned
        ) {
            return;
        }

        $nextFollowup = now()->addMinutes($repeat ? 5 : ($this->isSuccessfulStatus($callLog->dial_status) ? 15 : 5));

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $nextFollowup,
            'followup_note' => $this->buildNote($callLog, $repeat),
            'followed_by' => $lead->representative_user_id ?: null,
            'status' => 1,
        ]);

        $callLog->initial_followup_created_at = now();
        $callLog->save();
    }

    private function attachRepresentativeToOpenFollowup(
        Lead $lead
    ): void {
        if (empty($lead->representative_user_id)) {
            return;
        }

        $followup = $lead
            ->leadFollowups()
            ->whereNull('followed_by')
            ->whereNotNull('next_followup_date')
            ->whereNotIn('status', [2, 5])
            ->orderByDesc('created_at')
            ->first();

        if (!$followup) {
            return;
        }

        $followup->followed_by =
            $lead->representative_user_id;
        $followup->save();
    }

public function isSuccessfulStatus(
    ?string $status
): bool {
    $status = Str::lower(
        trim((string) $status)
    );

    return in_array(
        $status,
        [
            'success',
            'sucess',
            'connected',
        ],
        true
    );
}

    private function buildNote(IvrCallLog $callLog, bool $repeat): string
    {
        $parts = [
            $repeat ? 'Repeat VI IVR call received.' : 'New VI IVR lead received.',
            'Call ID: ' . $callLog->provider_call_id,
            'Call Type: ' . ($callLog->call_type_code ?: 'N/A'),
            'DTMF: ' . ($callLog->raw_dtmf ?: 'N/A'),
            'Dial Status: ' . ($callLog->dial_status ?: 'N/A'),
            'Agent: ' . ($callLog->agent_name ?: 'N/A'),
            'Agent Number: ' . ($callLog->agent_number ?: 'N/A'),
        ];

        if ($callLog->voice_url) {
            $parts[] = 'Recording URL: ' . $callLog->voice_url;
        }

        return implode(' | ', $parts);
    }
}
