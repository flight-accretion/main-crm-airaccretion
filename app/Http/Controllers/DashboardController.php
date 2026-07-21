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
use Illuminate\Http\Request;
use function App\Helpers\getRepresentativeIds;

class DashboardController extends Controller
{
    public function getSalesDashboard(Request $request)
    {

        $currentUser = auth()->user();
        $userType = $currentUser->userType->user_type;

        // Get current month target for Sales Executives
        $currentMonthTarget = null;
        $targetProgress = null;
        if ($userType === UserType::SALES_EXECUTIVE) {
            $currentYear = date('Y');
            $currentMonth = date('n');

            $currentMonthTarget = Target::where('sales_executive_id', $currentUser->id)
                ->where('year', $currentYear)
                ->where('month', $currentMonth)
                ->where('status', 'active')
                ->first();

            if ($currentMonthTarget) {
                // Achieved = Sum(Total Amount of LATEST followup per lead) - Sum(Refund Amount)
                // Only count leads that have approved payments with paid_date in the current month/year
                // // Find leads with approved payments in the current month/year (based on paid_date)
                // $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
                //     ->whereYear('paid_date', $currentYear)
                //     ->whereMonth('paid_date', $currentMonth)
                //     ->pluck('lead_followup_id')->unique();
                // $paidLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
                //     ->whereHas('enquiry', function ($query) use ($currentUser) {
                //         $query->where('representative_user_id', $currentUser->id);
                //     })
                //     ->pluck('lead_id')->unique();

                // Get candidate leads that had ANY approved payment this month
$paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
    ->whereYear('paid_date', $currentYear)
    ->whereMonth('paid_date', $currentMonth)
    ->pluck('lead_followup_id')->unique();

$candidateLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
    ->whereHas('enquiry', function ($query) use ($currentUser) {
        $query->where('representative_user_id', $currentUser->id);
    })
    ->pluck('lead_id')->unique();

// Only keep leads whose FIRST EVER approved payment paid_date is in this month
$allFollowupIdsByLead = \App\Models\LeadFollowup::whereIn('lead_id', $candidateLeadIds)
    ->get(['id', 'lead_id'])
    ->groupBy('lead_id')
    ->map(fn($g) => $g->pluck('id'));

$allFollowupIdsFlat = $allFollowupIdsByLead->flatten()->unique();

$firstPaymentPerLead = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsFlat)
    ->where('payment_status', 1)
    ->orderBy('paid_date')
    ->get()
    ->groupBy(function ($p) use ($allFollowupIdsByLead) {
        foreach ($allFollowupIdsByLead as $leadId => $fids) {
            if ($fids->contains($p->lead_followup_id)) return $leadId;
        }
        return null;
    })
    ->map(fn($payments) => $payments->sortBy('paid_date')->first());

$paidLeadIds = $firstPaymentPerLead->filter(function ($first) use ($currentYear, $currentMonth) {
    if (!$first || empty($first->paid_date)) return false;
    $d = \Carbon\Carbon::parse($first->paid_date);
    return $d->year === (int) $currentYear && $d->month === (int) $currentMonth;
})->keys();

                // Get ALL followups for those paid leads
                $allFollowups = \App\Models\LeadFollowup::whereIn('lead_id', $paidLeadIds)
                    ->whereHas('enquiry', function ($query) use ($currentUser) {
                        $query->where('representative_user_id', $currentUser->id);
                    })
                    ->get();

                // Calculate per-lead: Sales Amount = max(0, Total Amount - Refund Amount)
                // Filter to qualifying statuses FIRST, then take latest (matches Sales Report)
                $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) {
                    $qualifying = $group->filter(function ($f) {
                        return in_array($f->status, [2, 5, 7, 8]);
                    });
                    return $qualifying->sortByDesc('created_at')->first();
                })->filter()->sum(function ($f) {
                    $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
                    $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                        ->whereIn('status', [1, 2])->sum('refund_amount');
                    return max(0, (float) $f->total_amount - $refund);
                });

