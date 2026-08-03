<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\User;
use App\Models\Target;
use App\Models\Service;
use App\Models\Product;
use App\Models\LeadRide;
use App\Models\UserType;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Services\LeadAllocationService;
use App\Models\SalesDailyUpdate;
use Illuminate\Http\Request;
use function App\Helpers\getRepresentativeIds;

class DashboardController extends Controller
{
    private const TARGET_WORKING_DAYS_TOTAL = 26;

    private function resolveRepresentativeIds(User $currentUser, ?string $userId = null)
    {
        $userType = $currentUser->userType->user_type;

        if ($userId) {
            $targetUser = User::with('userType')->find($userId);
            if (!$targetUser) {
                return null;
            }

            $targetType = $targetUser->userType->user_type ?? null;

            if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                if ($targetType === UserType::SALES_EXECUTIVE) {
                    if (!\App\Models\SalesExecutiveAssignment::isAssigned($currentUser->id, $userId)) {
                        return false;
                    }
                } elseif (in_array($targetType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                    if ($userId != $currentUser->id) {
                        return false;
                    }
                }
            }

            return [$userId];
        }

        if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $assigned = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
            $ids = $assigned->pluck('id')->toArray();
            $ids = array_unique(array_merge($ids, [$currentUser->id]));
            return $ids ?: [$currentUser->id];
        }

        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
            $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
            $execTypeId = $execType ? $execType->id : null;

