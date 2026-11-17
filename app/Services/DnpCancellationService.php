<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DnpCancellationService
{
    public const SOURCE = 'dnp_auto_cancel';
    public const REASON = 'Automatically cancelled after 30 complete days in DNP';

    public function listingQuery()
    {
        $serviceId = Service::where('service', 'Call Not Connected')->value('id');
        return Lead::with(['client', 'representative', 'leadFollowups'])
            ->where(function ($query) use ($serviceId) {
                $query->whereHas('leadFollowups', fn ($history) => $history
                    ->where('customer_not_picked_up', true)
                    ->orWhere('contact_outcome', LeadFollowup::CONTACT_OUTCOME_NO_ANSWER)
                    ->orWhere('source', self::SOURCE));
                if ($serviceId) {
                    $query->orWhereJsonContains('service_ids', (string) $serviceId);
                }
            });
    }

    private function history(Lead $lead)
    {
        return $lead->leadFollowups->sort(function ($left, $right) {
            return strcmp($right->created_at?->format('Y-m-d H:i:s.u') ?? '', $left->created_at?->format('Y-m-d H:i:s.u') ?? '')
                ?: strcmp((string) $right->id, (string) $left->id);
        })->values();
    }

    public function markedAt(Lead $lead): ?Carbon
    {
        $marked = null;
        foreach ($this->history($lead) as $followup) {
            if (!$followup->customer_not_picked_up
                && $followup->contact_outcome !== LeadFollowup::CONTACT_OUTCOME_NO_ANSWER) {
                break;
            }
            // An explicit persisted no-answer record supplies the timestamp.
            // A legacy service selection alone cannot supply a marking date.
            $marked = $followup->created_at;
        }
        return $marked?->copy();
    }

    public function visible(Lead $lead): bool
    {
        $history = $this->history($lead);
        $business = $history->first(fn ($row) => !in_array((int) $row->status, LeadFollowup::FOLLOWUP_ONLY_STATUSES, true));
        if ((int) $business?->status === LeadFollowup::STATUS_CANCELLED) {
            return $history->takeWhile(fn ($row) => (int) $row->status === LeadFollowup::STATUS_CANCELLED
                || in_array((int) $row->status, LeadFollowup::FOLLOWUP_ONLY_STATUSES, true))
                ->contains(fn ($row) => $row->source === self::SOURCE);
        }
        if ((int) $business?->status === LeadFollowup::STATUS_RIDE_COMPLETED) {
            return false;
        }
        return $this->markedAt($lead) !== null
            || !$history->contains(fn ($row) => $row->customer_not_picked_up
                || $row->contact_outcome === LeadFollowup::CONTACT_OUTCOME_NO_ANSWER);
    }

    private function eligible(Lead $lead, Carbon $asOf): bool
    {
        $marked = $this->markedAt($lead);
        $status = app(LeadStatusTransitionService::class)->displayedBusinessStatus($lead);
        return $marked !== null && $marked->lte($asOf->copy()->subDays(30))
            && !in_array($status, [LeadFollowup::STATUS_CANCELLED, LeadFollowup::STATUS_RIDE_COMPLETED], true)
            && !app(LeadStatusTransitionService::class)->hasApprovedPayment($lead);
    }

    public function run(bool $dryRun = false, ?Carbon $asOf = null): array
    {
        $asOf = $asOf ? $asOf->copy() : now();
        $result = ['candidate_ids' => [], 'cancelled_ids' => []];
        $this->listingQuery()->chunkById(100, function ($leads) use (&$result, $dryRun, $asOf) {
            foreach ($leads as $lead) {
                if (!$this->eligible($lead, $asOf)) {
                    continue;
                }
                $result['candidate_ids'][] = $lead->id;
                if ($dryRun) {
                    continue;
                }
                $cancelled = $this->cancelIfEligible((string) $lead->id, $asOf);
                if ($cancelled) {
                    $result['cancelled_ids'][] = $lead->id;
                }
            }
        });
        return $result;
    }
    public function cancelIfEligible(string $leadId, ?Carbon $asOf = null): bool
    {
        $asOf = $asOf ? $asOf->copy() : now();

        return DB::transaction(function () use ($leadId, $asOf) {
            $fresh = Lead::whereKey($leadId)->lockForUpdate()->firstOrFail();
            $fresh->load('leadFollowups');
            if (!$this->eligible($fresh, $asOf)) {
                return false;
            }

            $previous = $this->history($fresh)->first();
            $followup = LeadFollowup::create([
                'id' => (string) Str::uuid(), 'lead_id' => $fresh->id,
                'parent_followup_id' => $previous?->id, 'status' => LeadFollowup::STATUS_CANCELLED,
                'followup_note' => self::REASON, 'source' => self::SOURCE, 'followed_by' => null,
            ]);

            // Scheduled operations use the established null-actor convention.
            $audit = app(ActivityAuditService::class)->record('lead', 'dnp_auto_cancelled', $fresh,
                ['status' => $previous?->status], ['status' => LeadFollowup::STATUS_CANCELLED],
                ['source' => self::SOURCE, 'followup_id' => $followup->id,
                    'dnp_marked_at' => $this->markedAt($fresh)?->toISOString()]);
            if (!$audit) {
                throw new \RuntimeException('DNP cancellation audit could not be recorded.');
            }
            $audit->forceFill(['actor_id' => null, 'actor_name' => null, 'actor_role' => null])->save();

            return true;
        });
    }
}