                // Update the target's achieved amount for real-time tracking
                $currentMonthTarget->update(['achieved_amount' => $achievedAmount]);

                // Refresh the model to get updated achieved_amount
                $currentMonthTarget = $currentMonthTarget->fresh();

                $achievementPercentage = $currentMonthTarget->target_amount > 0
                    ? round(($achievedAmount / $currentMonthTarget->target_amount) * 100, 2)
                    : 0;

                $remainingAmount = $currentMonthTarget->target_amount - $achievedAmount;

                $targetProgress = [
                    'achievement_percentage' => $achievementPercentage,
                    'remaining_amount' => max(0, $remainingAmount),
                    'target_amount' => $currentMonthTarget->target_amount,
                    'achieved_amount' => $achievedAmount,
                    'sales_amount' => $achievedAmount,
                ];
            }
        }

        // If current user is a Sales Manager (or Senior), prepare assigned executives and team progress
        $assignedExecutives = collect();
        $teamTargetProgress = null;
        if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);

            $assignedIds = $assignedExecutives->pluck('id')->toArray();
            // Include the manager's own id so the team total includes the manager's target/achieved if they have one
            $ids = array_unique(array_merge($assignedIds, [$currentUser->id]));
            if (!empty($ids)) {
                // Sum targets for the assigned executives for current month
                $teamTargetAmount = Target::whereIn('sales_executive_id', $ids)
                    ->where('year', date('Y'))
                    ->where('month', date('n'))
                    ->where('status', 'active')
                    ->sum('target_amount');

                // Find leads with approved payments in current month/year (based on paid_date)
                // $teamPaidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
                //     ->whereYear('paid_date', date('Y'))
                //     ->whereMonth('paid_date', date('n'))
                //     ->pluck('lead_followup_id')->unique();
                // $teamPaidLeadIds = \App\Models\LeadFollowup::whereIn('id', $teamPaidFollowupIds)
                //     ->whereHas('enquiry', function ($q) use ($ids) {
                //         $q->whereIn('representative_user_id', $ids);
                //     })
                //     ->pluck('lead_id')->unique();

                $currentYear = date('Y');
                $currentMonth = date('n');
                
                $teamPaidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
    ->whereYear('paid_date', $currentYear)
    ->whereMonth('paid_date', $currentMonth)
    ->pluck('lead_followup_id')->unique();

$teamCandidateLeadIds = \App\Models\LeadFollowup::whereIn('id', $teamPaidFollowupIds)
    ->whereHas('enquiry', function ($q) use ($ids) {
        $q->whereIn('representative_user_id', $ids);
    })
    ->pluck('lead_id')->unique();

$teamAllFollowupIdsByLead = \App\Models\LeadFollowup::whereIn('lead_id', $teamCandidateLeadIds)
    ->get(['id', 'lead_id'])
    ->groupBy('lead_id')
    ->map(fn($g) => $g->pluck('id'));

$teamAllFollowupIdsFlat = $teamAllFollowupIdsByLead->flatten()->unique();

$teamFirstPaymentPerLead = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $teamAllFollowupIdsFlat)
    ->where('payment_status', 1)
    ->orderBy('paid_date')
    ->get()
    ->groupBy(function ($p) use ($teamAllFollowupIdsByLead) {
        foreach ($teamAllFollowupIdsByLead as $leadId => $fids) {
            if ($fids->contains($p->lead_followup_id)) return $leadId;
        }
        return null;
    })
    ->map(fn($payments) => $payments->sortBy('paid_date')->first());

