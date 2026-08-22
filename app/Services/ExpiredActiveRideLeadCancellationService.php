<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExpiredActiveRideLeadCancellationService
{
    private const ACTIVE_STATUS = 1;
    private const CANCELLED_STATUS = 2;
    private const CANCEL_AFTER_DAYS = 15;

    public function cancelExpiredActiveRideLeads(?Carbon $now = null): array
    {
        $now = $now ? $now->copy() : now();
        $cutoffDate = $now->copy()->subDays(self::CANCEL_AFTER_DAYS)->startOfDay();

        $result = [
            'checked' => 0,
            'cancelled' => 0,
            'skipped' => 0,
        ];

        $this->candidateQuery()
            ->chunkById(100, function ($leads) use (&$result, $cutoffDate) {
                foreach ($leads as $lead) {
                    $result['checked']++;

                    if ($this->cancelLeadIfExpired($lead, $cutoffDate)) {
                        $result['cancelled']++;
                    } else {
                        $result['skipped']++;
                    }
                }
            });

        return $result;
    }

    private function candidateQuery()
    {
        return Lead::query()
            ->with([
                'rideSegments',
                'leadFollowups' => function ($query) {
                    $query->orderByDesc('created_at');
                },
            ])
            ->whereNotNull('representative_user_id')
            ->whereHas('rideSegments', function ($query) {
                $query->whereNotNull('from_date')
                    ->orWhereNotNull('to_date');
            })
            ->whereHas('leadFollowups', function ($query) {
                $query
                    ->where('status', self::ACTIVE_STATUS)
                    ->where('created_at', function ($subQuery) {
                        $subQuery
                            ->selectRaw('MAX(latest_followups.created_at)')
                            ->from('lead_followups as latest_followups')
                            ->whereColumn(
                                'latest_followups.lead_id',
                                'lead_followups.lead_id'
                            );
                    });
            });
    }

    private function cancelLeadIfExpired(Lead $lead, Carbon $cutoffDate): bool
    {
        $latestFollowup = $lead->leadFollowups->first();

        if (!$latestFollowup || (int) $latestFollowup->status !== self::ACTIVE_STATUS) {
            return false;
        }

        $lastRideDate = $this->lastRideDate($lead);

        if (!$lastRideDate || $lastRideDate->copy()->startOfDay()->gt($cutoffDate)) {
            return false;
        }

        if (empty($lead->representative_user_id)) {
            Log::warning('Expired active ride lead skipped because no representative is assigned.', [
                'lead_id' => $lead->id,
            ]);

            return false;
        }

        return DB::transaction(function () use ($lead, $lastRideDate) {
            $freshLatestFollowup = LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->orderByDesc('created_at')
                ->first();

            if (!$freshLatestFollowup || (int) $freshLatestFollowup->status !== self::ACTIVE_STATUS) {
                return false;
            }

            LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'parent_followup_id' => $freshLatestFollowup->id,
                'lead_id' => $lead->id,
                'next_followup_date' => now(),
                'followup_note' => $this->cancellationNote($lastRideDate),
                'status' => self::CANCELLED_STATUS,
                'followed_by' => $lead->representative_user_id,
                'file' => $freshLatestFollowup->file,
                'service_ids' => $freshLatestFollowup->service_ids,
                'extra_service_ids' => $freshLatestFollowup->extra_service_ids,
                'total_amount' => $freshLatestFollowup->total_amount,
                'received_amount' => $freshLatestFollowup->received_amount,
                'payment_method' => $freshLatestFollowup->payment_method,
                'paid_date' => $freshLatestFollowup->paid_date,
                'service_amount' => $freshLatestFollowup->service_amount,
                'discount_amount' => $freshLatestFollowup->discount_amount,
                'service_details' => $freshLatestFollowup->service_details,
            ]);

            return true;
        });
    }

    private function lastRideDate(Lead $lead): ?Carbon
    {
        $lastRideDate = null;

        foreach ($lead->rideSegments as $ride) {
            $rideDate = $ride->to_date ?: $ride->from_date;

            if (!$rideDate) {
                continue;
            }

            $rideDate = $rideDate instanceof Carbon
                ? $rideDate->copy()
                : Carbon::parse($rideDate);

            if (!$lastRideDate || $rideDate->gt($lastRideDate)) {
                $lastRideDate = $rideDate;
            }
        }

        return $lastRideDate;
    }

    private function cancellationNote(Carbon $lastRideDate): string
    {
        return sprintf(
            'Lead automatically cancelled because the last ride date (%s) passed %d days ago and the latest lead status was still Active.',
            $lastRideDate->format('d-m-Y'),
            self::CANCEL_AFTER_DAYS
        );
    }
}
