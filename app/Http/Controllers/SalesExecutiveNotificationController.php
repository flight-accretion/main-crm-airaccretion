<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Target;
use App\Models\UserType;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use Illuminate\Support\Facades\Log;

class SalesExecutiveNotificationController extends Controller
{
    // ═════════════════════════════════════════════════════════════════════════
    // MAIN ENTRY POINT — called by cron (morning & evening)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Send WhatsApp + Email update to all active sales executives
     * $session = 'Morning' or 'Evening'
     */
    public function sendDailyUpdates(string $session = 'Morning'): void
    {
        Log::info("SALES NOTIFICATION: Starting {$session} update run");

        // Get all active sales executives
        $salesExecTypeId = UserType::where('user_type', UserType::SALES_EXECUTIVE)
            ->value('id');

        if (!$salesExecTypeId) {
            Log::error('SALES NOTIFICATION: Could not find SALES_EXECUTIVE user type');
            return;
        }

        // Get ALL sales executives — active/inactive doesn't matter
        // Sending is controlled purely by whether they have an active target this month
        $executives = User::where('user_type_id', $salesExecTypeId)
            ->whereNotNull('contact_number')
            ->get();

        Log::info("SALES NOTIFICATION: Found {$executives->count()} active executives");

        foreach ($executives as $executive) {
            try {
                $this->sendToExecutive($executive, $session);
            } catch (\Exception $e) {
                Log::error("SALES NOTIFICATION: Failed for executive {$executive->id} ({$executive->name})", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("SALES NOTIFICATION: {$session} update run complete");
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PER EXECUTIVE — fetch data and send
    // ═════════════════════════════════════════════════════════════════════════

    private function sendToExecutive(User $executive, string $session): void
    {
        $now          = Carbon::now('Asia/Kolkata');
        $currentYear  = $now->year;
        $currentMonth = $now->month;
        $monthName    = $now->format('F Y');  // e.g. "March 2026"

        // ── 1. Get active target for this month ───────────────────────────
        $target = Target::where('sales_executive_id', $executive->id)
            ->where('year', $currentYear)
            ->where('month', $currentMonth)
            ->where('status', 'active')
            ->first();

        // ── SKIP if no active target for this month ───────────────────────
        if (!$target) {
            Log::info("SALES NOTIFICATION: Skipping {$executive->name} — no active target for {$monthName}");
            return;
        }

        $monthlyTarget = (float) $target->target_amount;

        // ── 2. Calculate sales completed this month ───────────────────────
        $salesCompleted = $this->getSalesCompleted($executive->id, $currentYear, $currentMonth);

        // ── 3. Follow-ups for today ───────────────────────────────────────
        $followupsToday = $this->getFollowupsToday($executive->id, $now);
        

        // ── 4. Sale to achieve today (client's formula) ───────────────────
        // (target - sales_completed) / (25 - today)  |  if today >= 25 → divide by 1
        $today         = $now->day;
        $days          = max(1, 25 - $today);
        $saleToAchieve = $monthlyTarget > 0
            ? max(0, round(($monthlyTarget - $salesCompleted) / $days, 0))
            : 0;

        // ── 5. Achievement % ──────────────────────────────────────────────
        $targetAchievedPct = $monthlyTarget > 0
            ? round(($salesCompleted / $monthlyTarget) * 100)
            : 0;

        Log::info("FOLLOWUP SUMMARY: [{$executive->name}] (ID: {$executive->id})", [
            'date'           => $now->toDateString(),
            'followups_today' => $followupsToday,
            'sales_completed' => $salesCompleted,
            'monthly_target'  => $monthlyTarget,
            'sale_to_achieve' => $saleToAchieve,
        ]);

        Log::info("SALES NOTIFICATION: Data for {$executive->name}", [
            'sales_completed'     => $salesCompleted,
            'monthly_target'      => $monthlyTarget,
            'followups_today'     => $followupsToday,
            'sale_to_achieve'     => $saleToAchieve,
            'target_achieved_pct' => $targetAchievedPct,
            'days_remaining'      => $days,
        ]);

        // ── 6. Send WhatsApp ──────────────────────────────────────────────
        $this->sendWhatsApp($executive, $monthName, $salesCompleted, $monthlyTarget, $followupsToday, $saleToAchieve);

        // ── 7. Send Email ─────────────────────────────────────────────────
       // $this->sendEmail($executive, $session, $monthName, $salesCompleted, $monthlyTarget, $followupsToday, $saleToAchieve, $targetAchievedPct, $days);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CALCULATE SALES COMPLETED THIS MONTH
    // Same logic as DashboardController / Target::updateAchievedAmount()
    // ═════════════════════════════════════════════════════════════════════════

    private function getSalesCompleted(string $executiveId, int $year, int $month): float
    {
        // Step 1: Get paid followup IDs this month
        $paidFollowupIds = PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $year)
            ->whereMonth('paid_date', $month)
            ->pluck('lead_followup_id')
            ->unique();

        // Step 2: Get candidate lead IDs for this executive
        $candidateLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($q) use ($executiveId) {
                $q->where('representative_user_id', $executiveId);
            })
            ->pluck('lead_id')
            ->unique();

        if ($candidateLeadIds->isEmpty()) return 0;

        // Step 3: Only keep leads whose FIRST approved payment is in this month
        $allFollowupIdsByLead = LeadFollowup::whereIn('lead_id', $candidateLeadIds)
            ->get(['id', 'lead_id'])
            ->groupBy('lead_id')
            ->map(fn($g) => $g->pluck('id'));

        $allFollowupIdsFlat = $allFollowupIdsByLead->flatten()->unique();

        $firstPaymentPerLead = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsFlat)
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

        $paidLeadIds = $firstPaymentPerLead->filter(function ($first) use ($year, $month) {
            if (!$first || empty($first->paid_date)) return false;
            $d = Carbon::parse($first->paid_date);
            return $d->year === $year && $d->month === $month;
        })->keys();

        if ($paidLeadIds->isEmpty()) return 0;

        // Step 4: Sum (total_amount - refunds) for qualifying followups
        $allFollowups = LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($q) use ($executiveId) {
                $q->where('representative_user_id', $executiveId);
            })
            ->get();

        $achieved = $allFollowups->groupBy('lead_id')->map(function ($group) {
            $qualifying = $group->filter(fn($f) => in_array($f->status, [2, 5, 7, 8]));
            return $qualifying->sortByDesc('created_at')->first();
        })->filter()->sum(function ($f) {
            $followupIds = LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
            $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $followupIds)
                ->whereIn('status', [1, 2])
                ->sum('refund_amount');
            return max(0, (float) $f->total_amount - $refund);
        });

        return (float) $achieved;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // FOLLOW-UPS SCHEDULED FOR TODAY
    // ═════════════════════════════════════════════════════════════════════════

    // private function getFollowupsToday(string $executiveId, Carbon $now): int
    // {
    //     // Step 1: Get all followups for today for this executive
    //     $todayFollowups = LeadFollowup::whereHas('enquiry', function ($q) use ($executiveId) {
    //             $q->where('representative_user_id', $executiveId);
    //         })
    //         ->whereDate('next_followup_date', $now->toDateString())
    //         ->with(['enquiry', 'lead'])
    //         ->get();
 
    //     Log::info("FOLLOWUP DEBUG: Raw followups from DB for executive [{$executiveId}]", [
    //         'date'          => $now->toDateString(),
    //         'total_raw'     => $todayFollowups->count(),
    //         'raw_followups' => $todayFollowups->map(fn($f) => [
    //             'followup_id'        => $f->id,
    //             'lead_id'            => $f->lead_id,
    //             'status'             => $f->status,
    //             'next_followup_date' => $f->next_followup_date,
    //             'lead_name'          => optional($f->enquiry?->lead)->name ?? 'N/A',
    //             'enquiry_id'         => $f->enquiry_id ?? 'N/A',
    //         ])->values()->toArray(),
    //     ]);
    //     // Step 2: Group by lead_id → keep only the LATEST per lead
    //     // (same as dashboard: groupBy lead_id → sortByDesc next_followup_date → first)
    //     $latestPerLead = $todayFollowups
    //         ->groupBy('lead_id')
    //         ->map(function ($group) {
    //             return $group->sortByDesc('next_followup_date')->first();
    //         })
    //         ->values();

    //     Log::info("FOLLOWUP DEBUG: After dedup (latest per lead) for executive [{$executiveId}]", [
    //         'date'             => $now->toDateString(),
    //         'unique_leads'     => $latestPerLead->count(),
    //         'final_followups'  => $latestPerLead->map(fn($f) => [
    //             'followup_id'        => $f->id,
    //             'lead_id'            => $f->lead_id,
    //             'status'             => $f->status,
    //             'next_followup_date' => $f->next_followup_date,
    //             'lead_name'          => optional($f->enquiry?->lead)->name ?? 'N/A',
    //             'enquiry_id'         => $f->enquiry_id ?? 'N/A',
    //         ])->values()->toArray(),
    //     ]);

 
    //     // Step 3: Return count of unique leads with followup today
    //     return $latestPerLead->count();
    // }

    // private function getFollowupsToday(string $executiveId, Carbon $now): int
    // {
    //     $today = $now->toDateString();
 
    //     // Step 1: Get all candidate leads that have any open followup
    //     // (today or missed) for this executive
    //     $todayLeadIds = LeadFollowup::whereHas('enquiry', function ($q) use ($executiveId) {
    //             $q->where('representative_user_id', $executiveId);
    //         })
    //         ->whereDate('next_followup_date', $today)
    //         ->whereNotIn('status', [2, 5])
    //         ->pluck('lead_id')
    //         ->unique();
 
    //     $missedLeadIds = LeadFollowup::whereHas('enquiry', function ($q) use ($executiveId) {
    //             $q->where('representative_user_id', $executiveId);
    //         })
    //         ->whereDate('next_followup_date', '<', $today)
    //         ->whereIn('status', [0, 1, 4])
    //         ->pluck('lead_id')
    //         ->unique();
 
    //     // Combine unique lead IDs from both buckets
    //     $allLeadIds = $todayLeadIds->merge($missedLeadIds)->unique();
 
    //     if ($allLeadIds->isEmpty()) {
    //         Log::info("FOLLOWUP COUNT: [{$executiveId}] no candidate leads", ['date' => $today]);
    //         return 0;
    //     }
 
    //     // Step 2: For each lead → get absolute latest followup
    //     // and apply same rules as UpcomingFollowUpController
    //     $count = 0;
 
    //     foreach ($allLeadIds as $leadId) {
    //         $latest = LeadFollowup::where('lead_id', $leadId)
    //             ->orderByDesc('next_followup_date')
    //             ->orderByDesc('created_at')
    //             ->first();
 
    //         if (!$latest) continue;
 
    //         // If latest is completed or cancelled → skip (not shown on page)
    //         if (in_array($latest->status, [2, 5])) continue;
 
    //         $latestDate = $latest->next_followup_date
    //             ? $latest->next_followup_date->toDateString()
    //             : null;
 
    //         if (!$latestDate) continue;
 
    //         // Show if: scheduled today (normal) OR past date + open (yellow/missed)
    //         if ($latestDate === $today) {
    //             $count++;
    //         } elseif ($latestDate < $today && in_array($latest->status, [0, 1, 4])) {
    //             $count++;
    //         }
    //         // If latest is a FUTURE date → skip (newer followup scheduled, not due yet)
    //     }
 
    //     Log::info("FOLLOWUP COUNT: [{$executiveId}]", [
    //         'date'             => $today,
    //         'today_leads'      => $todayLeadIds->count(),
    //         'missed_leads'     => $missedLeadIds->count(),
    //         'candidate_leads'  => $allLeadIds->count(),
    //         'final_count'      => $count,
    //     ]);
 
    //     return $count;
    // }
    private function getFollowupsToday(string $executiveId, Carbon $now): int
    {
        $fromDate = $now->copy()->startOfDay();
 
        // Base query — same as UpcomingFollowUpController
        $baseQuery = LeadFollowup::with(['enquiry', 'enquiry.representative'])
            ->whereHas('enquiry', function ($q) use ($executiveId) {
                $q->where('representative_user_id', $executiveId);
            });
 
        // Today's open followups
        $todayOpenFollowups = (clone $baseQuery)
            ->whereDate('next_followup_date', '=', $fromDate)
            ->whereNotIn('status', [2, 5])
            ->get();
 
        // Missed open followups (yellow)
        $missedOpenFollowups = (clone $baseQuery)
            ->whereDate('next_followup_date', '<', $fromDate)
            ->whereIn('status', [0, 1, 4])
            ->get();
 
        // Combine
        $allRelevantFollowups = $todayOpenFollowups->merge($missedOpenFollowups);
 
        // For each lead → get absolute latest followup and apply page rules
        $processedLeads = collect();
        $count          = 0;
 
        foreach ($allRelevantFollowups as $followup) {
            $leadId = $followup->lead_id;
 
            if ($processedLeads->contains($leadId)) continue;
 
            // Get absolute latest followup for this lead
            $latest = LeadFollowup::where('lead_id', $leadId)
                ->orderByDesc('next_followup_date')
                ->orderByDesc('created_at')
                ->first();
 
            if ($latest) {
                // If latest is completed/cancelled → skip (same as page)
                if (in_array($latest->status, [2, 5])) {
                    $processedLeads->push($leadId);
                    continue;
                }
 
                if ($latest->next_followup_date) {
                    $latestDate   = $latest->next_followup_date->toDateString();
                    $selectedDate = $fromDate->toDateString();
 
                    if ($latestDate === $selectedDate) {
                        // Scheduled today → count (normal row on page)
                        $count++;
                    } elseif ($latest->next_followup_date->lt($fromDate) && in_array($latest->status, [0, 1, 4])) {
                        // Past date + open → count (yellow row on page)
                        $count++;
                    }
                    // Future date → skip (not shown on page)
                }
            }
 
            $processedLeads->push($leadId);
        }
 
        Log::info("FOLLOWUP COUNT: [{$executiveId}]", [
            'date'            => $fromDate->toDateString(),
            'today_raw'       => $todayOpenFollowups->count(),
            'missed_raw'      => $missedOpenFollowups->count(),
            'final_count'     => $count,
        ]);
 
        return $count;
    }
    // ═════════════════════════════════════════════════════════════════════════
    // SEND WHATSAPP
    // ═════════════════════════════════════════════════════════════════════════

    private function sendWhatsApp(
        User $executive,
        string $monthName,
        float $salesCompleted,
        float $monthlyTarget,
        int $followupsToday,
        float $saleToAchieve
    ): void {
        // Clean contact number — remove +, spaces, dashes
        $toNumber = preg_replace('/[^0-9]/', '', $executive->contact_number);

        if (empty($toNumber)) {
            Log::warning("SALES NOTIFICATION: No contact number for {$executive->name}, skipping WhatsApp");
            return;
        }

        $sendMessageController = new SendMessageController();
        $sendMessageController->sendWhatsAppMetaMessage(
            $toNumber,
            $executive->name,                              // header {{1}}
            number_format($salesCompleted, 0),             // body {{1}}
            number_format($monthlyTarget, 0),              // body {{2}}
            (string) $followupsToday,                      // body {{3}}
            number_format($saleToAchieve, 0)               // body {{4}}
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SEND EMAIL
    // ═════════════════════════════════════════════════════════════════════════

    private function sendEmail(
        User $executive,
        string $session,
        string $monthName,
        float $salesCompleted,
        float $monthlyTarget,
        int $followupsToday,
        float $saleToAchieve,
        int $targetAchievedPct,
        int $daysRemaining
    ): void {
        if (empty($executive->email)) {
            Log::warning("SALES NOTIFICATION: No email for {$executive->name}, skipping email");
            return;
        }

        $data = [
            'executive_name'      => $executive->name,
            'month_name'          => $monthName,
            'session'             => $session,
            'sales_completed'     => $salesCompleted,
            'monthly_target'      => $monthlyTarget,
            'followups_today'     => $followupsToday,
            'sale_to_achieve'     => $saleToAchieve,
            'target_achieved_pct' => $targetAchievedPct,
            'days_remaining'      => $daysRemaining,
        ];

        \Mail::send('emails.sales-executive-update', ['data' => $data], function ($message) use ($executive, $session, $monthName) {
            $message->to($executive->email, $executive->name)
                    ->subject("Your {$session} Sales Update – {$monthName} | Accretion Aviation");
        });

        Log::info("SALES NOTIFICATION: Email sent to {$executive->name} ({$executive->email})");
    }
}