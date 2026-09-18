<?php

namespace App\Services\Kpi;

use App\Models\KpiActivityEvent;
use App\Models\KpiOutreachAssignment;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;

class KpiWorkDoneService
{
    /*
    |--------------------------------------------------------------------------
    | Lead status mapping
    |--------------------------------------------------------------------------
    */
    private const ACTIVE = 1;
    private const CANCELLED = 2;
    private const FULL_PAYMENT = 3;
    private const PARTIAL_PAYMENT = 4;

    public function dashboard(
        User $actor,
        array $filters
    ): array {
        $users = $this->allowedUsers($actor);

        $selectedUserId = $this->resolveSelectedUser(
            $actor,
            $users,
            $filters['user_id'] ?? null
        );

        $userIds = $selectedUserId
            ? [$selectedUserId]
            : $users->pluck('id')->all();

        $leadIds = $this->leadIdsByCategory(
            $userIds,
            $filters['from'],
            $filters['to']
        );

        $summary = [
            'completed' => count($leadIds['completed']),
            'active' => count($leadIds['active']),
            'cancelled' => count($leadIds['cancelled']),
            'total' => count($leadIds['total']),

            'outreach_completed' => $this->outreachCompleted(
                $userIds,
                $filters['from'],
                $filters['to']
            ),

            'outreach_dnp' => $this->outreachDnp(
                $userIds,
                $filters['from'],
                $filters['to']
            ),
        ];

        return [
            'summary' => $summary,
            'users' => $users,
            'selected_user_id' => $selectedUserId,
        ];
    }

