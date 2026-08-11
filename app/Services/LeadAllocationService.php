<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\LeadAllocationQueue;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LeadAllocationService
{
    public function processPendingLeads(): array
    {
        $settings = LeadAllocationSetting::getActiveSettings();
        if (!$settings->auto_allocation_enabled) {
            return ['processed' => 0, 'skipped' => 'disabled'];
        }

        if (!$this->isOfficeOpen($settings)) {
            return ['processed' => 0, 'skipped' => 'office_closed'];
        }

        $queueItems = LeadAllocationQueue::query()
            ->where('status', 'queued')
            ->orderBy('queued_at')
            ->get();

        $processed = 0;
        foreach ($queueItems as $queueItem) {
            $lead = Lead::find($queueItem->lead_id);
            if (!$lead || !empty($lead->representative_user_id)) {
                $queueItem->status = 'skipped';
                $queueItem->reason = 'already_assigned';
                $queueItem->save();
                continue;
            }

            $salesperson = $this->pickSalesperson($lead, $settings);
            if (!$salesperson) {
                $queueItem->attempt_count += 1;
                $queueItem->save();
                continue;
            }

         DB::transaction(function () use ($lead, $salesperson, $queueItem) {
            $lead->representative_user_id = $salesperson->id;
            $lead->save();

            $queueItem->assigned_to = $salesperson->id;
            $queueItem->status = 'assigned';
            $queueItem->processed_at = now();
            $queueItem->save();

            LeadAllocationLog::create([
                'lead_id' => $lead->id,
                'salesperson_id' => $salesperson->id,
                'action' => 'assigned',
                'result' => 'success',
                'details' => 'Auto assigned via queue',
            ]);
        });

        $ivrCallLog = $lead->ivrCallLogs()
            ->whereNull('initial_followup_created_at')
            ->orderByDesc('call_start_at')
            ->first();

        if ($ivrCallLog) {
            app(IvrFollowupService::class)->createIfNeeded(
                $lead,
                $ivrCallLog,
                $ivrCallLog->processing_status === 'repeat_lead'
            );
        }

            $processed++;
        }

        return ['processed' => $processed];
    }

    public function queueLead(Lead $lead, ?string $reason = null): void
    {
        if (!empty($lead->representative_user_id)) {
            return;
        }

        $existing = LeadAllocationQueue::where('lead_id', $lead->id)->first();
        if ($existing) {
            return;
        }

        if (LeadAllocationQueue::where('lead_id', $lead->id)->exists()) {
            return;
        }

        LeadAllocationQueue::create([
            'lead_id' => $lead->id,
            'status' => 'queued',
            'reason' => $reason ?? 'new_lead',
        ]);

        LeadAllocationLog::create([
            'lead_id' => $lead->id,
            'action' => 'queued',
            'result' => 'success',
            'details' => $reason ?? 'new_lead',
        ]);
    }

    public function acceptPopup(User $user): void
    {
        $availability = SalespersonAvailability::firstOrCreate(
            ['user_id' => $user->id],
            ['state' => 'available', 'is_available' => true, 'is_opted_in' => true]
        );

        $availability->state = 'available';
        $availability->is_available = true;
        $availability->is_opted_in = true;
        $availability->last_response_at = now();
        $availability->last_popup_at = now();
        $availability->save();

        $this->processPendingLeads();

        LeadAllocationLog::create([
            'salesperson_id' => $user->id,
            'action' => 'popup_accepted',
            'result' => 'success',
            'details' => 'Salesperson opted in and pending leads were processed',
        ]);
    }

    public function declinePopup(User $user): void
    {
        $availability = SalespersonAvailability::firstOrCreate(
            ['user_id' => $user->id],
            ['state' => 'offline', 'is_available' => false, 'is_opted_in' => false]
        );

        $availability->state = 'offline';
        $availability->is_available = false;
        $availability->is_opted_in = false;
        $availability->last_response_at = now();
        $availability->last_popup_at = now();
        $availability->save();

        LeadAllocationLog::create([
            'salesperson_id' => $user->id,
            'action' => 'popup_declined',
            'result' => 'success',
            'details' => 'Salesperson opted out',
        ]);
    }

 public function getPopupData(User $user, ?Carbon $currentTime = null): array
{
    $settings = LeadAllocationSetting::getActiveSettings();
    $now = $currentTime ?? now();

    $queueCount = LeadAllocationQueue::where('status', 'queued')->count();

    $availability = SalespersonAvailability::firstOrCreate(
        ['user_id' => $user->id],
        [
            'state' => 'offline',
            'is_available' => false,
            'is_opted_in' => false,
        ]
    );

    $isOfficeOpen = $this->isOfficeOpen($settings);

    $popupIntervalMinutes = max(
        120,
        (int) ($settings->popup_interval_minutes ?? 120)
    );

    $showPopup = false;
    $popupReason = 'not_required';

    /*
     * Has the salesperson already answered the
     * availability popup today?
     */
    $respondedToday = $availability->last_response_at
        && $availability->last_response_at->isSameDay($now);

    /*
     * IMPORTANT:
     *
     * We no longer require queued leads for the FIRST popup.
     *
     * If office is open and salesperson has not given
     * today's availability response, show the popup.
     */
    if ($isOfficeOpen && !$respondedToday) {
        $showPopup = true;
        $popupReason = 'daily_availability';
    }

    /*
     * If salesperson already answered NO today,
     * we can show the popup again after the configured
     * interval, but only when leads are actually waiting.
     */
    if (
        $isOfficeOpen
        && $respondedToday
        && !$availability->is_opted_in
        && $queueCount > 0
    ) {
        $lastPopupAt = $availability->last_popup_at
            ?? $availability->last_response_at;

        if (
            !$lastPopupAt
            || $lastPopupAt->diffInMinutes($now) >= $popupIntervalMinutes
        ) {
            $showPopup = true;
            $popupReason = 'waiting_for_leads';
        }
    }

    /*
     * Record when the popup was actually shown.
     */
    if ($showPopup) {
        $availability->last_popup_at = $now;
        $availability->save();
    }

    return [
        'show_popup' => $showPopup,
        'queue_count' => $queueCount,
        'availability' => $availability,
        'popup_interval_minutes' => $popupIntervalMinutes,
        'office_open' => $isOfficeOpen,
        'popup_reason' => $popupReason,
    ];
}

    public function shouldShowPopup(
        User $user,
        bool $isOfficeOpen,
        ?Carbon $lastPromptAt,
        Carbon $now,
        int $popupIntervalMinutes,
        bool $isOptedIn,
        bool $hasNewQueuedLeadsSinceLastPopup
    ): bool
    {
        if (!$user->userType || !in_array($user->userType->user_type, UserType::SALES_ROLES)) {
            return false;
        }

        if (!$isOfficeOpen) {
            return false;
        }

        if (!$lastPromptAt) {
            return true;
        }

        if ($lastPromptAt->isSameDay($now)) {
            if ($lastPromptAt->diffInMinutes($now) < $popupIntervalMinutes) {
                return false;
            }

            return !$isOptedIn || $hasNewQueuedLeadsSinceLastPopup;
        }

        return true;
    }

    protected function pickSalesperson(Lead $lead, LeadAllocationSetting $settings): ?User
    {
        $eligibleUsers = User::query()
            ->whereHas('userType', function ($query) {
                $query->whereIn('user_type', UserType::SALES_ROLES);
            })
            ->where('status', 1)
            ->get();

        $eligibleUsers = $eligibleUsers->filter(function ($user) {
            $availability = SalespersonAvailability::where('user_id', $user->id)->first();
            return $availability ? $availability->is_available && $availability->is_opted_in : false;
        });

        if ($eligibleUsers->isEmpty()) {
            return null;
        }

        $ivrMode = null;

// $ivrCallLog = $lead->ivrCallLogs()
//     ->orderByDesc('call_start_at')
//     ->first();
$ivrCallLog = $lead->ivrCallLogs()
    ->latest('call_start_at')
    ->first();

if ($ivrCallLog) {
    $pool = app(DtmfAllocationService::class)
        ->poolForCallLog($ivrCallLog);

    $allowedIvrUserIds = collect(
        $pool['user_ids'] ?? []
    );

    $ivrMode = $pool['mode'] ?? 'balanced';

    if ($allowedIvrUserIds->isNotEmpty()) {
        $eligibleUsers = $eligibleUsers->filter(
            function ($user) use ($allowedIvrUserIds) {
                return $allowedIvrUserIds->contains(
                    $user->id
                );
            }
        );
    }

    if ($eligibleUsers->isEmpty()) {
        return null;
    }
}

        $leadProductIds = $lead->product_ids_array;
        if (!empty($leadProductIds)) {
            $allowedSalespersonIds = Product::whereIn('id', $leadProductIds)
                ->get()
                ->pluck('user_ids')
                ->flatMap(function ($userIds) {
                    if (is_string($userIds)) {
                        $userIds = json_decode($userIds, true) ?? [];
                    }

                    return is_array($userIds) ? $userIds : [];
                })
                ->filter()
                ->unique()
                ->values();

            if ($allowedSalespersonIds->isNotEmpty()) {
                $eligibleUsers = $eligibleUsers->filter(function ($user) use ($allowedSalespersonIds) {
                    return $allowedSalespersonIds->contains($user->id);
                });

                if ($eligibleUsers->isEmpty()) {
                    return null;
                }
            }
        }

        $eligibleUsers = $eligibleUsers->sortBy(function ($user) {
            $availability = SalespersonAvailability::where('user_id', $user->id)->first();
            $score = 0;
            if ($availability && $availability->state === 'available') {
                $score += 10;
            }

            return $score;
        });

        // if ($settings->allocation_method === 'balanced') {
        //     return $eligibleUsers->sortBy(function ($user) {
        //         $assignedCount = Lead::where('representative_user_id', $user->id)->count();
        //         $queuedCount = LeadAllocationQueue::where('assigned_to', $user->id)->where('status', 'queued')->count();
        //         return $assignedCount + $queuedCount;
        //     })->first();
        // }
       $allocationMethod = $ivrMode ?? $settings->allocation_method;

        if ($allocationMethod === 'random') {
            return $eligibleUsers
                ->values()
                ->random();
        }

        if ($allocationMethod === 'balanced') {
            return $eligibleUsers
                ->sortBy(function ($user) {
                    return Lead::where(
                        'representative_user_id',
                        $user->id
                    )
                    ->whereDate(
                        'created_at',
                        now()->toDateString()
                    )
                    ->count();
                })
                ->first();
        }


        return $eligibleUsers->first();
    }

    public function isOfficeOpenForDebug(LeadAllocationSetting $settings, ?Carbon $now = null): bool
    {
        $current = $now ?? Carbon::now();
        $start = Carbon::parse($current->toDateString() . ' ' . $settings->office_start_time);
        $end = Carbon::parse($current->toDateString() . ' ' . $settings->office_end_time);

        return $current->between($start, $end);
    }

    protected function isOfficeOpen(LeadAllocationSetting $settings): bool
    {
        return $this->isOfficeOpenForDebug($settings, Carbon::now());
    }
}
