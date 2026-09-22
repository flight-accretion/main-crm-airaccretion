<?php

namespace App\Services\Operations;

use App\Models\CallSummaryIntegration;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;

class OperationsCallDashboardService
{
    public function __construct(
        private OperationsLeadEligibilityService $eligibility
    ) {
    }

    public function rows(User $viewer): Collection
    {
        $viewer->loadMissing('userType');
        $role = optional($viewer->userType)->user_type;

        $query = Lead::query()
            ->with([
                'client',
                'representative.userType',
                'leadFollowups.followedBy.userType',
                'activeOperationsAssignment.operationsUser.userType',
            ]);

        $this->eligibility->applyEligibleLeadConstraint($query);

        if ($role === UserType::OPERATIONS_EXECUTIVE) {
            $query->whereHas('activeOperationsAssignment', function ($assignmentQuery) use ($viewer) {
                $assignmentQuery->where('operations_user_id', $viewer->id);
            });
        } elseif (
            !in_array(
                $role,
                [
                    UserType::OPERATIONS_MANAGER,
                    UserType::SENIOR_OPERATIONS_MANAGER,
                    UserType::SUPER_ADMIN,
                ],
                true
            )
        ) {
            abort(403);
        }

        $leads = $query
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Lead $lead) => $this->eligibility->isEligible($lead))
            ->values();

        if ($leads->isEmpty()) {
            return collect();
        }

        $leadIds = $leads->pluck('id')->all();

        $opsFollowups = LeadFollowup::query()
            ->with(['followedBy.userType'])
            ->whereIn('lead_id', $leadIds)
            ->whereHas('followedBy.userType', function ($query) {
                $query->whereIn('user_type', UserType::OPERATIONS_ROLES);
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lead_id');

        $followupIds = $opsFollowups
            ->flatten(1)
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $integrationsByFollowup = empty($followupIds)
            ? collect()
            : CallSummaryIntegration::query()
                ->whereIn('followup_id', $followupIds)
                ->get()
                ->keyBy('followup_id');

        return $leads->map(function (Lead $lead) use ($opsFollowups, $integrationsByFollowup) {
            $leadOpsFollowups = $opsFollowups->get($lead->id, collect());
            $lastOpsFollowup = $leadOpsFollowups->first();
            $latestFollowup = $this->eligibility->latestFollowup($lead);
            $lastIntegration = $lastOpsFollowup
                ? $integrationsByFollowup->get($lastOpsFollowup->id)
                : null;

            $calledToday = $leadOpsFollowups->contains(function (LeadFollowup $followup) use ($integrationsByFollowup) {
                return $followup->created_at
                    && $followup->created_at->isToday()
                    && $integrationsByFollowup->has($followup->id);
            });

            return [
                'lead' => $lead,
                'customer_name' => optional($lead->client)->name ?: 'Customer',
                'customer_mobile' => optional($lead->client)->contact_number ?: '-',
                'salesperson' => optional($lead->representative)->name ?: 'Unassigned',
                'operations_handler' => optional(
                    optional($lead->activeOperationsAssignment)->operationsUser
                )->name ?: 'Unassigned',
                'current_status' => $latestFollowup?->status,
                'current_status_label' => $this->statusLabel($latestFollowup?->status),
                'last_ops_contact_at' => $lastOpsFollowup?->created_at,
                'last_handled_by' => optional($lastOpsFollowup?->followedBy)->name,
                'last_summary' => $lastOpsFollowup?->followup_note,
                'last_direction' => $lastIntegration?->direction,
                'call_verified' => (bool) $lastIntegration,
                'called_today' => $calledToday,
                'next_followup_date' => $lastOpsFollowup?->next_followup_date,
            ];
        });
    }

    private function statusLabel($status): string
    {
        return match ((int) $status) {
            LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED => 'Full Payment Received',
            LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED => 'Partial Payment Received',
            LeadFollowup::STATUS_CONFIRMED => 'Confirmed',
            LeadFollowup::STATUS_RESCHEDULED => 'Rescheduled',
            default => 'Status ' . (string) $status,
        };
    }
}
