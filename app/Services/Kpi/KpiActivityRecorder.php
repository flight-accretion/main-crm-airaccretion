<?php

namespace App\Services\Kpi;

use App\Models\KpiActivityEvent;
use App\Models\LeadFollowup;
use App\Models\User;

class KpiActivityRecorder
{
    public const EVENT_NOTE_ADDED = 'note_added';
    public const EVENT_STATUS_CHANGED = 'status_changed';

    public function recordSalesFollowup(
        LeadFollowup $followup,
        $previousStatus,
        User $actor
    ): void {
        $occurredAt = $followup->created_at ?: now();
        $note = trim((string) $followup->followup_note);

        if ($note !== '') {
            KpiActivityEvent::firstOrCreate(
                [
                    'event_key' => sprintf(
                        'lead_followup:%s:note_added',
                        $followup->id
                    ),
                ],
                [
                    'department' => 'sales',
                    'user_id' => $actor->id,
                    'entity_type' => 'lead',
                    'entity_id' => (string) $followup->lead_id,
                    'event_type' => self::EVENT_NOTE_ADDED,
                    'source_record_id' => (string) $followup->id,
                    'source' => 'human_ui',
                    'old_value' => null,
                    'new_value' => $note,
                    'metadata' => [
                        'followup_id' => (string) $followup->id,
                    ],
                    'occurred_at' => $occurredAt,
                ]
            );
        }

        if (
            $previousStatus !== null
            && $followup->status !== null
            && (int) $previousStatus !== (int) $followup->status
        ) {
            KpiActivityEvent::firstOrCreate(
                [
                    'event_key' => sprintf(
                        'lead_followup:%s:status_changed',
                        $followup->id
                    ),
                ],
                [
                    'department' => 'sales',
                    'user_id' => $actor->id,
                    'entity_type' => 'lead',
                    'entity_id' => (string) $followup->lead_id,
                    'event_type' => self::EVENT_STATUS_CHANGED,
                    'source_record_id' => (string) $followup->id,
                    'source' => 'human_ui',
                    'old_value' => (string) $previousStatus,
                    'new_value' => (string) $followup->status,
                    'metadata' => [
                        'followup_id' => (string) $followup->id,
                    ],
                    'occurred_at' => $occurredAt,
                ]
            );
        }
    }
}