            $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
            $ids = !empty($allTypes) ? User::whereIn('user_type_id', $allTypes)->where('status', 1)->pluck('id')->toArray() : [];
            return $ids ?: [];
        }

        return [$currentUser->id];
    }

    private function resolveTargetPeriod(?Request $request = null): array
    {
        $now = Carbon::now();
        $year = (int) ($request && $request->filled('target_year') ? $request->input('target_year') : $now->year);
        $month = (int) ($request && $request->filled('target_month') ? $request->input('target_month') : $now->month);

        if ($request && $request->filled('from_date') && !$request->filled('target_year') && !$request->filled('target_month')) {
            try {
                $fromDate = Carbon::parse($request->input('from_date'));
                $year = (int) $fromDate->year;
                $month = (int) $fromDate->month;
            } catch (\Throwable $e) {
                $year = (int) $now->year;
                $month = (int) $now->month;
            }
        }

        return [
            'year' => $year > 0 ? $year : (int) $now->year,
            'month' => $month >= 1 && $month <= 12 ? $month : (int) $now->month,
        ];
    }

    private function resolveTodayClosed(?Request $request = null): bool
    {
        return $request && $request->has('today_closed') ? $request->boolean('today_closed') : false;
    }

    private function calculateWorkingDayStats(int $year, int $month, bool $todayClosed): array
    {
        $now = Carbon::now();
        $workingDaysTotal = self::TARGET_WORKING_DAYS_TOTAL;
        $isCurrentMonth = (int) $now->year === $year && (int) $now->month === $month;
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();

        if ($periodStart->gt($now)) {
            $workingDaysCompleted = 0;
            $remainingDays = $workingDaysTotal;
        } elseif ($isCurrentMonth) {
            $workingDaysCompleted = min($workingDaysTotal, max(0, (int) $now->day));
            $remainingDays = $todayClosed
                ? $workingDaysTotal - $workingDaysCompleted
                : $workingDaysTotal - $workingDaysCompleted + 1;
        } else {
            $workingDaysCompleted = $workingDaysTotal;
            $remainingDays = 0;
        }

        return [
            'working_days_total' => $workingDaysTotal,
            'working_days_completed' => max(0, $workingDaysCompleted),
            'remaining_days' => max(0, $remainingDays),
            'today_closed' => $todayClosed,
        ];
    }

    private function calculateAchievedAmountForRepIds(array $repIds, int $year, int $month, ?Carbon $paidDate = null): float
    {
        $repIds = array_values(array_filter(array_unique($repIds)));
        if (empty($repIds)) {
            return 0.0;
        }

        $paidFollowupQuery = PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $year)
            ->whereMonth('paid_date', $month);

        if ($paidDate) {
            $paidFollowupQuery->whereDate('paid_date', $paidDate->toDateString());
        }

        $paidFollowupIds = $paidFollowupQuery->pluck('lead_followup_id')->unique();
        if ($paidFollowupIds->isEmpty()) {
            return 0.0;
        }

        $candidateLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($query) use ($repIds) {
                $query->whereIn('representative_user_id', $repIds);
            })
            ->pluck('lead_id')
            ->unique();

        if ($candidateLeadIds->isEmpty()) {
            return 0.0;
        }

        $allFollowupIdsByLead = LeadFollowup::whereIn('lead_id', $candidateLeadIds)
            ->get(['id', 'lead_id'])
            ->groupBy('lead_id')
            ->map(fn($group) => $group->pluck('id'));

        $allFollowupIdsFlat = $allFollowupIdsByLead->flatten()->unique();
        if ($allFollowupIdsFlat->isEmpty()) {
            return 0.0;
        }

        $firstPaymentPerLead = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsFlat)
            ->where('payment_status', 1)
            ->orderBy('paid_date')
            ->get()
            ->groupBy(function ($payment) use ($allFollowupIdsByLead) {
                foreach ($allFollowupIdsByLead as $leadId => $followupIds) {
                    if ($followupIds->contains($payment->lead_followup_id)) {
                        return $leadId;
                    }
                }
                return null;
            })
            ->map(fn($payments) => $payments->sortBy('paid_date')->first());

        $paidLeadIds = $firstPaymentPerLead->filter(function ($firstPayment) use ($year, $month, $paidDate) {
            if (!$firstPayment || empty($firstPayment->paid_date)) {
                return false;
            }

            $date = Carbon::parse($firstPayment->paid_date);
            if ($paidDate) {
                return $date->isSameDay($paidDate);
            }

            return (int) $date->year === $year && (int) $date->month === $month;
        })->keys();

        if ($paidLeadIds->isEmpty()) {
            return 0.0;
        }

        $allFollowups = LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($query) use ($repIds) {
                $query->whereIn('representative_user_id', $repIds);
            })
            ->get();

        return (float) $allFollowups->groupBy('lead_id')->map(function ($group) {
            $qualifying = $group->filter(function ($followup) {
                return in_array($followup->status, [2, 5, 7, 8]);
            });

            return $qualifying->sortByDesc('created_at')->first();
        })->filter()->sum(function ($followup) {
            $allFollowupIdsForLead = LeadFollowup::where('lead_id', $followup->lead_id)->pluck('id');
            $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->whereIn('status', [1, 2])
                ->sum('refund_amount');

            return max(0, (float) $followup->total_amount - $refund);
        });
    }

    private function calculateTargetProgressForRepIds(array $repIds, int $year, int $month, bool $todayClosed = false): array
    {
        $repIds = array_values(array_filter(array_unique($repIds)));
        $targetAmount = empty($repIds) ? 0.0 : (float) Target::whereIn('sales_executive_id', $repIds)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'active')
            ->sum('target_amount');

        $salesDoneTillNow = $this->calculateAchievedAmountForRepIds($repIds, $year, $month);
        $isCurrentMonth = (int) Carbon::now()->year === $year && (int) Carbon::now()->month === $month;
        $todaySalesAmount = $isCurrentMonth
            ? $this->calculateAchievedAmountForRepIds($repIds, $year, $month, Carbon::today())
            : 0.0;

        $workingStats = $this->calculateWorkingDayStats($year, $month, $todayClosed);
        $remainingTarget = $targetAmount - $salesDoneTillNow;

        if ($remainingTarget <= 0) {
            $requiredDailyTarget = 0.0;
            $status = 'Target achieved';
        } elseif ($workingStats['remaining_days'] <= 0) {
            $requiredDailyTarget = $remainingTarget;
            $status = 'Period over / target missed';
        } else {
            $requiredDailyTarget = $remainingTarget / $workingStats['remaining_days'];
            $status = 'Active';
        }

        $attainmentPercent = $targetAmount > 0 ? round(($salesDoneTillNow / $targetAmount) * 100, 2) : 0.0;
        $currentRunRate = $workingStats['working_days_completed'] > 0
            ? $salesDoneTillNow / $workingStats['working_days_completed']
            : 0.0;
        $projectedMonthEndSales = $currentRunRate * $workingStats['working_days_total'];
        $dailyProgressPercent = $requiredDailyTarget > 0
            ? round(($todaySalesAmount / $requiredDailyTarget) * 100, 2)
            : ($todaySalesAmount > 0 ? 100.0 : 0.0);

        $monthlyProgress = [
            'achievement_percentage' => $attainmentPercent,
            'remaining_amount' => max(0, $remainingTarget),
            'target_amount' => $targetAmount,
            'achieved_amount' => max(0, $salesDoneTillNow),
            'sales_amount' => max(0, $salesDoneTillNow),
        ];

        $dailyProgress = [
            'achievement_percentage' => $dailyProgressPercent,
            'remaining_amount' => max(0, $requiredDailyTarget - $todaySalesAmount),
            'target_amount' => max(0, $requiredDailyTarget),
            'achieved_amount' => max(0, $todaySalesAmount),
            'sales_amount' => max(0, $todaySalesAmount),
        ];

        return array_merge($monthlyProgress, $workingStats, [
            'month_name' => Carbon::create($year, $month, 1)->format('F'),
            'year' => $year,
            'required_daily_target' => max(0, $requiredDailyTarget),
            'target_status' => $status,
            'status' => $status,
            'attainment_percent' => $attainmentPercent,
            'current_run_rate' => max(0, $currentRunRate),
            'projected_month_end_sales' => max(0, $projectedMonthEndSales),
            'gap_vs_target' => $targetAmount - $salesDoneTillNow,
            'today_sales_amount' => max(0, $todaySalesAmount),
            'monthly_progress' => $monthlyProgress,
            'daily_progress' => $dailyProgress,
            'target_calculation' => [
                'monthly_target' => $targetAmount,
                'sales_done_till_now' => max(0, $salesDoneTillNow),
                'working_days_total' => $workingStats['working_days_total'],
                'working_days_completed' => $workingStats['working_days_completed'],
                'today_closed' => $workingStats['today_closed'],
                'remaining_days' => $workingStats['remaining_days'],
                'remaining_target' => $remainingTarget,
                'required_daily_target' => max(0, $requiredDailyTarget),
                'status' => $status,
                'attainment_percent' => $attainmentPercent,
                'current_run_rate' => max(0, $currentRunRate),
                'projected_month_end_sales' => max(0, $projectedMonthEndSales),
                'gap_vs_target' => $targetAmount - $salesDoneTillNow,
            ],
        ]);
    }

    private function buildTeamMemberProgress(array $repIds, int $year, int $month, bool $todayClosed = false)
    {
        $repIds = array_values(array_filter(array_unique($repIds)));
        if (empty($repIds)) {
            return collect();
        }

        return User::whereIn('id', $repIds)
            ->where('status', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($member) use ($year, $month, $todayClosed) {
                $progress = $this->calculateTargetProgressForUser($member, $year, $month, $todayClosed);
                $progress['user_id'] = $member->id;
                $progress['user_name'] = $member->name;

                return $progress;
            })
            ->values();
    }

    private function calculateTargetProgressForUser(User $user, ?int $year = null, ?int $month = null, bool $todayClosed = false): array
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('n');
        $progress = $this->calculateTargetProgressForRepIds([$user->id], $year, $month, $todayClosed);
        $progress['user_name'] = $user->name;

        return $progress;
    }

    public function getSalesDashboard(Request $request)
    {

        $currentUser = auth()->user();
        $userType = $currentUser->userType->user_type;
        $targetPeriod = $this->resolveTargetPeriod($request);
        $todayClosed = $this->resolveTodayClosed($request);
        $currentYear = $targetPeriod['year'];
        $currentMonthNumber = $targetPeriod['month'];

        // Get current month target for Sales Executives
        $currentMonthTarget = null;
        $targetProgress = null;
        if ($userType === UserType::SALES_EXECUTIVE) {
            $currentMonthTarget = Target::where('sales_executive_id', $currentUser->id)
                ->where('year', $currentYear)
                ->where('month', $currentMonthNumber)
                ->where('status', 'active')
                ->first();

            if ($currentMonthTarget) {
                $targetProgressData = $this->calculateTargetProgressForUser($currentUser, $currentYear, $currentMonthNumber, $todayClosed);
                $targetProgress = $targetProgressData;

                $currentMonthTarget->update(['achieved_amount' => $targetProgressData['achieved_amount']]);
                $currentMonthTarget = $currentMonthTarget->fresh();
            }
        }

        // If current user is a Sales Manager (or Senior), prepare assigned executives and team progress
        $assignedExecutives = collect();
        $teamTargetProgress = null;
        $teamMemberProgress = collect();
        if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);

            $assignedIds = $assignedExecutives->pluck('id')->toArray();
            $ids = array_unique(array_merge($assignedIds, [$currentUser->id]));
            if (!empty($ids)) {
                $teamTargetProgress = $this->calculateTargetProgressForRepIds($ids, $currentYear, $currentMonthNumber, $todayClosed);
                $teamMemberProgress = $this->buildTeamMemberProgress($ids, $currentYear, $currentMonthNumber, $todayClosed);
            } else {
                $teamTargetProgress = $this->calculateTargetProgressForRepIds([], $currentYear, $currentMonthNumber, $todayClosed);
            }
        }

        // If current user is Admin/Super Admin, prepare a full list of managers + sales executives
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            // fetch user_type ids for managers and executives
            $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
            $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
            $execTypeId = $execType ? $execType->id : null;

            $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);

            $assignedExecutives = User::whereIn('user_type_id', $allTypes)->where('status', 1)->get();

            $ids = $assignedExecutives->pluck('id')->toArray();
            // include managers and executives targets in overall totals
            if (!empty($ids)) {
                $teamTargetProgress = $this->calculateTargetProgressForRepIds($ids, $currentYear, $currentMonthNumber, $todayClosed);
                $teamMemberProgress = $this->buildTeamMemberProgress($ids, $currentYear, $currentMonthNumber, $todayClosed);
            } else {
                $teamTargetProgress = $this->calculateTargetProgressForRepIds([], $currentYear, $currentMonthNumber, $todayClosed);
            }
        }
        // Keep an unfiltered copy for dropdowns that should show full list (product summary etc.)
        $assignedExecutivesAll = $assignedExecutives instanceof \Illuminate\Support\Collection ? $assignedExecutives->values() : collect($assignedExecutives);

        $currentDate = Carbon::now()->toDateString();
        $currentMonth = Carbon::now()->format('F Y');
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $currentMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $nextMonthStart = Carbon::now()->addMonth()->startOfMonth()->toDateString();

        // Get "Call Not Connected" service id
        $dnpService = Service::where('service', 'Call Not Connected')->first();
        $dnpServiceId = $dnpService ? $dnpService->id : null;

        $representatives = getRepresentativeIds($currentUser);

        // Start with leads instead of clients to get all leads
        $leadQuery = Lead::with(['client', 'representative', 'rideSegments', 'leadFollowups.followedBy']);
        //->whereNotNull('service_ids')
        // ->whereRaw("service_ids::text != '[]'")
        // ->whereHas('rideSegments');

        if ($representatives) {
            $leadQuery->whereIn('representative_user_id', $representatives);
        }

        // Filter non connected calls
        if ($dnpServiceId) {
            $leadQuery->whereRaw("
                replace(trim(both '\"' from service_ids::text), '\\', '') NOT LIKE ?
            ", ['%' . $dnpServiceId . '%']);
        }

        // if ($request->filled('representative_user_id')) {
        //     $leadQuery->where('representative_user_id', $request->representative_user_id);
        // }

        // Previous month leads
        $previousMonthCount = (clone $leadQuery)
            ->whereDate('created_at', '>=', $previousMonthStart)
            ->whereDate('created_at', '<=', $currentMonthStart)
            ->count();


        // Current month leads
        $currentMonthCount = (clone $leadQuery)
            ->whereDate('created_at', '>=', $currentMonthStart)
            ->whereDate('created_at', '<=', $nextMonthStart)
            ->count();

        // Calculate percentage change
        if ($previousMonthCount == 0) {
            $percentageChange = $currentMonthCount > 0 ? 100 : 0;
        } else {
            $percentageChange = (($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100;
        }

        // Format as positive or negative
        $percentageChange = round($percentageChange, 1);

        // All leads
        $leads = [
            'leadsCount' => $currentMonthCount,
            'percentageChange' => $percentageChange,
        ];

        // Get upcoming followups
        $services = Service::where('status', 1)->get();

        // Base query — filter by representative
        $followUpQuery = LeadFollowup::with(['enquiry', 'enquiry.representative', 'enquiry.client']);
        if ($representatives) {
            $followUpQuery->whereHas('enquiry', function ($q) use ($representatives) {
                $q->whereIn('representative_user_id', $representatives);
            });
        }

        // ── STEP 1: Get candidate lead IDs (today + missed) in 2 queries ──────
        $todayLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '=', $currentDate)
            ->whereNotIn('status', [2, 5])
            ->pluck('lead_id')
            ->unique();

        $missedLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '<', $currentDate)
            ->whereIn('status', [0, 1, 4])
            ->pluck('lead_id')
            ->unique();

        $allLeadIds = $todayLeadIds->merge($missedLeadIds)->unique()->values();

        // ── STEP 2: Bulk fetch absolute latest followup per lead (ONE query) ───
        // Use a subquery to get max created_at per lead, then join back
        $latestFollowups = collect();
        if ($allLeadIds->isNotEmpty()) {
            // Get all followups for these leads, ordered so we can pick latest per lead
            $allFollowupsForLeads = LeadFollowup::with(['enquiry', 'enquiry.representative', 'enquiry.client'])
                ->whereIn('lead_id', $allLeadIds)
                ->orderByDesc('next_followup_date')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('lead_id')
                ->map(fn($group) => $group->first()); // first = latest due to ordering

            // Apply same rules as UpcomingFollowUpController
            foreach ($allFollowupsForLeads as $leadId => $latest) {
                if (in_array($latest->status, [2, 5])) continue;
                if (!$latest->next_followup_date) continue;

                $latestDate = $latest->next_followup_date->toDateString();
                if ($latestDate === $currentDate) {
                    $latest->is_missed = false;
                    $latestFollowups->push($latest);
                } elseif ($latestDate < $currentDate && in_array($latest->status, [0, 1, 4])) {
                    $latest->is_missed = true;
                    $latestFollowups->push($latest);
                }
                // Future date → skip
            }
        }

        // ── STEP 3: Sort — today first (asc), missed after (desc) ────────────
        $notMissed = $latestFollowups->filter(fn($f) => !$f->is_missed)
            ->sortBy(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MAX);
        $missed = $latestFollowups->filter(fn($f) => $f->is_missed)
            ->sortByDesc(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MIN);

        $allSorted = $notMissed->values()->merge($missed->values());

        // Count = full total (what we send in WhatsApp and show in count box)
        $todayFollowUpsCount = $allSorted->count();

        // Table = top 5 only
        $todayFollowUps = $allSorted->take(5)->values();

        // Build the dropdown list for today's follow-ups.
        // Requirement: show the related sales executives in the dropdown, and if a sales executive
        // has no follow-ups today still show them in the dropdown (so the dropdown is never empty).
        try {
            $todayRepIds = $todayFollowUps->map(function ($f) {
                // representative may be eager-loaded or stored on enquiry
                return $f->enquiry->representative->id ?? $f->enquiry->representative_user_id ?? null;
            })->filter()->unique()->values()->toArray();

            // Make a union: include those who have follow-ups today PLUS all assigned executives.
            // This ensures the dropdown contains all assigned executives even if they have no data today.
            if ($assignedExecutives instanceof \Illuminate\Support\Collection && $assignedExecutives->count() > 0) {
                // Representatives who have follow-ups today (may be subset)
                $withFollowups = $assignedExecutives->filter(function ($u) use ($todayRepIds) {
                    return in_array($u->id, $todayRepIds);
                })->values();

                // Merge with full assigned list to ensure everyone appears; then unique by id and reindex
                $assignedExecutivesToday = $assignedExecutives->merge($withFollowups)->unique('id')->values();
            } else {
                // Fallback to the unfiltered master list (could be empty collection)
                $assignedExecutivesToday = $assignedExecutives instanceof \Illuminate\Support\Collection ? $assignedExecutives->values() : collect();
            }
        } catch (\Throwable $e) {
            // if anything goes wrong, fall back to the unfiltered master list
            $assignedExecutivesToday = $assignedExecutives instanceof \Illuminate\Support\Collection ? $assignedExecutives->values() : collect();
        }

        // Get DNP leads
        $dnpLeads = [];
        $nextWeekDnpLeads = [];
        if ($dnpServiceId) {
            $dnpQuery = Lead::with(['client', 'representative'])
                ->whereRaw("
                replace(trim(both '\"' from service_ids::text), '\\', '') LIKE ?
                ", ['%' . $dnpServiceId . '%'])
                ->whereDate('updated_at', '>=', $currentMonthStart)
                ->whereDate('updated_at', '<=', $nextMonthStart)
                ->orderby('updated_at', 'desc');

            if ($representatives) {
                $dnpQuery->whereIn('representative_user_id', $representatives);
            }

            $dnpLeads = (clone $dnpQuery)->get();

            $nextWeekDnpLeads = (clone $dnpQuery)->limit(7)->get();
        }

        // Product Statuses – use LeadFollowup (same logic as KPI export)
        $followupProductQuery = LeadFollowup::with(['enquiry.representative', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        if ($representatives) {
            $followupProductQuery->whereHas('enquiry', function ($q) use ($representatives) {
                $q->whereIn('representative_user_id', $representatives);
            });
        }

        // By Service Date: followups whose lead has ride segments entirely in current month
        // (matches KPI export logic: from_date >= monthStart AND to_date <= monthEnd)
        $currentMonthEnd = Carbon::now()->endOfMonth()->endOfDay();
        $byServiceDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry.rideSegments', function ($q) use ($currentMonthStart, $currentMonthEnd) {
                $q->where('from_date', '>=', $currentMonthStart)
                    ->where('to_date', '<=', $currentMonthEnd);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // By Created Date: followups whose LEAD was created in current month (matches KPI report)
        $byCreatedDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry', function ($q) use ($currentMonthStart, $nextMonthStart) {
                $q->where('created_at', '>=', $currentMonthStart)
                    ->where('created_at', '<', $nextMonthStart);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $dataByServiceDate = $this->buildProductSummaryFromFollowups($byServiceDateFollowups);
        $dataByCreatedDate = $this->buildProductSummaryFromFollowups($byCreatedDateFollowups);

        $productSummary = [
            'dataByProductDate' => $dataByServiceDate,
            'dataByCreatedDate' => $dataByCreatedDate
        ];

        if ($currentUser->userType && in_array($currentUser->userType->user_type, [UserType::SALES_EXECUTIVE, UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $currentUser->forceFill(['last_login' => now()])->save();
        }

        $popupData = app(LeadAllocationService::class)->getPopupData($currentUser, now());

        $dailyUpdate = null;
        $managerUpdates = collect();
        $manager = null;

        if ($currentUser->userType && in_array($currentUser->userType->user_type, [UserType::SALES_EXECUTIVE])) {
            $dailyUpdate = SalesDailyUpdate::where('user_id', $currentUser->id)
                ->whereDate('update_date', Carbon::today())
                ->first();

            $manager = $currentUser->assignedManagers()->first();
        }

        if ($currentUser->userType && in_array($currentUser->userType->user_type, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $managerUpdates = SalesDailyUpdate::with('user')
                ->whereDate('update_date', Carbon::today())
                ->where('manager_id', $currentUser->id)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('admin.pages.dashboards.sales-dashboard', compact('currentMonth', 'leads', 'todayFollowUps', 'todayFollowUpsCount', 'services', 'dnpLeads', 'nextWeekDnpLeads', 'productSummary', 'currentMonthTarget', 'targetProgress', 'assignedExecutives', 'teamTargetProgress', 'teamMemberProgress', 'assignedExecutivesAll', 'assignedExecutivesToday', 'popupData', 'dailyUpdate', 'managerUpdates', 'manager'));
    }

    public function acceptPopup(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        app(LeadAllocationService::class)->acceptPopup($user);

        return redirect()->route('admin.sales-dashboard')->with('success', 'Availability updated.');
    }

    public function declinePopup(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        app(LeadAllocationService::class)->declinePopup($user);

        return redirect()->route('admin.sales-dashboard')->with('success', 'Availability updated.');
    }

    public function storeDailyUpdate(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $request->validate([
            'task_summary' => 'required|string|max:2000',
            'work_hours' => 'required|numeric|min:0|max:24',
        ]);

        $manager = $user->assignedManagers()->first();

        SalesDailyUpdate::updateOrCreate(
            [
                'user_id' => $user->id,
                'update_date' => Carbon::today()->toDateString(),
            ],
            [
                'manager_id' => $manager?->id,
                'task_summary' => $request->input('task_summary'),
                'work_hours' => round((float) $request->input('work_hours'), 2),
                'status' => 'submitted',
            ]
        );

        return redirect()->route('admin.sales-dashboard')->with('success', 'Your daily update has been sent to your manager.');
    }

    /**
     * AJAX: Return product x status summary filtered by representative / team
     * - if `user_id` provided: return data for that user (manager or executive) according to authorization rules
     * - if no `user_id`: for managers return team (assigned executives + manager), for admins return overall (all execs), for execs return themselves
     */
    public function getProductSummaryData(Request $request)
    {
        $currentUser = auth()->user();
        $userType = $currentUser->userType->user_type;

        $userId = $request->get('user_id');

        // Authorization & resolution of representative ids to include in the query
        $resolveRepIds = function () use ($currentUser, $userType, $userId) {
            // If specific user requested - validate and return that single id
            if ($userId) {
                $targetUser = User::with('userType')->find($userId);
                if (!$targetUser) {
                    return null;
                }

                $targetType = $targetUser->userType->user_type ?? null;

                // Sales Manager may query their assigned executives and themselves only
                if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                    if ($targetType === UserType::SALES_EXECUTIVE) {
                        if (!\App\Models\SalesExecutiveAssignment::isAssigned($currentUser->id, $userId)) {
                            return false; // unauthorized
                        }
                    } elseif (in_array($targetType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                        if ($userId != $currentUser->id) {
                            return false; // unauthorized
                        }
                    }
                }

                // Admins can query anyone. Sales executives can only query themselves if they pass user_id.
                return [$userId];
            }

            // No user_id: build default set based on current user's role
            if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                $assigned = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
                $ids = $assigned->pluck('id')->toArray();
                $ids = array_unique(array_merge($ids, [$currentUser->id]));
                return $ids ?: [$currentUser->id];
            }

            if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
                $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
                $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
                $execTypeId = $execType ? $execType->id : null;

                $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
                $ids = !empty($allTypes) ? User::whereIn('user_type_id', $allTypes)->where('status', 1)->pluck('id')->toArray() : [];
                return $ids ?: [];
            }

            // Sales executives: only themselves
            return [$currentUser->id];
        };

        $repIds = $resolveRepIds();
        if ($repIds === null) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($repIds === false) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Use same month range as dashboard
        $currentMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $currentMonthEnd = Carbon::now()->endOfMonth()->endOfDay();
        $nextMonthStart = Carbon::now()->addMonth()->startOfMonth()->toDateString();

        // Use LeadFollowup (same logic as KPI export) for consistency
        $followupProductQuery = LeadFollowup::with(['enquiry.representative', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        if (!empty($repIds)) {
            $followupProductQuery->whereHas('enquiry', function ($q) use ($repIds) {
                $q->whereIn('representative_user_id', $repIds);
            });
        }

        // By Service Date: followups whose lead has ride segments entirely in current month
        // (matches KPI export logic: from_date >= monthStart AND to_date <= monthEnd)
        $byServiceDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry.rideSegments', function ($q) use ($currentMonthStart, $currentMonthEnd) {
                $q->where('from_date', '>=', $currentMonthStart)
                    ->where('to_date', '<=', $currentMonthEnd);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        // By Created Date: followups whose LEAD was created in current month (matches KPI report)
        $byCreatedDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry', function ($q) use ($currentMonthStart, $nextMonthStart) {
                $q->where('created_at', '>=', $currentMonthStart)
                    ->where('created_at', '<', $nextMonthStart);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $dataByServiceDate = $this->buildProductSummaryFromFollowups($byServiceDateFollowups);
        $dataByCreatedDate = $this->buildProductSummaryFromFollowups($byCreatedDateFollowups);

        return response()->json(['data' => [
            'dataByProductDate' => $dataByServiceDate,
            'dataByCreatedDate' => $dataByCreatedDate,
        ]]);
    }

    /**
     * AJAX: Return today's follow-ups (including missed) filtered by representative/team.
     * Accepts optional `user_id` query param. Authorization mirrors target-progress rules.
     */
    public function getSalesDashboardOverviewData(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $userId = $request->get('user_id');
        $repIds = $this->resolveRepresentativeIds($currentUser, $userId);
        if ($repIds === null) {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($repIds === false) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $currentDate = Carbon::now()->toDateString();
        $currentMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $currentMonthEnd = Carbon::now()->endOfMonth()->endOfDay();
        $nextMonthStart = Carbon::now()->addMonth()->startOfMonth()->toDateString();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $targetPeriod = $this->resolveTargetPeriod($request);
        $todayClosed = $this->resolveTodayClosed($request);

        $leadQuery = Lead::with(['client', 'representative', 'rideSegments', 'leadFollowups.followedBy']);
        if (!empty($repIds)) {
            $leadQuery->whereIn('representative_user_id', $repIds);
        }

        $dnpService = Service::where('service', 'Call Not Connected')->first();
        $dnpServiceId = $dnpService ? $dnpService->id : null;

        $previousMonthCount = (clone $leadQuery)
            ->whereDate('created_at', '>=', $previousMonthStart)
            ->whereDate('created_at', '<=', $currentMonthStart)
            ->count();

        $currentMonthCount = (clone $leadQuery)
            ->whereDate('created_at', '>=', $currentMonthStart)
            ->whereDate('created_at', '<=', $nextMonthStart)
            ->count();

        $percentageChange = $previousMonthCount == 0
            ? ($currentMonthCount > 0 ? 100 : 0)
            : (($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100;

        $followUpQuery = LeadFollowup::with(['enquiry', 'enquiry.representative', 'enquiry.client']);
        if (!empty($repIds)) {
            $followUpQuery->whereHas('enquiry', function ($q) use ($repIds) {
                $q->whereIn('representative_user_id', $repIds);
            });
        }

        $todayLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '=', $currentDate)
            ->whereNotIn('status', [2, 5])
            ->pluck('lead_id')
            ->unique();

        $missedLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '<', $currentDate)
            ->whereIn('status', [0, 1, 4])
            ->pluck('lead_id')
            ->unique();

        $allLeadIds = $todayLeadIds->merge($missedLeadIds)->unique()->values();
        $latestFollowups = collect();
        if ($allLeadIds->isNotEmpty()) {
            $allFollowupsForLeads = LeadFollowup::with(['enquiry', 'enquiry.representative', 'enquiry.client'])
                ->whereIn('lead_id', $allLeadIds)
                ->orderByDesc('next_followup_date')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('lead_id')
                ->map(fn($group) => $group->first());

            foreach ($allFollowupsForLeads as $latest) {
                if (in_array($latest->status, [2, 5])) continue;
                if (!$latest->next_followup_date) continue;

                $latestDate = $latest->next_followup_date->toDateString();
                if ($latestDate === $currentDate) {
                    $latest->is_missed = false;
                    $latestFollowups->push($latest);
                } elseif ($latestDate < $currentDate && in_array($latest->status, [0, 1, 4])) {
                    $latest->is_missed = true;
                    $latestFollowups->push($latest);
                }
            }
        }

        $notMissed = $latestFollowups->filter(fn($f) => !$f->is_missed)->sortBy(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MAX);
        $missed = $latestFollowups->filter(fn($f) => $f->is_missed)->sortByDesc(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MIN);
        $allSorted = $notMissed->values()->merge($missed->values());

        $todayFollowUpsCount = $allSorted->count();
        $todayFollowUps = $allSorted->take(5)->values();

        $dnpLeads = [];
        $nextWeekDnpLeads = [];
        if ($dnpServiceId) {
            $dnpQuery = Lead::with(['client', 'representative'])
                ->where(function ($query) use ($dnpServiceId) {
                    $query->where('service_ids', 'like', '%' . $dnpServiceId . '%')
                        ->orWhere('service_ids', 'like', '%"' . $dnpServiceId . '"%');
                })
                ->whereDate('updated_at', '>=', $currentMonthStart)
                ->whereDate('updated_at', '<=', $nextMonthStart)
                ->orderby('updated_at', 'desc');

            if (!empty($repIds)) {
                $dnpQuery->whereIn('representative_user_id', $repIds);
            }

            $dnpLeads = (clone $dnpQuery)->get();
            $nextWeekDnpLeads = (clone $dnpQuery)->limit(7)->get();
        }

        $targetProgress = $this->calculateTargetProgressForRepIds(
            $repIds,
            $targetPeriod['year'],
            $targetPeriod['month'],
            $todayClosed
        );
        $targetMonthName = $targetProgress['month_name'];
        $targetMonthYear = $targetProgress['year'];
        $teamMemberProgress = $this->buildTeamMemberProgress(
            $repIds,
            $targetPeriod['year'],
            $targetPeriod['month'],
            $todayClosed
        );

        $followupProductQuery = LeadFollowup::with(['enquiry.representative', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        if (!empty($repIds)) {
            $followupProductQuery->whereHas('enquiry', function ($q) use ($repIds) {
                $q->whereIn('representative_user_id', $repIds);
            });
        }

        $byServiceDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry.rideSegments', function ($q) use ($currentMonthStart, $currentMonthEnd) {
                $q->where('from_date', '>=', $currentMonthStart)
                    ->where('to_date', '<=', $currentMonthEnd);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $byCreatedDateFollowups = (clone $followupProductQuery)
            ->whereHas('enquiry', function ($q) use ($currentMonthStart, $nextMonthStart) {
                $q->where('created_at', '>=', $currentMonthStart)
                    ->where('created_at', '<', $nextMonthStart);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $dataByServiceDate = $this->buildProductSummaryFromFollowups($byServiceDateFollowups);
        $dataByCreatedDate = $this->buildProductSummaryFromFollowups($byCreatedDateFollowups);

        $todayRows = [];
        foreach ($todayFollowUps as $row) {
            $enq = $row->enquiry;
            $serviceNames = $enq->service_names ?? [];
            $serviceText = is_array($serviceNames) ? implode(', ', $serviceNames) : ($serviceNames ?: 'N/A');
            $todayRows[] = [
                'sno' => count($todayRows) + 1,
                'client_name' => $enq->client->name ?? 'N/A',
                'contact_number' => $enq->client->contact_number ?? 'N/A',
                'representative_name' => $enq->representative->name ?? '--',
                'service_text' => $serviceText,
                'next_followup' => $row->next_followup_date ? $row->next_followup_date->format('d M, H:i') : '--',
                'followup_route' => route('admin.leads.follow-up.create', $enq->id ?? 0),
            ];
        }

        $dnpRows = [];
        foreach ($nextWeekDnpLeads as $index => $lead) {
            $serviceNames = $lead->service_names ?? [];
            $serviceText = is_array($serviceNames) ? implode(', ', $serviceNames) : ($serviceNames ?: 'N/A');
            $dnpRows[] = [
                'sno' => $index + 1,
                'name' => $lead->client->name ?? 'N/A',
                'number' => $lead->client->contact_number ?? 'N/A',
                'service_text' => $serviceText,
                'last_followup' => $lead->updated_at ? $lead->updated_at->format('d M, H:i') : '--',
                'representative_name' => $lead->representative->name ?? 'N/A',
                'view_route' => route('admin.clients.view', $lead->client->id),
                'edit_route' => route('admin.clients.edit', $lead->client->id),
            ];
        }

        return response()->json([
            'data' => [
                'leads_count' => $currentMonthCount,
                'percentage_change' => round($percentageChange, 1),
                'today_followups_count' => $todayFollowUpsCount,
                'dnp_count' => count($dnpLeads),
                'target_progress' => $targetProgress,
                'target_month_name' => $targetMonthName,
                'target_month_year' => $targetMonthYear,
                'team_member_progress' => $teamMemberProgress,
                'product_summary' => [
                    'dataByProductDate' => $dataByServiceDate,
                    'dataByCreatedDate' => $dataByCreatedDate,
                ],
                'today_followups' => $todayRows,
                'dnp_leads' => $dnpRows,
            ]
        ]);
    }

    public function getTodayFollowUpsData(Request $request)
    {
        $currentUser = auth()->user();
        $userType = $currentUser->userType->user_type;
        $userId = $request->get('user_id');

        // Resolve representative ids to include based on role and optional user_id
        $resolveRepIds = function () use ($currentUser, $userType, $userId) {
            if ($userId) {
                $targetUser = User::with('userType')->find($userId);
                if (!$targetUser) return null;
                $targetType = $targetUser->userType->user_type ?? null;

                if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                    if ($targetType === UserType::SALES_EXECUTIVE) {
                        if (!\App\Models\SalesExecutiveAssignment::isAssigned($currentUser->id, $userId)) return false;
                    } elseif (in_array($targetType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                        if ($userId != $currentUser->id) return false;
                    }
                }
                return [$userId];
            }

            if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                $assigned = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
                $ids = $assigned->pluck('id')->toArray();
                $ids = array_unique(array_merge($ids, [$currentUser->id]));
                return $ids ?: [$currentUser->id];
            }

            if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
                $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
                $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
                $execTypeId = $execType ? $execType->id : null;
                $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
                $ids = !empty($allTypes) ? User::whereIn('user_type_id', $allTypes)->where('status', 1)->pluck('id')->toArray() : [];
                return $ids ?: [];
            }

            return [$currentUser->id];
        };

        $repIds = $resolveRepIds();
        if ($repIds === null) return response()->json(['error' => 'Not found'], 404);
        if ($repIds === false) return response()->json(['error' => 'Unauthorized'], 403);

        $currentDate = Carbon::now()->toDateString();

        // Base follow-up query
        $followUpQuery = LeadFollowup::with(['enquiry', 'enquiry.client', 'enquiry.representative'])
            ->whereHas('enquiry', function ($q) use ($repIds) {
                $q->whereIn('representative_user_id', $repIds);
            });

        // ── Bulk fetch: get candidate lead IDs ───────────────────────────────
        $todayLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '=', $currentDate)
            ->whereNotIn('status', [2, 5])
            ->pluck('lead_id')->unique();

        $missedLeadIds = (clone $followUpQuery)
            ->whereDate('next_followup_date', '<', $currentDate)
            ->whereIn('status', [0, 1, 4])
            ->pluck('lead_id')->unique();

        $allLeadIds = $todayLeadIds->merge($missedLeadIds)->unique()->values();

        $result = collect();
        if ($allLeadIds->isNotEmpty()) {
            // ONE bulk query — get latest followup per lead
            $allFollowupsForLeads = LeadFollowup::with(['enquiry', 'enquiry.client', 'enquiry.representative'])
                ->whereIn('lead_id', $allLeadIds)
                ->orderByDesc('next_followup_date')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('lead_id')
                ->map(fn($group) => $group->first());

            $latestPerLead = collect();
            foreach ($allFollowupsForLeads as $leadId => $latest) {
                if (in_array($latest->status, [2, 5])) continue;
                if (!$latest->next_followup_date) continue;
                $latestDate = $latest->next_followup_date->toDateString();
                if ($latestDate === $currentDate) {
                    $latest->is_missed = false;
                    $latestPerLead->push($latest);
                } elseif ($latestDate < $currentDate && in_array($latest->status, [0, 1, 4])) {
                    $latest->is_missed = true;
                    $latestPerLead->push($latest);
                }
            }

            $notMissed = $latestPerLead->filter(fn($f) => !$f->is_missed)
                ->sortBy(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MAX);
            $missed = $latestPerLead->filter(fn($f) => $f->is_missed)
                ->sortByDesc(fn($f) => $f->next_followup_date?->timestamp ?? PHP_INT_MIN);

            $result = $notMissed->values()->merge($missed->values())->take(5)->values();
        }

        // Build response array
        $data = [];
        foreach ($result as $intKey => $obj) {
            $enq = $obj->enquiry;
            $serviceNames = $enq->service_names ?? [];
            $serviceText = is_array($serviceNames) ? implode(', ', $serviceNames) : ($serviceNames ?: 'N/A');
            $data[] = [
                'sno' => $intKey + 1,
                'enquiry_id' => $enq->id ?? null,
                'lead_id' => $obj->lead_id ?? null,
                'client_name' => $enq->client->name ?? 'N/A',
                'contact_number' => $enq->client->contact_number ?? 'N/A',
                'representative_name' => $enq->representative->name ?? '--',
                'service_text' => $serviceText,
                'next_followup' => $obj->next_followup_date ? $obj->next_followup_date->format('d M, H:i') : '--',
                'followup_route' => route('admin.leads.follow-up.create', $enq->id ?? 0),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get status text based on status code
     */
    private function getStatusText($status): string
    {
        switch ((int) $status) {
            case 0:
            case 1:
            case 6:
                return 'Active';
            case 2:
            case 9:
                return 'Cancelled';
            case 3:
            case 4:
            case 5:
            case 7:
            case 8:
                return 'Confirmed/Complete';
            default:
                return 'Active'; // Default to Active for unknown statuses
        }
    }

    /**
     * Check whether any follow-up for the given lead has an approved payment
     * (payment_status == 1) in its payment audit trail.
     */
    private function leadHasApprovedPayment($lead): bool
    {
        if (empty($lead->leadFollowups)) return false;

        foreach ($lead->leadFollowups as $followup) {
            if (empty($followup->paymentAuditTrail)) continue;
            foreach ($followup->paymentAuditTrail as $audit) {
                if ((int) ($audit->payment_status ?? 0) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine effective status text for a lead.
     * If any follow-up has an approved payment, classify as Confirmed/Complete.
     * Otherwise, fall back to latest follow-up status mapping.
     */
    private function getEffectiveStatusFromLead($lead, $latestFollowup = null): string
    {
        if ($this->leadHasApprovedPayment($lead)) {
            return 'Confirmed/Complete';
        }

        return $this->getStatusText($latestFollowup ? $latestFollowup->status : 6);
    }

    private function getServicesFromFollowup($followup)
    {
        if (!$followup || !$followup->service_ids) {
            return [];
        }

        $serviceIds = is_array($followup->service_ids)
            ? $followup->service_ids
            : json_decode($followup->service_ids, true);

        return $serviceIds ? Service::whereIn('id', $serviceIds)->pluck('service')->toArray() : [];
    }

    private function getProductsFromFollowup($followup)
    {
        if (!$followup) {
            return [];
        }

        // First, try to get product ids directly from the enquiry (lead) if available
        $enquiry = $followup->enquiry ?? null;
        if ($enquiry) {
            // Lead model provides product_ids_array accessor
            $productIds = $enquiry->product_ids_array ?? [];
            if (is_string($productIds)) {
                $productIds = json_decode($productIds, true) ?: [];
            }
            $productIds = is_array($productIds) ? array_values(array_unique($productIds)) : [];
            if (!empty($productIds)) {
                return Product::whereIn('id', $productIds)->pluck('product')->toArray();
            }
        }

        // Fallback: if lead has no product_ids, map services -> product_ids
        $serviceIds = [];
        if (!empty($followup->service_ids)) {
            $serviceIds = is_array($followup->service_ids) ? $followup->service_ids : (json_decode($followup->service_ids, true) ?: []);
        }

        if (empty($serviceIds)) {
            return [];
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        $productIds = [];
        foreach ($services as $s) {
            $pids = $s->product_ids ?? [];
            $pids = is_array($pids) ? $pids : (json_decode($pids, true) ?: []);
            foreach ($pids as $pid) {
                if ($pid) $productIds[] = $pid;
            }
        }

        $productIds = array_values(array_unique($productIds));
        if (empty($productIds)) {
            return [];
        }

        return Product::whereIn('id', $productIds)->pluck('product')->toArray();
    }

    private function getEmptyStatusArray()
    {
        return [
            'Active' => 0,
            'Cancelled' => 0,
            'Confirmed/Complete' => 0,
        ];
    }

    /**
     * Build product x status summary from a collection of followups.
     * Uses the same logic as the KPI export and client status text mapping:
     *   Active = latest followup status [0,1,6]
     *   Cancelled = latest followup status [2,9]
     *   Confirmed/Complete = latest followup status [3,4,5,7,8]
     * Groups by product (from enquiry.product_ids) and counts per unique lead.
     */
    private function buildProductSummaryFromFollowups($followups): array
    {
        $groupedByProduct = collect();

        foreach ($followups as $followup) {
            if (!$followup->enquiry) continue;

            $productIds = $followup->enquiry->product_ids;

            if (empty($productIds)) {
                $productName = 'NO REQUIREMENT';
            } else {
                $productIdsArray = is_string($productIds) ? json_decode($productIds, true) : $productIds;
                if (!is_array($productIdsArray)) {
                    $productIdsArray = [$productIds];
                }
                $products = Product::whereIn('id', $productIdsArray)->pluck('product')->toArray();
                $productName = !empty($products) ? implode(', ', $products) : 'NO REQUIREMENT';
            }

            if (!$groupedByProduct->has($productName)) {
                $groupedByProduct->put($productName, collect());
            }
            $groupedByProduct->get($productName)->push($followup);
        }

        $data = [];
        foreach ($groupedByProduct as $productName => $group) {
            $byLead = $group->groupBy('lead_id');

            $activeCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [0, 1, 6]) ? 1 : 0;
            })->sum();

            $cancelledCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [2, 9]) ? 1 : 0;
            })->sum();

            $completedCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [3, 4, 5, 7, 8]) ? 1 : 0;
            })->sum();

            $otherCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                $status = $latest->status;
                if (in_array($status, [0, 1, 6])) {
                    return 0;
                }
                if (in_array($status, [2, 9])) {
                    return 0;
                }
                if (in_array($status, [3, 4, 5, 7, 8])) {
                    return 0;
                }
                return 1;
            })->sum();

            $data[$productName] = [
                'Active' => $activeCount,
                'Cancelled' => $cancelledCount,
                'Confirmed/Complete' => $completedCount,
                'Other' => $otherCount,
                'Total' => $byLead->count(),
            ];
        }

        return $data;
    }

    /**
     * AJAX: Return target progress for a team or a single sales executive.
     * - if `user_id` is provided: return that executive's progress (admins can query anyone; managers only for assigned execs)
     * - if no `user_id`: for managers return team progress (assigned executives), for admins return overall (all execs)
     */
    public function getTargetProgressData(Request $request)
    {
        $currentUser = auth()->user();
        $userType = $currentUser->userType->user_type;

        $userId = $request->get('user_id');
        $targetPeriod = $this->resolveTargetPeriod($request);
        $todayClosed = $this->resolveTodayClosed($request);

        // If user_id provided -> single user (manager or executive). Do NOT expand manager to include their team.
        if ($userId) {
            $targetUser = User::with('userType')->find($userId);
            if (!$targetUser) {
                return response()->json(['error' => 'Not found'], 404);
            }

            $targetType = $targetUser->userType->user_type ?? null;

            // Authorization rules:
            // - Sales Manager can query: their assigned executives and themselves only
            // - Admin/Super Admin can query anyone
            // - Sales Executive can only query themselves (handled elsewhere)
            if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                if ($targetType === UserType::SALES_EXECUTIVE) {
                    if (!\App\Models\SalesExecutiveAssignment::isAssigned($currentUser->id, $userId)) {
                        return response()->json(['error' => 'Unauthorized'], 403);
                    }
                } elseif (in_array($targetType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
                    // manager can only query themselves
                    if ($userId != $currentUser->id) {
                        return response()->json(['error' => 'Unauthorized'], 403);
                    }
                }
            }

            // Compute only for the selected user (manager or executive)
            $repIds = [$userId];
            $result = $this->calculateTargetProgressForRepIds($repIds, $targetPeriod['year'], $targetPeriod['month'], $todayClosed);
            return response()->json([
                'data' => $result,
                'team_member_progress' => $this->buildTeamMemberProgress($repIds, $targetPeriod['year'], $targetPeriod['month'], $todayClosed),
            ]);
        }

        // No user_id: return team progress for managers, or overall for admins
        if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
            $ids = $assignedExecutives->pluck('id')->toArray();
            // include manager's own id
            $ids = array_unique(array_merge($ids, [$currentUser->id]));
            $ids = $ids ?: [$currentUser->id];
            $result = $this->calculateTargetProgressForRepIds($ids, $targetPeriod['year'], $targetPeriod['month'], $todayClosed);
            return response()->json([
                'data' => $result,
                'team_member_progress' => $this->buildTeamMemberProgress($ids, $targetPeriod['year'], $targetPeriod['month'], $todayClosed),
            ]);
        }

        // Admins: compute for all sales executives
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            // include managers + executives in overall totals
            $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
            $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
            $execTypeId = $execType ? $execType->id : null;

            $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
            $ids = !empty($allTypes) ? User::whereIn('user_type_id', $allTypes)->where('status', 1)->pluck('id')->toArray() : [];
            $result = $this->calculateTargetProgressForRepIds($ids, $targetPeriod['year'], $targetPeriod['month'], $todayClosed);
            return response()->json([
                'data' => $result,
                'team_member_progress' => $this->buildTeamMemberProgress($ids, $targetPeriod['year'], $targetPeriod['month'], $todayClosed),
            ]);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
