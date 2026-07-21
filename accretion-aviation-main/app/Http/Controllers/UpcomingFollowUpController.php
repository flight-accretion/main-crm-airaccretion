<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\Service;
use Illuminate\View\View;
use Illuminate\Support\Str;
use App\Models\LeadFollowup;
use Illuminate\Http\Request;
use App\Models\FollowUpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use function App\Helpers\getRepresentativeIds;

class UpcomingFollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $currentUser = auth()->user();
        $representatives = getRepresentativeIds($currentUser);

        // Prepare list for dropdown: for managers show assigned executives + manager, for admins show managers+execs
        $assignedExecutives = collect();
        if (in_array($currentUser->userType->user_type, [\App\Models\UserType::SALES_MANAGER, \App\Models\UserType::SENIOR_SALES_MANAGER])) {
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id);
        } elseif (in_array($currentUser->userType->user_type, [\App\Models\UserType::ADMIN, \App\Models\UserType::SUPER_ADMIN])) {
            // Admins: include all managers and sales executives
            $managerTypes = \App\Models\UserType::whereIn('user_type', [\App\Models\UserType::SALES_MANAGER, \App\Models\UserType::SENIOR_SALES_MANAGER])->pluck('id')->toArray();
            $execType = \App\Models\UserType::where('user_type', \App\Models\UserType::SALES_EXECUTIVE)->first();
            $execTypeId = $execType ? $execType->id : null;
            $allTypes = array_merge($managerTypes, $execTypeId ? [$execTypeId] : []);
            $assignedExecutives = \App\Models\User::whereIn('user_type_id', $allTypes)->where('status', 1)->get();
        }

    // Base query used for both today's and missed follow-ups
    // eager load enquiry and its representative so views can display staff representative name
    $baseQuery = LeadFollowup::with(['enquiry', 'enquiry.representative']);
            // ->whereNotNull('service_ids')
            // ->whereRaw("service_ids::text != '[]'");

        $fromDate = $request->filled('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : Carbon::now()->startOfDay();

    // If a specific representative filter is provided via request, validate it against allowed reps
    // IMPORTANT: representative IDs are UUID strings, do NOT cast to int (that would become 0)
    $selectedRepId = $request->filled('representative_user_id') ? $request->representative_user_id : null;

        // Determine effective representative ids to filter by
        $forceNoResults = false;
        if ($selectedRepId) {
            // Admins (representatives === null) may select any user
            if (is_null($representatives)) {
                $effectiveReps = [$selectedRepId];
            } else {
                // Normalize representatives to array of ids (strings)
                if ($representatives instanceof \Illuminate\Support\Collection) {
                    $repArray = $representatives->toArray();
                } elseif (is_array($representatives)) {
                    $repArray = $representatives;
                } else {
                    // fallback - try cast
                    $repArray = (array) $representatives;
                }

                // For managers/executives, only allow selecting from their visible set
                if (in_array($selectedRepId, $repArray, true)) {
                    $effectiveReps = [$selectedRepId];
                } else {
                    // Unauthorized selection -> no results
                    $forceNoResults = true;
                    $effectiveReps = [];
                }
            }
        } else {
            // no specific selection: keep original representatives handling
            $effectiveReps = $representatives;
        }

        if ($forceNoResults) {
            // Shortcut: render view with empty results
            $fromDate = $request->filled('from_date')
                ? Carbon::parse($request->from_date)->startOfDay()
                : Carbon::now()->startOfDay();

            $products = Product::where('status', 1)->get();
            $arrFollowUps = collect();
            return view('admin.pages.follow-ups.index-upcoming-follow-up', compact('fromDate', 'arrFollowUps', 'products', 'assignedExecutives'));
        }

        // if ($effectiveReps) {
        //     // Filter by both follow-up assigned user AND lead representative
        //     $baseQuery->where(function($query) use ($effectiveReps) {
        //         $query->whereIn('followed_by', $effectiveReps)
        //               ->orWhereHas('enquiry', function($leadQuery) use ($effectiveReps) {
        //                   $leadQuery->whereIn('representative_user_id', $effectiveReps);
        //               });
        //     });
        // }

        if ($effectiveReps) {
            // Filter strictly by the Lead Representative (matches the table view)
            $baseQuery->whereHas('enquiry', function($leadQuery) use ($effectiveReps) {
                $leadQuery->whereIn('representative_user_id', $effectiveReps);
            });
        }

        // Get follow-ups scheduled for the selected date
        $todayQuery = (clone $baseQuery)->whereDate('next_followup_date', '=', $fromDate);
        $todayFollowUps = $todayQuery->orderBy('next_followup_date', 'desc')->get()->map(function ($f) {
            $f->is_missed = false;
            return $f;
        });

        // Also include 'missed' follow-ups: those scheduled before the selected date and still open (status 0 or 1)
        // Adjust statuses here if your app uses different codes for 'open' follow-ups
        $missedQuery = (clone $baseQuery)
            ->whereDate('next_followup_date', '<', $fromDate)
            ->orderBy('next_followup_date', 'asc')
            ->whereIn('status', [0, 1, 4]);
        $missedFollowUps = $missedQuery->orderBy('next_followup_date', 'asc')->get()->map(function ($f) {
            $f->is_missed = true;
            return $f;
        });

        // Combine: show today's first, then missed ones
        $arrFollowUps = $todayFollowUps->merge($missedFollowUps);

        // NEW LOGIC: Only show open follow-ups, properly handle completed ones
        // Instead of complex filtering, let's simplify:
        // 1. Get all follow-ups for the selected date that are still open (not completed/cancelled)
        // 2. Get all missed follow-ups that are still open
        // 3. For each lead, show only the latest open follow-up
        
        $allRelevantFollowups = collect();
        
        // Get today's open follow-ups
        $todayOpenQuery = (clone $baseQuery)
            ->whereDate('next_followup_date', '=', $fromDate)
            ->whereNotIn('status', [2, 5]); // Exclude cancelled and completed
        $todayOpenFollowUps = $todayOpenQuery->get();
        
        // Get missed open follow-ups
        $missedOpenQuery = (clone $baseQuery)
            ->whereDate('next_followup_date', '<', $fromDate)
            ->whereIn('status', [0, 1, 4]); // Only open statuses
        $missedOpenFollowUps = $missedOpenQuery->get();
        
        // Combine all relevant follow-ups
        $allRelevantFollowups = $todayOpenFollowUps->merge($missedOpenFollowUps);
        
        // For each lead, get only the latest follow-up entry and check if it should be shown
        $latestPerLead = collect();
        $processedLeads = collect();
        
        foreach ($allRelevantFollowups as $followup) {
            $leadId = $followup->lead_id;
            
            // Skip if we already processed this lead
            if ($processedLeads->contains($leadId)) {
                continue;
            }
            
            // Get the absolute latest follow-up for this lead (prefer the one with the
            // most recent scheduled follow-up date; fall back to created_at to break ties).
            // This avoids showing an older "missed" follow-up when a newer follow-up
            // for the same lead (e.g. scheduled for today) has been created.
            $latestFollowupForLead = LeadFollowup::with(['enquiry', 'enquiry.representative'])
                ->where('lead_id', $leadId)
                ->orderByDesc('next_followup_date')
                ->orderByDesc('created_at')
                ->first();
            
            if ($latestFollowupForLead) {
                // If the latest follow-up is completed/cancelled, don't show this lead
                if (in_array($latestFollowupForLead->status, [2, 5])) {
                    $processedLeads->push($leadId);
                    continue;
                }
                
                // If the latest follow-up is open, check if it should appear for the selected date or is missed
                if ($latestFollowupForLead->next_followup_date) {
                    $followupDate = $latestFollowupForLead->next_followup_date->toDateString();
                    $selectedDate = $fromDate->toDateString();
                    
                    if ($followupDate === $selectedDate) {
                        // This follow-up is for the selected date
                        $latestFollowupForLead->is_missed = false;
                        $latestPerLead->push($latestFollowupForLead);
                    } elseif ($latestFollowupForLead->next_followup_date->lt($fromDate) &&
                             in_array($latestFollowupForLead->status, [0, 1, 4])) {
                        // This is a missed follow-up that's still open
                        $latestFollowupForLead->is_missed = true;
                        $latestPerLead->push($latestFollowupForLead);
                    }
                }
            }
            
            $processedLeads->push($leadId);
        }

        // No need to load enquiry relationship as it's already loaded with the query above

        $notMissed = $latestPerLead->filter(function ($f) {
            return !$f->is_missed;
        })->sortBy(function ($f) {
            // Sort ascending by scheduled follow-up datetime (earliest first).
            // Use timestamp to avoid issues if the attribute is a Carbon instance or string.
            return $f->next_followup_date ? ($f->next_followup_date->timestamp ?? strtotime($f->next_followup_date)) : PHP_INT_MAX;
        });

        $missed = $latestPerLead->filter(function ($f) {
            return !empty($f->is_missed) && $f->is_missed;
        })->sortByDesc(function ($f) {
            return $f->next_followup_date ? ($f->next_followup_date->timestamp ?? strtotime($f->next_followup_date)) : PHP_INT_MIN;
        });

        $arrFollowUps = $notMissed->values()->merge($missed->values());

        if ($request->filled('product_id')) {
            $serviceIds = Service::whereJsonContains('product_ids', $request->product_id)
                ->pluck('id')
                ->toArray();

            $arrFollowUps = $arrFollowUps->filter(function ($row) use ($serviceIds) {
                if (empty($row->service_ids)) return false;

                $json = trim($row->service_ids, '"');
                $json = str_replace('\\"', '"', $json);
                $ids = json_decode($json, true);

                return is_array($ids) && count(array_intersect($ids, $serviceIds)) > 0;
            });
        }

        $products = Product::where('status', 1)->get();

        return view('admin.pages.follow-ups.index-upcoming-follow-up', compact('fromDate', 'arrFollowUps', 'products', 'assignedExecutives'));
    }


    public function show(string $id): JsonResponse
    {
        if (!Str::isUuid($id)) {
            return response()->json(['message' => 'Invalid ID format.'], 400);
        }

        $objFollowUpStatus = FollowUpStatus::find($id);

        if (!$objFollowUpStatus) {
            return response()->json(['message' => 'Follow-up status not found.'], 404);
        }

        return response()->json($objFollowUpStatus);
    }
}
