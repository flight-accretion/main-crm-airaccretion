<?php

namespace App\Services;

use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Support\Str;

class IvrFollowupService
{
    public function createIfNeeded(Lead $lead, IvrCallLog $callLog, bool $repeat = false): void
    {
        if (empty($lead->representative_user_id) || !empty($callLog->initial_followup_created_at)) {
            return;
        }

        $nextFollowup = now()->addMinutes($repeat ? 5 : ($this->isSuccessfulStatus($callLog->dial_status) ? 15 : 5));

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $nextFollowup,
            'followup_note' => $this->buildNote($callLog, $repeat),
            'followed_by' => $lead->representative_user_id,
            'status' => 1,
        ]);

        $callLog->initial_followup_created_at = now();
        $callLog->save();
    }

    public function isSuccessfulStatus(?string $status): bool
    {
        $status = Str::lower(trim((string) $status));
        return in_array($status, ['success', 'sucess'], true);
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
        ];

        if ($callLog->voice_url) {
            $parts[] = 'Recording URL: ' . $callLog->voice_url;
        }

        return implode(' | ', $parts);
    }
}
