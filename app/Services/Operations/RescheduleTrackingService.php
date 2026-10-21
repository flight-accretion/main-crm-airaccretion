<?php

namespace App\Services\Operations;

use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RescheduleTrackingService
{
    public function trip(LeadRide $ride): array
    {
        return [
            'from_date' => $ride->from_date?->toDateTimeString(),
            'to_date' => $ride->to_date?->toDateTimeString(),
            'from_place' => $ride->from_place,
            'to_place' => $ride->to_place,
            'total_time' => $ride->total_time,
            'is_tba' => (bool) $ride->is_tba,
            'booking_resource_key' => $ride->booking_resource_key,
        ];
    }

    public function original(LeadRide $ride): ?array
    {
        $previous = OperationCase::query()->where('type', OperationCase::TYPE_RESCHEDULE)
            ->where('lead_id', $ride->lead_id)->where('metadata->ride_id', $ride->id)
            ->whereNotNull('metadata->reschedule->original')->oldest('opened_at')->first();

        return data_get($previous?->metadata, 'reschedule.original');
    }

    public function save(OperationCase $case, LeadRide $ride, User $user, array $data): void
    {
        DB::transaction(function () use ($case, $ride, $user, $data) {
            $case = OperationCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $ride = LeadRide::whereKey($ride->id)->lockForUpdate()->firstOrFail();
            if ($case->completed_at) {
                throw ValidationException::withMessages(['case' => 'This case has already been closed. Refresh the list.']);
            }
            if ($case->lead_id !== $ride->lead_id || (data_get($case->metadata, 'ride_id') && data_get($case->metadata, 'ride_id') !== $ride->id)) {
                throw ValidationException::withMessages(['case' => 'The selected trip does not belong to this reschedule case.']);
            }
            $metadata = $case->metadata ?: [];
            $tracking = $metadata['reschedule'] ?? [];
            $before = $this->trip($ride);
            // Never reset the first recorded service date when a ride is moved again.
            if (!array_key_exists('original', $tracking)) {
                $tracking['original'] = $this->original($ride) ?? $before;
            }
            $noDate = !empty($data['no_date']);
            $updates = [
                'from_date' => $noDate ? null : Carbon::parse($data['from_date']),
                'to_date' => $noDate || empty($data['to_date']) ? null : Carbon::parse($data['to_date']),
                'from_place' => $data['from_place'] ?? $ride->from_place,
                'to_place' => $data['to_place'] ?? $ride->to_place,
                'total_time' => $data['total_time'] ?? $ride->total_time,
                'is_tba' => $noDate,
            ];
            if (array_key_exists('booking_resource_key', $data)) {
                $updates['booking_resource_key'] = app(BookingConflictService::class)->normalize($data['booking_resource_key']);
            }
            $ride->fill($updates);
            $after = $this->trip($ride);
            $originalDate = data_get($tracking, 'original.from_date');
            $tracking['expires_on'] = $originalDate ? Carbon::parse($originalDate)->addDays(90)->toDateString() : null;
            $tracking['state'] = $noDate ? 'open' : 'fixed';
            $tracking['latest'] = $after;
            $tracking['reason'] = $data['reason'] ?? null;
            $tracking['saved_by'] = $user->id;
            $tracking['saved_at'] = now()->toDateTimeString();
            $metadata['ride_id'] = $ride->id;
            $metadata['reschedule'] = $tracking;
            $oldStatus = $case->status;
            $case->update([
                'metadata' => $metadata,
                'note' => $data['note'] ?? $case->note,
                'status' => $noDate ? OperationCase::STATUS_IN_PROGRESS : OperationCase::STATUS_COMPLETED,
                'completed_at' => $noDate ? null : now(),
                'completed_by' => $noDate ? null : $user->id,
                'assigned_to' => $case->assigned_to ?: $user->id,
            ]);
            $ride->save();
            OperationCaseActivity::create([
                'operation_case_id' => $case->id, 'lead_id' => $case->lead_id,
                'user_id' => $user->id, 'action' => $noDate ? 'reschedule_updated' : 'completed',
                'from_status' => $oldStatus, 'to_status' => $case->status,
                'note' => $data['note'] ?? null,
                'metadata' => ['before' => $before, 'after' => $after, 'reason' => $tracking['reason']],
            ]);
            app(RescheduleWindowService::class)->sync($case);
        });
    }

    public function reconcileRide(LeadRide $ride): void
    {
        if (!$ride->from_date || $ride->is_tba || !Schema::hasTable('operation_cases')) return;
        DB::transaction(function () use ($ride) {
            $cases = OperationCase::where('type', OperationCase::TYPE_RESCHEDULE)
                ->where('lead_id', $ride->lead_id)->where('metadata->ride_id', $ride->id)
                ->where('metadata->reschedule->state', 'open')->whereNull('completed_at')
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($cases as $case) {
                $metadata = $case->metadata;
                $before = data_get($metadata, 'reschedule.latest');
                $metadata['reschedule']['state'] = 'fixed';
                $metadata['reschedule']['latest'] = $this->trip($ride);
                $metadata['reschedule']['saved_at'] = now()->toDateTimeString();
                $metadata['reschedule']['saved_by'] = auth()->id();
                $previousStatus = $case->status;
                $case->update(['metadata' => $metadata, 'status' => OperationCase::STATUS_COMPLETED,
                    'completed_at' => now(), 'completed_by' => auth()->id(), 'next_followup_at' => null]);
                OperationCaseActivity::create([
                    'operation_case_id' => $case->id, 'lead_id' => $case->lead_id, 'user_id' => auth()->id(),
                    'action' => 'completed', 'from_status' => $previousStatus, 'to_status' => $case->status,
                    'note' => 'New service date saved from the existing trip editor.',
                    'metadata' => ['before' => $before, 'after' => $this->trip($ride)],
                ]);
                app(RescheduleWindowService::class)->closeLinkedTasks($case);
            }
        });
    }

    public function window(?string $date): array
    {
        if (!$date) {
            return ['expires_on' => null, 'days_left' => null];
        }
        $expiry = Carbon::parse($date)->startOfDay()->addDays(90);
        return ['expires_on' => $expiry, 'days_left' => (int) now()->startOfDay()->diffInDays($expiry, false)];
    }
}