    public function details(
        User $actor,
        string $type,
        array $filters
    ): array {
        $users = $this->allowedUsers($actor);

        $selectedUserId = $this->resolveSelectedUser(
            $actor,
            $users,
            $filters['user_id'] ?? null
        );

        $userIds = $selectedUserId
            ? [$selectedUserId]
            : $users->pluck('id')->all();

        if (in_array(
            $type,
            ['outreach_completed', 'outreach_dnp'],
            true
        )) {
            return [
                'title' => $type === 'outreach_dnp'
                    ? 'Outreach DNP'
                    : 'Outreach Completed',

                'rows' => $this->outreachRows(
                    $userIds,
                    $filters['from'],
                    $filters['to'],
                    $type
                ),
            ];
        }

        $ids = $this->leadIdsByCategory(
            $userIds,
            $filters['from'],
            $filters['to']
        );

        $leadIds = $ids[$type] ?? [];

        $rows = Lead::query()
            ->with([
                'client',
                'representative',
                'leadFollowups' => function ($query) {
                    $query->latest();
                },
            ])
            ->whereIn('id', $leadIds)
            ->get();

        return [
            'title' => match ($type) {
                'completed' => 'Completed Leads',
                'active' => 'Active Leads',
                'cancelled' => 'Cancelled Leads',
                default => 'Total Worked Leads',
            },
            'rows' => $rows,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Section 1 - Leads
    |--------------------------------------------------------------------------
    |
    | Completed:
    |     Full Payment / Partial Payment status changed during selected period.
    |
    | Active:
    |     salesperson added note during period AND lead is currently Active.
    |
    | Cancelled:
    |     note added during period AND status changed to Cancelled in same period.
    |
    | One lead can appear in only one final bucket:
    | Completed > Cancelled > Active.
    |
    */

    private function leadIdsByCategory(
        array $userIds,
        $from,
        $to
    ): array {
        if (empty($userIds)) {
            return $this->emptyBuckets();
        }

        /*
         * Human notes recorded by KPI activity recorder.
         */
        $noteLeadIds = KpiActivityEvent::query()
            ->where('department', 'sales')
            ->where('entity_type', 'lead')
            ->where('event_type', 'note_added')
            ->whereIn('user_id', $userIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->pluck('entity_id')
            ->unique()
            ->values();

        /*
         * Payment status changes during selected date.
         */
        $completedLeadIds = KpiActivityEvent::query()
            ->where('department', 'sales')
            ->where('entity_type', 'lead')
            ->where('event_type', 'status_changed')
            ->whereIn('user_id', $userIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->whereIn('new_value', [
                (string) self::FULL_PAYMENT,
                (string) self::PARTIAL_PAYMENT,
            ])
            ->pluck('entity_id')
            ->unique()
            ->values();

        /*
         * Cancelled TODAY / selected date only.
         *
         * Requirement:
         * must have a human note in the selected period and
         * cancellation status change in selected period.
         */
        $cancelStatusIds = KpiActivityEvent::query()
            ->where('department', 'sales')
            ->where('entity_type', 'lead')
            ->where('event_type', 'status_changed')
            ->whereIn('user_id', $userIds)
            ->whereBetween('occurred_at', [$from, $to])
            ->where('new_value', (string) self::CANCELLED)
            ->pluck('entity_id')
            ->unique()
            ->values();

        $cancelledLeadIds = $cancelStatusIds
            ->intersect($noteLeadIds)
            ->values();

        /*
         * Remove payment leads from Cancelled in case multiple
         * status changes occurred during the same selected period.
         */
        $cancelledLeadIds = $cancelledLeadIds
            ->diff($completedLeadIds)
            ->values();

        /*
         * Active means:
         * - person was actually worked during selected period
         * - note was added
         * - current latest LeadFollowup status is Active
         *
         * This is intentionally based on current lead outcome,
         * not merely an "Active" event sometime earlier.
         */
        $activeCandidateIds = $noteLeadIds
            ->diff($completedLeadIds)
            ->diff($cancelledLeadIds)
            ->values();

        $activeLeadIds = $activeCandidateIds
            ->filter(function ($leadId) {
                $latest = LeadFollowup::query()
                    ->where('lead_id', $leadId)
                    ->latest('created_at')
                    ->first();

                return $latest
                    && (int) $latest->status === self::ACTIVE;
            })
            ->values();

        $total = $completedLeadIds
            ->merge($cancelledLeadIds)
            ->merge($activeLeadIds)
            ->unique()
            ->values();

        return [
            'completed' => $completedLeadIds->all(),
            'active' => $activeLeadIds->all(),
            'cancelled' => $cancelledLeadIds->all(),
            'total' => $total->all(),
        ];
    }

    private function emptyBuckets(): array
    {
        return [
            'completed' => [],
            'active' => [],
            'cancelled' => [],
            'total' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Section 2 - Daily Outreach
    |--------------------------------------------------------------------------
    */

    private function outreachCompleted(
        array $userIds,
        $from,
        $to
    ): int {
        if (empty($userIds)) {
            return 0;
        }

        return KpiOutreachAssignment::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->count();
    }

    private function outreachDnp(
        array $userIds,
        $from,
        $to
    ): int {
        if (empty($userIds)) {
            return 0;
        }

        return KpiOutreachAssignment::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->where('completion_type', 'dnp')
            ->count();
    }

    private function outreachRows(
        array $userIds,
        $from,
        $to,
        string $type
    ): Collection {
        $query = KpiOutreachAssignment::query()
            ->with([
                'pool',
                'user',
            ])
            ->whereIn('user_id', $userIds)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to]);

        if ($type === 'outreach_dnp') {
            $query->where('completion_type', 'dnp');
        }

        return $query
            ->latest('completed_at')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    private function allowedUsers(User $actor): Collection
    {
        $actor->loadMissing('userType');

        $role = $actor->userType->user_type ?? '';

        /*
         * Super Admin / Admin:
         * all active Sales users.
         */
        if (in_array($role, UserType::ADMIN_ROLES, true)) {
            return User::query()
                ->with('userType')
                ->where('status', 1)
                ->whereHas('userType', function ($query) {
                    $query->whereIn(
                        'user_type',
                        UserType::SALES_ROLES
                    );
                })
                ->orderBy('name')
                ->get();
        }

        /*
         * Sales managers:
         * own data + specifically assigned executives.
         */
        if (in_array($role, [
            UserType::SALES_MANAGER,
            UserType::SENIOR_SALES_MANAGER,
        ], true)) {
            $executiveIds = SalesExecutiveAssignment::query()
                ->where('manager_id', $actor->id)
                ->where('status', 1)
                ->pluck('sales_executive_id');

            $ids = $executiveIds
                ->push($actor->id)
                ->unique()
                ->values();

            return User::query()
                ->with('userType')
                ->whereIn('id', $ids)
                ->where('status', 1)
                ->orderBy('name')
                ->get();
        }

        /*
         * Sales Executive:
         * own data only.
         */
        return collect([$actor]);
    }

    private function resolveSelectedUser(
        User $actor,
        Collection $allowedUsers,
        ?string $requestedUserId
    ): ?string {
        if (!$requestedUserId) {
            /*
             * Executive defaults to own account.
             * Manager/Admin default means aggregate visible team.
             */
            $role = $actor->userType->user_type ?? '';

            if ($role === UserType::SALES_EXECUTIVE) {
                return $actor->id;
            }

            return null;
        }

        if (!$allowedUsers->contains('id', $requestedUserId)) {
            abort(403);
        }

        return $requestedUserId;
    }
}