<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorFollowupAutomationService
{
    public function syncRideConfirmation(
        Lead $lead,
        CarbonInterface $rideDate,
        ?string $note = null
    ): LeadFollowup {
        return DB::transaction(function () use ($lead, $rideDate, $note) {
            $existing = LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->where('source', 'auto')
                ->where('followup_type', 'ride_confirmation')
                ->whereIn('status', [
                    LeadFollowup::STATUS_PENDING,
                    LeadFollowup::STATUS_ACTIVE,
                    LeadFollowup::STATUS_RESCHEDULED,
                ])
                ->lockForUpdate()
                ->first();

            $dueAt = $this->calculateDueAt($rideDate);

            if ($existing) {
                $existing->fill([
                        'ride_date' => $rideDate->toDateString(),
                        'due_at' => $dueAt,
                        'next_followup_date' => $dueAt,
                        'followup_note' => $note ?: $existing->followup_note,
                    ]);
                $existing->saveQuietly();

                return $existing->fresh();
            }

            $followup = new LeadFollowup([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'source' => 'auto',
                'followup_type' => 'ride_confirmation',
                'ride_date' => $rideDate->toDateString(),
                'due_at' => $dueAt,
                'next_followup_date' => $dueAt,
                'status' => LeadFollowup::STATUS_PENDING,
                'followed_by' => null,
                'followup_note' => $note
                    ?: 'System-created Ride Confirmation follow-up. Confirm vendor, timing and remaining operational details.',
            ]);

            $followup->saveQuietly();

            return $followup;
        });
    }

    public function createNewForReschedule(Lead $lead, CarbonInterface $rideDate): LeadFollowup
    {
        $dueAt = $this->calculateDueAt($rideDate);

        $followup = new LeadFollowup([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'source' => 'auto',
            'followup_type' => 'ride_confirmation',
            'ride_date' => $rideDate->toDateString(),
            'due_at' => $dueAt,
            'next_followup_date' => $dueAt,
            'status' => LeadFollowup::STATUS_PENDING,
            'followed_by' => null,
            'followup_note' => 'Ride date changed. Vendor reconfirmation required.',
        ]);

        $followup->saveQuietly();

        return $followup;
    }

    public function createManual(
        Lead $lead,
        User $user,
        string $type,
        CarbonInterface $dueAt,
        ?string $note = null
    ): LeadFollowup {
        $allowed = [
            'ride_confirmation',
            'vendor_callback',
            'timing_confirmation',
            'document_confirmation',
            'general',
        ];

        if (!in_array($type, $allowed, true)) {
            $type = 'general';
        }

        return LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'source' => 'manual',
            'followup_type' => $type,
            'due_at' => $dueAt,
            'next_followup_date' => $dueAt,
            'status' => LeadFollowup::STATUS_PENDING,
            'followed_by' => $user->id,
            'followup_note' => $note,
        ]);
    }

    private function calculateDueAt(CarbonInterface $rideDate): CarbonInterface
    {
        $now = now('Asia/Kolkata');
        $due = $rideDate
            ->copy()
            ->timezone('Asia/Kolkata')
            ->startOfDay()
            ->subDays((int) config('services.operations_ride_alert.days_before', 3))
            ->setTime(10, 0);

        return $due->lte($now) ? $now : $due;
    }
}
