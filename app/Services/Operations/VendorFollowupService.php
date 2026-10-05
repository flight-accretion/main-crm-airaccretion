<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorFollowupService
{
    public const DEFAULT_PURPOSE = 'Confirm Ride Date & Time';

    public function create(
        Lead $lead,
        Vendor $vendor,
        User $createdBy,
        User $assignedTo,
        array $data
    ): OperationCase {
        return DB::transaction(function () use ($lead, $vendor, $createdBy, $assignedTo, $data) {
            $existing = OperationCase::query()
                ->where('lead_id', $lead->id)
                ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)
                ->whereNull('completed_at')
                ->whereRaw("metadata->>'vendor_id' = ?", [$vendor->id])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'vendor_id' => 'An open Vendor Follow-up already exists for this Lead and Vendor. Open that follow-up and reschedule it instead.',
                ]);
            }

            $followupDate = Carbon::parse($data['followup_date'])->startOfDay();
            $tentativeRideAt = !empty($data['tentative_ride_at'])
                ? Carbon::parse($data['tentative_ride_at'])
                : null;

            if ($tentativeRideAt && $followupDate->gt($tentativeRideAt->copy()->startOfDay())) {
                throw ValidationException::withMessages([
                    'followup_date' => 'Vendor Follow-up date cannot be after the tentative ride date.',
                ]);
            }

            $metadata = [
                'vendor_id' => $vendor->id,
                'vendor_name_snapshot' => $vendor->name,
                'purpose' => trim((string) ($data['purpose'] ?? self::DEFAULT_PURPOSE)) ?: self::DEFAULT_PURPOSE,
                'tentative_ride_at' => $tentativeRideAt ? $tentativeRideAt->toDateTimeString() : null,
                'confirmed_ride_at' => null,
                'vendor_response' => null,
                'resolution' => null,
            ];

            $case = OperationCase::create([
                'lead_id' => $lead->id,
                'type' => OperationCase::TYPE_VENDOR_FOLLOWUP,
                'status' => OperationCase::STATUS_PENDING,
                'assigned_to' => $assignedTo->id,
                'created_by' => $createdBy->id,
                'opened_at' => now(),
                'completed_at' => null,
                'next_followup_at' => $followupDate,
                'note' => $data['remarks'] ?? null,
                'metadata' => $metadata,
            ]);

            $this->activity(
                $case,
                $createdBy,
                'vendor_followup_created',
                null,
                OperationCase::STATUS_PENDING,
                sprintf(
                    'Operations member %s created a Vendor Follow-up for %s. Vendor: %s. Expected ride: %s.',
                    $createdBy->name,
                    $followupDate->format('d-m-Y'),
                    $vendor->name,
                    $tentativeRideAt ? $tentativeRideAt->format('d-m-Y H:i') : 'Not set'
                ),
                [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'followup_date' => $followupDate->toDateString(),
                    'tentative_ride_at' => $tentativeRideAt ? $tentativeRideAt->toDateTimeString() : null,
                    'assigned_to' => $assignedTo->id,
                ]
            );

            return $case->fresh();
        });
    }

    public function reschedule(OperationCase $case, User $user, string $newDate, string $reason): OperationCase
    {
        return DB::transaction(function () use ($case, $user, $newDate, $reason) {
            $case = $this->lockOpenCase($case);
            $oldDate = $case->next_followup_at ? $case->next_followup_at->copy() : null;
            $newFollowupDate = Carbon::parse($newDate)->startOfDay();
            $tentativeRideAt = data_get($case->metadata, 'tentative_ride_at');

            if ($tentativeRideAt && $newFollowupDate->gt(Carbon::parse($tentativeRideAt)->startOfDay())) {
                throw ValidationException::withMessages([
                    'followup_date' => 'Vendor Follow-up date cannot be after the tentative ride date.',
                ]);
            }

            $oldStatus = $case->status;
            $metadata = $case->metadata ?? [];
            $metadata['last_reschedule_reason'] = $reason;
            $metadata['last_rescheduled_at'] = now()->toDateTimeString();
            $metadata['last_rescheduled_by'] = $user->id;

            $case->update([
                'status' => OperationCase::STATUS_PENDING,
                'next_followup_at' => $newFollowupDate,
                'metadata' => $metadata,
            ]);

            $this->activity(
                $case,
                $user,
                'vendor_followup_rescheduled',
                $oldStatus,
                OperationCase::STATUS_PENDING,
                sprintf(
                    'Operations member %s rescheduled Vendor Follow-up from %s to %s. Reason: %s',
                    $user->name,
                    $oldDate ? $oldDate->format('d-m-Y') : 'N/A',
                    $newFollowupDate->format('d-m-Y'),
                    $reason
                ),
                [
                    'old_followup_date' => $oldDate ? $oldDate->toDateString() : null,
                    'new_followup_date' => $newFollowupDate->toDateString(),
                    'reason' => $reason,
                ]
            );

            return $case->fresh();
        });
    }

    public function complete(OperationCase $case, User $user, array $data): OperationCase
    {
        return DB::transaction(function () use ($case, $user, $data) {
            $case = $this->lockOpenCase($case);
            $confirmedRideAt = Carbon::createFromFormat(
                'Y-m-d H:i',
                $data['confirmed_ride_date'] . ' ' . $data['confirmed_ride_time']
            );
            $oldStatus = $case->status;
            $metadata = $case->metadata ?? [];
            $metadata['confirmed_ride_at'] = $confirmedRideAt->toDateTimeString();
            $metadata['vendor_response'] = trim((string) $data['vendor_response']);
            $metadata['resolution'] = 'confirmed';
            $metadata['confirmed_by'] = $user->id;
            $metadata['confirmed_at'] = now()->toDateTimeString();

            $case->update([
                'status' => OperationCase::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
                'next_followup_at' => null,
                'metadata' => $metadata,
            ]);

            $this->activity(
                $case,
                $user,
                'vendor_followup_completed',
                $oldStatus,
                OperationCase::STATUS_COMPLETED,
                sprintf(
                    'Operations member %s completed Vendor Follow-up. %s confirmed the ride for %s. Vendor response: %s',
                    $user->name,
                    data_get($metadata, 'vendor_name_snapshot', 'Vendor'),
                    $confirmedRideAt->format('d-m-Y H:i'),
                    $metadata['vendor_response']
                ),
                [
                    'confirmed_ride_at' => $confirmedRideAt->toDateTimeString(),
                    'vendor_response' => $metadata['vendor_response'],
                    'resolution' => 'confirmed',
                ]
            );

            return $case->fresh();
        });
    }

    public function cancel(OperationCase $case, User $user, string $reason): OperationCase
    {
        return DB::transaction(function () use ($case, $user, $reason) {
            $case = $this->lockOpenCase($case);
            $oldStatus = $case->status;
            $metadata = $case->metadata ?? [];
            $metadata['resolution'] = 'cancelled';
            $metadata['cancellation_reason'] = $reason;
            $metadata['cancelled_by'] = $user->id;
            $metadata['cancelled_at'] = now()->toDateTimeString();

            $case->update([
                'status' => OperationCase::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
                'next_followup_at' => null,
                'metadata' => $metadata,
            ]);

            $this->activity(
                $case,
                $user,
                'vendor_followup_cancelled',
                $oldStatus,
                OperationCase::STATUS_COMPLETED,
                sprintf('Operations member %s cancelled Vendor Follow-up. Reason: %s', $user->name, $reason),
                [
                    'resolution' => 'cancelled',
                    'reason' => $reason,
                ]
            );

            return $case->fresh();
        });
    }

    private function lockOpenCase(OperationCase $case): OperationCase
    {
        $locked = OperationCase::query()
            ->where('id', $case->id)
            ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->completed_at) {
            throw ValidationException::withMessages([
                'case' => 'This Vendor Follow-up is already closed.',
            ]);
        }

        return $locked;
    }

    private function activity(
        OperationCase $case,
        User $user,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        string $note,
        array $metadata = []
    ): OperationCaseActivity {
        return OperationCaseActivity::create([
            'operation_case_id' => $case->id,
            'lead_id' => $case->lead_id,
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }
}