$teamPaidLeadIds = $teamFirstPaymentPerLead->filter(function ($first) use ($currentYear, $currentMonth) {
    if (!$first || empty($first->paid_date)) return false;
    $d = \Carbon\Carbon::parse($first->paid_date);
    return $d->year === (int) $currentYear && $d->month === (int) $currentMonth;
})->keys();

                $teamAllFollowups = \App\Models\LeadFollowup::whereIn('lead_id', $teamPaidLeadIds)
                    ->whereHas('enquiry', function ($q) use ($ids) {
                        $q->whereIn('representative_user_id', $ids);
                    })
                    ->get();

                // Calculate per-lead: Sales Amount = max(0, Total Amount - Refund Amount)
                $teamAchievedAmount = $teamAllFollowups->groupBy('lead_id')->map(function ($group) {
                    $qualifying = $group->filter(function ($f) {
                        return in_array($f->status, [2, 5, 7, 8]);
                    });
                    return $qualifying->sortByDesc('created_at')->first();
                })->filter()->sum(function ($f) {
                    $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
                    $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                        ->whereIn('status', [1, 2])->sum('refund_amount');
                    return max(0, (float) $f->total_amount - $refund);
                });

                $teamRemaining = $teamTargetAmount - $teamAchievedAmount;
                $teamAchievementPercentage = $teamTargetAmount > 0 ? round(($teamAchievedAmount / $teamTargetAmount) * 100, 2) : 0;

                $teamTargetProgress = [
                    'achievement_percentage' => $teamAchievementPercentage,
                    'remaining_amount' => max(0, $teamRemaining),
                    'target_amount' => $teamTargetAmount,
                    'achieved_amount' => max(0, $teamAchievedAmount),
                    'sales_amount' => max(0, $teamAchievedAmount),
                ];
            } else {
                // No assigned executives — zero values
                $teamTargetProgress = [
                    'achievement_percentage' => 0,
                    'remaining_amount' => 0,
                    'target_amount' => 0,
                    'achieved_amount' => 0,
                    'sales_amount' => 0,
                ];
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
                $teamTargetAmount = Target::whereIn('sales_executive_id', $ids)
                    ->where('year', date('Y'))
                    ->where('month', date('n'))
                    ->where('status', 'active')
                    ->sum('target_amount');

                // Find leads with approved payments in current month/year (based on paid_date)
                // $adminPaidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
                //     ->whereYear('paid_date', date('Y'))
                //     ->whereMonth('paid_date', date('n'))
                //     ->pluck('lead_followup_id')->unique();
                // $adminPaidLeadIds = \App\Models\LeadFollowup::whereIn('id', $adminPaidFollowupIds)
                //     ->whereHas('enquiry', function ($q) use ($ids) {
                //         $q->whereIn('representative_user_id', $ids);
                //     })
                //     ->pluck('lead_id')->unique();

                $adminYear = date('Y');
$adminMonth = date('n');

$adminPaidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
    ->whereYear('paid_date', $adminYear)
    ->whereMonth('paid_date', $adminMonth)
    ->pluck('lead_followup_id')->unique();

$adminCandidateLeadIds = \App\Models\LeadFollowup::whereIn('id', $adminPaidFollowupIds)
    ->whereHas('enquiry', function ($q) use ($ids) {
        $q->whereIn('representative_user_id', $ids);
    })
    ->pluck('lead_id')->unique();

$adminAllFollowupIdsByLead = \App\Models\LeadFollowup::whereIn('lead_id', $adminCandidateLeadIds)
    ->get(['id', 'lead_id'])
    ->groupBy('lead_id')
    ->map(fn($g) => $g->pluck('id'));

$adminAllFollowupIdsFlat = $adminAllFollowupIdsByLead->flatten()->unique();

$adminFirstPaymentPerLead = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $adminAllFollowupIdsFlat)
    ->where('payment_status', 1)
    ->orderBy('paid_date')
    ->get()
    ->groupBy(function ($p) use ($adminAllFollowupIdsByLead) {
        foreach ($adminAllFollowupIdsByLead as $leadId => $fids) {
            if ($fids->contains($p->lead_followup_id)) return $leadId;
        }
        return null;
    })
    ->map(fn($payments) => $payments->sortBy('paid_date')->first());

