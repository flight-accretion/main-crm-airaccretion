<?php

namespace App\Services\Kpi;

use App\Models\KpiActivityEvent;
use App\Models\KpiOutreachAssignment;
use App\Models\KpiUserAssignment;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;
use Carbon\Carbon;

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
        array $filters,
        bool $includeBreakdown = false
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

            $leadWindow =
            $this->leadActivityWindow(
                $filters['from'],
                $filters['to']
            );

        if ($leadWindow['available']) {

            $leadIds =
                $this->leadIdsByCategory(
                    $userIds,
                    $leadWindow['from'],
                    $leadWindow['to']
                );

        } else {

            $leadIds =
                $this->emptyBuckets();
        }

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
            'breakdown' => $includeBreakdown ? $this->breakdown($users->whereIn('id', $userIds)->values(), $filters, $leadWindow) : [],
            'users' => $users,
            'selected_user_id' => $selectedUserId,
            'lead_history_available' =>
    $leadWindow['available'],

'lead_history_notice' =>
    $leadWindow['notice'],
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

        $window = $this->leadActivityWindow($filters['from'], $filters['to']);
        $ids = $window['available'] ? $this->leadIdsByCategory(
            $userIds,
            $window['from'],
            $window['to']
        ) : $this->emptyBuckets();

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

    private function breakdown(Collection $users, array $filters, array $window): array
    {
        $userIds = $users->pluck('id')->all();
        $events = $window['available'] ? KpiActivityEvent::query()->where('department', 'sales')
            ->where('entity_type', 'lead')->whereIn('event_type', ['note_added', 'status_changed'])
            ->whereIn('user_id', $userIds)->whereBetween('occurred_at', [$window['from'], $window['to']])
            ->get(['user_id', 'entity_id', 'event_type', 'new_value'])->groupBy('user_id') : collect();
        $candidateIds = $events->flatten(1)->where('event_type', 'note_added')->pluck('entity_id')->unique();
        $activeIds = $candidateIds->isEmpty() ? collect() : LeadFollowup::query()
            ->whereIn('lead_id', $candidateIds)->where('status', self::ACTIVE)
            ->where('id', function ($query) {
                $query->select('latest.id')->from('lead_followups as latest')
                    ->whereColumn('latest.lead_id', 'lead_followups.lead_id')
                    ->orderByDesc('latest.created_at')->limit(1);
            })->pluck('lead_id')->unique();
        $outreach = KpiOutreachAssignment::query()->whereIn('user_id', $userIds)
            ->whereNotNull('completed_at')->whereBetween('completed_at', [$filters['from'], $filters['to']])
            ->selectRaw('user_id, COUNT(*) as total, SUM(CASE WHEN completion_type = ? THEN 1 ELSE 0 END) as dnp', ['dnp'])
            ->groupBy('user_id')->get()->keyBy('user_id');
        $asOf = $filters['to']->copy()->min(now())->toDateString();
        $assignments = KpiUserAssignment::with('template.metrics')->whereIn('user_id', $userIds)->where('active', true)
            ->whereDate('effective_from', '<=', $asOf)->where(function ($query) use ($asOf) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $asOf);
            })->orderByDesc('effective_from')->get()->unique('user_id')->keyBy('user_id');
        return $users->map(function ($user) use ($events, $activeIds, $outreach, $window, $assignments) {
            $rows = $events->get($user->id, collect());
            $notes = $rows->where('event_type', 'note_added')->pluck('entity_id')->unique();
            $completed = $rows->where('event_type', 'status_changed')->whereIn('new_value', ['3', '4'])->pluck('entity_id')->unique();
            $cancelled = $rows->where('event_type', 'status_changed')->where('new_value', '2')->pluck('entity_id')
                ->unique()->intersect($notes)->diff($completed);
            $active = $notes->intersect($activeIds)->diff($completed)->diff($cancelled);
            $total = $completed->merge($cancelled)->merge($active)->unique()->count();
            $template = optional($assignments->get($user->id))->template;
            $metric = $template && $template->active ? $template->metrics->where('active', true)->firstWhere('source_key', 'sales_outreach') : null;
            $target = $metric && (float) $metric->target_value > 0 ? (int) round($metric->target_value) : null;
            return ['user' => $user,
                'completed' => $window['available'] ? $completed->count() : null,
                'cancelled' => $window['available'] ? $cancelled->count() : null,
                'active' => $window['available'] ? $active->count() : null,
                'total' => $window['available'] ? $total : null,
                'outreach_completed' => (int) optional($outreach->get($user->id))->total,
                'outreach_dnp' => (int) optional($outreach->get($user->id))->dnp,
                'outreach_target' => $target];
        })->all();
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

        $activeLeadIds = $activeCandidateIds->isEmpty() ? collect() : LeadFollowup::query()
            ->whereIn('lead_id', $activeCandidateIds)
            ->where('status', self::ACTIVE)
            ->where('id', function ($query) {
                $query->select('latest.id')->from('lead_followups as latest')
                    ->whereColumn('latest.lead_id', 'lead_followups.lead_id')
                    ->orderByDesc('latest.created_at')->limit(1);
            })
            ->pluck('lead_id')->unique()->values();

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
        if (in_array($role, UserType::ADMIN_ROLES, true) || $role === UserType::HR) {
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

    private function leadActivityWindow(
    Carbon $from,
    Carbon $to
): array {
    $configured =
        config(
            'kpi.activity_event_cutover_at'
        );

    /*
     * If no cutover is configured,
     * preserve existing behaviour.
     */
    if (!$configured) {
        return [
            'available' => true,
            'from' => $from->copy(),
            'to' => $to->copy(),
            'notice' => null,
        ];
    }

    $cutover =
        Carbon::parse($configured);

    /*
     * Entire requested range is before
     * trustworthy KPI event tracking.
     */
    if ($to->lt($cutover)) {
        return [
            'available' => false,
            'from' => null,
            'to' => null,

            'notice' =>
                'Historical Lead Work data is not available before '
                . $cutover->format('d M Y h:i A')
                . '.',
        ];
    }

    /*
     * Selected range crosses the cutover.
     *
     * Only Lead Work is clamped.
     * Daily Outreach continues using the
     * user's complete selected range.
     */
    if ($from->lt($cutover)) {
        return [
            'available' => true,
            'from' => $cutover->copy(),
            'to' => $to->copy(),

            'notice' =>
                'Lead Work is calculated only from '
                . $cutover->format('d M Y h:i A')
                . ' because earlier KPI activity history is not reliable.',
        ];
    }

    return [
        'available' => true,
        'from' => $from->copy(),
        'to' => $to->copy(),
        'notice' => null,
    ];
}
}
