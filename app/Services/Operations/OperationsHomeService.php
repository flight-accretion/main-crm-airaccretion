<?php

namespace App\Services\Operations;

use App\Models\{Lead, LeadFollowup, LeadRefund, LeadRide, OperationCase, OperationCaseActivity, Target, User, UserType, VendorRefund};
use App\Services\Kpi\KpiDashboardService;
use App\Services\TargetIncentiveService;

class OperationsHomeService
{
    public function forUser(User $actor, ?string $caseBucket = null): array
    {
        $actor->loadMissing('userType');
        $role = $actor->userType?->user_type;
        // Match the existing Operations call dashboard's manager/team scope.
        $team = in_array($role, [...UserType::ADMIN_ROLES, UserType::OPERATIONS_MANAGER, UserType::SENIOR_OPERATIONS_MANAGER], true);
        $personalKpi = in_array($role, UserType::OPERATIONS_ROLES, true);
        $today = now()->startOfDay();
        $tomorrow = $today->copy()->addDay();
        $cases = OperationCase::query();
        if (!$team) $cases->where('assigned_to', $actor->id);
        $open = (clone $cases)->whereNull('completed_at')->where('status', '!=', OperationCase::STATUS_COMPLETED);
        $leads = Lead::query();
        if (!$team) {
            $leads->where(fn ($query) => $query
                ->whereHas('activeOperationsAssignment', fn ($assignment) => $assignment->where('operations_user_id', $actor->id))
                ->orWhereIn('id', (clone $open)->select('lead_id')));
        }
        $rides = LeadRide::query()->whereIn('lead_id', (clone $leads)->select('id'))
            ->whereHas('enquiry.vouchers')->whereLeadNotCancelled()
            ->where(fn ($query) => $query->whereNull('is_tba')->orWhere('is_tba', false));
        $nextRide = (clone $rides)->where('from_date', '>=', now())->oldest('from_date')->orderBy('id')->first();
        $todayRides = (clone $rides)->where('from_date', '>=', $today)->where('from_date', '<', $tomorrow)->count();
        // latestFollowup() orders rows but its existence query can still match old statuses.
        $latestStatus = fn ($followup) => $followup->where('lead_followups.id', function ($latest) {
            $latest->select('latest.id')->from('lead_followups as latest')
                ->whereColumn('latest.lead_id', 'lead_followups.lead_id')
                ->orderByDesc('latest.created_at')->orderByDesc('latest.id')->limit(1);
        });
        $unconfirmed = (clone $rides)->whereBetween('from_date', [now(), now()->addHours(48)])
            ->whereHas('enquiry', fn ($query) => $query->whereDoesntHave('leadFollowups', fn ($followup) => $latestStatus($followup)->where('status', LeadFollowup::STATUS_CONFIRMED)))->count();
        // Use the first recorded service date; never reset the approved 90-day clock.
        $expiring = (clone $open)->where('type', OperationCase::TYPE_RESCHEDULE)
            ->where('metadata->reschedule->state', 'open')->get(['id', 'metadata'])->filter(function ($case) {
                $date = data_get($case->metadata, 'reschedule.original.from_date');
                if (!$date) return false;
                $window = app(RescheduleTrackingService::class)->window($date);
                return $window['days_left'] >= 0 && $window['days_left'] <= 10;
            })->count();
        $oldest = (clone $open)->with('lead.client')->oldest('opened_at')->orderBy('id')->limit(5)->get();
        $vendor = (clone $open)->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP);
        $calls = (clone $open)->where('type', OperationCase::TYPE_CUSTOMER_CALL);
        $review = (clone $open)->where('type', OperationCase::TYPE_REVIEW)
            ->whereHas('reviewConversation', fn ($conversation) => $conversation->whereNotNull('initial_message_sent_at')->where('customer_replied', false));
        $activities = OperationCaseActivity::query()->with(['case.lead.client', 'user'])
            ->whereIn('operation_case_id', (clone $cases)->select('id'))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
        $caseRows = null;
        if ($caseBucket) {
            $selection = (clone $open)->with('lead.client');
            if ($caseBucket === 'due') $selection->where('next_followup_at', '>=', $today)->where('next_followup_at', '<', $tomorrow);
            if ($caseBucket === 'recent') $selection->where('opened_at', '>=', $today->copy()->subDay());
            if ($caseBucket === 'week') $selection->where('opened_at', '>=', $today->copy()->subDays(7))->where('opened_at', '<', $today->copy()->subDay());
            if ($caseBucket === 'older') $selection->where('opened_at', '<', $today->copy()->subDays(7));
            $caseRows = $selection->oldest('opened_at')->orderBy('id')->paginate(25)->withQueryString();
        }
        $customerRefund = LeadRefund::where('status', 1)->whereHas('leadFollowup', fn ($followup) => $followup->whereIn('lead_id', (clone $leads)->select('id')))->sum('refund_amount');
        $vendorRefund = VendorRefund::whereIn('lead_id', (clone $leads)->select('id'))
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', VendorRefund::STATUS_COMPLETED))->sum('refund_amount');
        $partPaid = (clone $leads)->whereIn('id', (clone $rides)->whereBetween('from_date', [now(), now()->addDays(7)])->select('lead_id'))
            ->with(['latestFollowup' => fn ($followup) => $latestStatus($followup)])
            ->whereHas('leadFollowups', fn ($followup) => $latestStatus($followup)->where('status', LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED))->get();
        $kpi = ['day' => null, 'month' => null];
        if ($personalKpi) {
            $kpi['day'] = app(KpiDashboardService::class)->forUser($actor, now(), $today)['overall_score'] ?? null;
            $kpi['month'] = app(KpiDashboardService::class)->forUser($actor, now(), now()->startOfMonth())['overall_score'] ?? null;
        }
        $incentive = ['status' => $personalKpi ? 'not_configured' : 'not_selected', 'amount' => null];
        if ($personalKpi) {
            $targets = Target::where('sales_executive_id', $actor->id)->where('year', now()->year)
                ->where('month', now()->month)->where('status', 'active')->limit(2)->get();
            if ($targets->count() > 1) {
                $incentive['status'] = 'duplicate';
            } elseif ($targets->first()?->incentive_enabled) {
                $incentive = ['status' => 'configured', 'amount' => (float) app(TargetIncentiveService::class)->forTarget($targets->first())['incentive']];
            }
        }