$adminPaidLeadIds = $adminFirstPaymentPerLead->filter(function ($first) use ($adminYear, $adminMonth) {
    if (!$first || empty($first->paid_date)) return false;
    $d = \Carbon\Carbon::parse($first->paid_date);
    return $d->year === (int) $adminYear && $d->month === (int) $adminMonth;
})->keys();

                $adminAllFollowups = \App\Models\LeadFollowup::whereIn('lead_id', $adminPaidLeadIds)
                    ->whereHas('enquiry', function ($q) use ($ids) {
                        $q->whereIn('representative_user_id', $ids);
                    })
                    ->get();

                // Calculate per-lead: Sales Amount = max(0, Total Amount - Refund Amount)
                $teamAchievedAmount = $adminAllFollowups->groupBy('lead_id')->map(function ($group) {
                    $qualifying = $group->filter(function ($f) {
                        return in_array($f->status, [2, 5, 7, 8]);
                    });
                    return $qualifying->sortByDesc('created_at')->first();
                })->filter()->sum(function ($f) {
                    $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
                    $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                        ->whereIn('status', [1, 2])->sum('refund_amount');
                    return max(0, (float) $f->total_amount - $refund);
                });

                $teamRemaining = $teamTargetAmount - $teamAchievedAmount;
                $teamAchievementPercentage = $teamTargetAmount > 0 ? round(($teamAchievedAmount / $teamTargetAmount) * 100, 2) : 0;

                $teamTargetProgress = [
                    'achievement_percentage' => $teamAchievementPercentage,
                    'remaining_amount' => max(0, $teamRemaining),
                    'target_amount' => $teamTargetAmount,
                    'achieved_amount' => max(0, $teamAchievedAmount),
                    'sales_amount' => max(0, $teamAchievedAmount),
                ];
            } else {
                $teamTargetProgress = [
                    'achievement_percentage' => 0,
                    'remaining_amount' => 0,
                    'target_amount' => 0,
                    'achieved_amount' => 0,
                    'sales_amount' => 0,
                ];
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

        return view('admin.pages.dashboards.sales-dashboard', compact('currentMonth', 'leads', 'todayFollowUps', 'todayFollowUpsCount', 'services', 'dnpLeads', 'nextWeekDnpLeads', 'productSummary', 'currentMonthTarget', 'targetProgress', 'assignedExecutives', 'teamTargetProgress', 'assignedExecutivesAll', 'assignedExecutivesToday'));
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
     * Uses the same logic as the KPI export:
     *   Active = latest followup status [1]
     *   Cancelled = latest followup status [2]
     *   Completed = latest followup status [3,4,5,7,8]
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
                return in_array($latest->status, [1]) ? 1 : 0;
            })->sum();

            $cancelledCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return $latest->status == 2 ? 1 : 0;
            })->sum();

            $completedCount = $byLead->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [3, 4, 5, 7, 8]) ? 1 : 0;
            })->sum();

            $data[$productName] = [
                'Active' => $activeCount,
                'Cancelled' => $cancelledCount,
                'Confirmed/Complete' => $completedCount,
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
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Helper to compute progress for a set of representative ids
        $computeForRepIds = function (array $repIds) use ($currentYear, $currentMonth) {
            $targetAmount = Target::whereIn('sales_executive_id', $repIds)
                ->where('year', $currentYear)
                ->where('month', $currentMonth)
                ->where('status', 'active')
                ->sum('target_amount');

            // Find leads with approved payments in current month/year (based on paid_date)
            // $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            //     ->whereYear('paid_date', $currentYear)
            //     ->whereMonth('paid_date', $currentMonth)
            //     ->pluck('lead_followup_id')->unique();
            // $paidLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
            //     ->whereHas('enquiry', function ($q) use ($repIds) {
            //         $q->whereIn('representative_user_id', $repIds);
            //     })
            //     ->pluck('lead_id')->unique();

            $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
    ->whereYear('paid_date', $currentYear)
    ->whereMonth('paid_date', $currentMonth)
    ->pluck('lead_followup_id')->unique();

$candidateLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
    ->whereHas('enquiry', function ($q) use ($repIds) {
        $q->whereIn('representative_user_id', $repIds);
    })
    ->pluck('lead_id')->unique();

$allFollowupIdsByLead = \App\Models\LeadFollowup::whereIn('lead_id', $candidateLeadIds)
    ->get(['id', 'lead_id'])
    ->groupBy('lead_id')
    ->map(fn($g) => $g->pluck('id'));

$allFollowupIdsFlat = $allFollowupIdsByLead->flatten()->unique();

$firstPaymentPerLead = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsFlat)
    ->where('payment_status', 1)
    ->orderBy('paid_date')
    ->get()
    ->groupBy(function ($p) use ($allFollowupIdsByLead) {
        foreach ($allFollowupIdsByLead as $leadId => $fids) {
            if ($fids->contains($p->lead_followup_id)) return $leadId;
        }
        return null;
    })
    ->map(fn($payments) => $payments->sortBy('paid_date')->first());

$paidLeadIds = $firstPaymentPerLead->filter(function ($first) use ($currentYear, $currentMonth) {
    if (!$first || empty($first->paid_date)) return false;
    $d = \Carbon\Carbon::parse($first->paid_date);
    return $d->year === (int) $currentYear && $d->month === (int) $currentMonth;
})->keys();

            $allFollowups = \App\Models\LeadFollowup::whereIn('lead_id', $paidLeadIds)
                ->whereHas('enquiry', function ($q) use ($repIds) {
                    $q->whereIn('representative_user_id', $repIds);
                })
                ->get();

            // Calculate per-lead: Sales Amount = max(0, Total Amount - Refund Amount)
            $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) {
                $qualifying = $group->filter(function ($f) {
                    return in_array($f->status, [2, 5, 7, 8]);
                });
                return $qualifying->sortByDesc('created_at')->first();
            })->filter()->sum(function ($f) {
                $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
                $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                    ->whereIn('status', [1, 2])->sum('refund_amount');
                return max(0, (float) $f->total_amount - $refund);
            });

            $remaining = $targetAmount - $achievedAmount;
            $percentage = $targetAmount > 0 ? round(($achievedAmount / $targetAmount) * 100, 2) : 0;

            return [
                'achievement_percentage' => $percentage,
                'remaining_amount' => max(0, $remaining),
                'target_amount' => $targetAmount,
                'achieved_amount' => max(0, $achievedAmount),
                'sales_amount' => max(0, $achievedAmount),
            ];
        };

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
            $result = $computeForRepIds($repIds);
            return response()->json(['data' => $result]);
        }

        // No user_id: return team progress for managers, or overall for admins
        if (in_array($userType, [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])) {
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
            $ids = $assignedExecutives->pluck('id')->toArray();
            // include manager's own id
            $ids = array_unique(array_merge($ids, [$currentUser->id]));
            $result = $computeForRepIds($ids ?: [$currentUser->id]);
            return response()->json(['data' => $result]);
        }

        // Admins: compute for all sales executives
        if (in_array($userType, [UserType::SUPER_ADMIN, UserType::ADMIN])) {
            // include managers + executives in overall totals
            $managerTypes = UserType::whereIn('user_type', [UserType::SALES_MANAGER, UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
            $execType = UserType::where('user_type', UserType::SALES_EXECUTIVE)->first();
            $execTypeId = $execType ? $execType->id : null;

            $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
            $ids = !empty($allTypes) ? User::whereIn('user_type_id', $allTypes)->where('status', 1)->pluck('id')->toArray() : [];
            $result = $computeForRepIds($ids);
            return response()->json(['data' => $result]);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}