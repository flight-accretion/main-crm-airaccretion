<?php

namespace App\Services\Operations;

use App\Models\LeadFollowup;
use App\Models\LeadRide;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingConflictService
{
    public function available(): bool
    {
        return config('operations.booking_conflict_checks', false) && Schema::hasTable('ride_booking_resources');
    }

    public function normalize(?string $key): ?string
    {
        $key = strtoupper(preg_replace('/\s+/', '', trim((string) $key)));
        return $key === '' ? null : $key;
    }

    public function conflicts(LeadRide $ride, string $key, Carbon $start, Carbon $end): array
    {
        $rows = LeadRide::query()->where('booking_resource_key', $key)
            ->where('id', '!=', $ride->id)->whereNotNull('from_date')
            ->where(function ($q) { $q->whereNull('is_tba')->orWhere('is_tba', false); })
            ->where('from_date', '<', $end)
            ->where(function ($q) use ($start) { $q->where('to_date', '>', $start)->orWhereNull('to_date'); })
            ->whereDoesntHave('enquiry.leadFollowups', function ($q) {
                $q->where('status', LeadFollowup::STATUS_CANCELLED)->whereNotExists(function ($newer) {
                    $newer->selectRaw('1')->from('lead_followups as newer')
                        ->whereColumn('newer.lead_id', 'lead_followups.lead_id')
                        ->where(function ($latest) {
                            $latest->whereColumn('newer.created_at', '>', 'lead_followups.created_at')
                                ->orWhere(function ($tie) { $tie->whereColumn('newer.created_at', 'lead_followups.created_at')->whereColumn('newer.id', '>', 'lead_followups.id'); });
                        });
                });
            })->limit(10)->get(['id', 'from_date', 'to_date']);

        return $rows->map(fn($row) => [
            'from' => $row->from_date->format('d M Y H:i'),
            'to' => $row->to_date?->format('d M Y H:i'),
        ])->all();
    }

    public function protectSave(LeadRide $ride, callable $save)
    {
        $key = $this->normalize($ride->booking_resource_key);
        if (!$key) {
            return $save();
        }
        if (!$this->available()) {
            throw ValidationException::withMessages(['booking_resource_key' => 'Booking resource migration must be applied before assigning a resource.']);
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9._:\/-]{0,79}$/', $key)) {
            throw ValidationException::withMessages(['booking_resource_key' => 'Use a valid aircraft or vehicle identifier.']);
        }
        return DB::transaction(function () use ($ride, $key, $save) {
            // Serialize all Eloquent booking writes for this exact resource, including date edits outside this page.
            DB::table('ride_booking_resources')->insertOrIgnore(['resource_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ride_booking_resources')->where('resource_key', $key)->lockForUpdate()->first();
            $ride->booking_resource_key = $key;
            if (!$ride->is_tba && $ride->from_date) {
                if (!$ride->to_date || $ride->to_date->lte($ride->from_date)) {
                    throw ValidationException::withMessages(['to_date' => 'An assigned resource requires an end time later than its start time.']);
                }
                $conflicts = $this->conflicts($ride, $key, $ride->from_date, $ride->to_date);
                if ($conflicts) {
                    throw ValidationException::withMessages(['booking_resource_key' => 'Resource '.$key.' is already booked or has an incomplete overlapping slot. Choose another resource or time.']);
                }
            }
            return $save();
        });
    }
}