        return compact('team', 'personalKpi', 'todayRides', 'nextRide', 'unconfirmed', 'expiring', 'oldest', 'activities', 'caseRows', 'caseBucket', 'customerRefund', 'vendorRefund', 'kpi', 'incentive') + [
            'partPaidCount' => $partPaid->count(),
            'partPaidBalance' => $partPaid->sum(fn ($lead) => max(0, (float) $lead->getRelation('latestFollowup')?->total_amount - (float) $lead->getRelation('latestFollowup')?->received_amount)),
            'openCount' => (clone $open)->count(),
            'dueToday' => (clone $open)->where('next_followup_at', '>=', $today)->where('next_followup_at', '<', $tomorrow)->count(),
            'nextDue' => (clone $open)->where('next_followup_at', '>=', now())->where('next_followup_at', '<', $tomorrow)->min('next_followup_at'),
            'age' => [
                'recent' => (clone $open)->where('opened_at', '>=', $today->copy()->subDay())->count(),
                'week' => (clone $open)->where('opened_at', '>=', $today->copy()->subDays(7))->where('opened_at', '<', $today->copy()->subDay())->count(),
                'older' => (clone $open)->where('opened_at', '<', $today->copy()->subDays(7))->count(),
            ],
            'vendorCount' => (clone $vendor)->count(),
            'vendorOverdue' => (clone $vendor)->where('next_followup_at', '<', $today)->count(),
            'vendorOldest' => (clone $vendor)->min('opened_at'),
            'reviewCount' => (clone $review)->count(),
            'reviewOldest' => (clone $review)->min('opened_at'),
            'callsCount' => (clone $calls)->count(),
            'callsOldest' => (clone $calls)->min('opened_at'),
        ];
    }
}
