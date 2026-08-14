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
use App\Services\EmailLeadFollowupService;

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

            // $salesperson = $this->pickSalesperson($lead, $settings);
            if (
                str_starts_with(
                    (string) $queueItem->reason,
                    'email_'
                )
            ) {
                $salesperson = app(
                    EmailLeadAllocationService::class
                )->pickSalesperson(
                    $lead,
                    $settings
                );
            } else {
               $whatsAppIntegration =
    \App\Models\WhatsAppLeadIntegration::query()
        ->where('lead_id', $lead->id)
        ->first();

        if (
            $whatsAppIntegration
            && $whatsAppIntegration->product_id
        ) {

            $salesperson =
                app(
                    \App\Services\WhatsAppProductAllocationService::class
                )->findUser(
                    $whatsAppIntegration->product_id
                );

        } elseif ($whatsAppIntegration) {

            /*
            * WhatsApp product was not resolved.
            *
            * Do NOT give it to a random salesperson.
            */
            $salesperson = null;

        } else {

            /*
            * Existing IVR / Email / manual queue behavior
            * remains exactly as before.
            */
            $salesperson =
                $this->pickSalesperson(
                    $lead,
                    $settings
                );
        }
            }
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

        /*
|--------------------------------------------------------------------------
| WhatsApp / WhatCRM assignment callback
|--------------------------------------------------------------------------
|
| If this queued lead originally came from WhatCRM,
| update the WhatsApp integration record and send
| the assigned CRM salesperson back to n8n.
|
| IVR / Email / Manual leads are unaffected because
| they will not have a WhatsAppLeadIntegration record.
|
*/

        $whatsAppIntegration =
            \App\Models\WhatsAppLeadIntegration::query()
                ->where('lead_id', $lead->id)
                ->first();

        if ($whatsAppIntegration) {

            $whatsAppIntegration->update([
                'status' => 'assigned',

                'assigned_user_id' =>
                    $salesperson->id,

                'assigned_at' =>
                    now(),
            ]);

            app(
                \App\Services\WhatCrmAssignmentWebhookService::class
            )->send(
                $whatsAppIntegration
            );
        }

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
        $emailLeadLog = $lead
    ->emailLeadLogs()
    ->whereNull(
        'followup_created_at'
    )
    ->orderByDesc(
        'received_at'
    )
    ->first();

if ($emailLeadLog) {
    app(
        EmailLeadFollowupService::class
    )->createIfNeeded(
        $lead,
        $emailLeadLog
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

public function getPopupData(
    User $user,
    ?Carbon $currentTime = null
): array {
    $settings = LeadAllocationSetting::getActiveSettings();
    $now = $currentTime ?? now();

    $queueCount = LeadAllocationQueue::where(
        'status',
        'queued'
    )->count();

    $availability = SalespersonAvailability::firstOrCreate(
        ['user_id' => $user->id],
        [
            'state' => 'offline',
            'is_available' => false,
            'is_opted_in' => false,
        ]
    );

    $isOfficeOpen = $this->isOfficeOpen($settings);

    /*
     * Automatic popup is ONLY for Sales Executive
     * and Sales Manager roles.
     */
    $isEligibleSalesRole = $user->userType
        && in_array(
            $user->userType->user_type,
            UserType::SALES_ROLES
        );

    /*
     * Has this salesperson already answered
     * today's automatic availability popup?
     */
    $respondedToday = $availability->last_response_at
        && $availability->last_response_at->isSameDay($now);

    $showPopup = false;
    $popupReason = 'not_required';

    /*
     * Automatic popup:
     *
     * - Sales Executive / Sales Manager only
     * - Office hours only
     * - Once per day
     * - Does NOT depend on queued leads
     */
    if (
        $isEligibleSalesRole
        && $isOfficeOpen
        && !$respondedToday
    ) {
        $showPopup = true;
        $popupReason = 'daily_availability';
    }

    if ($showPopup) {
        $availability->last_popup_at = $now;
        $availability->save();
    }

    return [
        'show_popup' => $showPopup,
        'queue_count' => $queueCount,
        'availability' => $availability,
        'office_open' => $isOfficeOpen,
        'popup_reason' => $popupReason,
        'responded_today' => $respondedToday,
        'is_sales_role' => $isEligibleSalesRole,
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

   protected function pickSalesperson(
    Lead $lead,
    LeadAllocationSetting $settings
): ?User {
    /*
     * STEP 1:
     * All ACTIVE sales users who have explicitly
     * opted in and are currently available.
     */
    $allAvailableUsers = User::query()
        ->whereHas('userType', function ($query) {
            $query->whereIn(
                'user_type',
                UserType::SALES_ROLES
            );
        })
        ->where('status', 1)
        ->get()
        ->filter(function ($user) {
            $availability = SalespersonAvailability::where(
                'user_id',
                $user->id
            )->first();

            return $availability
                && $availability->is_available
                && $availability->is_opted_in;
        })
        ->values();

    /*
     * Nobody is available today.
     * Keep the lead in queue.
     */
    if ($allAvailableUsers->isEmpty()) {
        return null;
    }

    $eligibleUsers = $allAvailableUsers;
    $allocationMethod = $settings->allocation_method;

    /*
     * STEP 2:
     * IVR / DTMF restriction.
     */
    $ivrCallLog = $lead->ivrCallLogs()
        ->orderByDesc('call_start_at')
        ->first();

    if ($ivrCallLog) {
        $pool = app(DtmfAllocationService::class)
            ->poolForCallLog($ivrCallLog);

        $allowedIvrUserIds = collect(
            $pool['user_ids'] ?? []
        );

        $allocationMethod =
            $pool['mode']
            ?? $allocationMethod;

        if ($allowedIvrUserIds->isNotEmpty()) {
            $ivrEligibleUsers = $eligibleUsers
                ->filter(function ($user) use (
                    $allowedIvrUserIds
                ) {
                    return $allowedIvrUserIds
                        ->contains($user->id);
                })
                ->values();

            /*
             * Use IVR pool only when at least
             * one mapped salesperson is available.
             */
            if ($ivrEligibleUsers->isNotEmpty()) {
                $eligibleUsers = $ivrEligibleUsers;
            } else {
                /*
                 * IVR team is absent.
                 *
                 * Requirement:
                 * Assign randomly to another available
                 * and opted-in salesperson.
                 */
                return $allAvailableUsers->random();
            }
        }
    }

    /*
     * STEP 3:
     * Product restriction.
     */
    $leadProductIds = $lead->product_ids_array;

    if (!empty($leadProductIds)) {
        $allowedSalespersonIds = Product::whereIn(
            'id',
            $leadProductIds
        )
            ->get()
            ->pluck('user_ids')
            ->flatMap(function ($userIds) {
                if (is_string($userIds)) {
                    $userIds = json_decode(
                        $userIds,
                        true
                    ) ?? [];
                }

                return is_array($userIds)
                    ? $userIds
                    : [];
            })
            ->filter()
            ->unique()
            ->values();

        if ($allowedSalespersonIds->isNotEmpty()) {
            $productEligibleUsers = $eligibleUsers
                ->filter(function ($user) use (
                    $allowedSalespersonIds
                ) {
                    return $allowedSalespersonIds
                        ->contains($user->id);
                })
                ->values();

            if ($productEligibleUsers->isNotEmpty()) {
                $eligibleUsers = $productEligibleUsers;
            } else {
                /*
                 * Product executives are absent.
                 *
                 * Fall back randomly to another
                 * available + opted-in salesperson.
                 */
                return $allAvailableUsers->random();
            }
        }
    }

    if ($eligibleUsers->isEmpty()) {
        return $allAvailableUsers->random();
    }

    /*
     * STEP 4:
     * Configured allocation method.
     */
    if ($allocationMethod === 'random') {
        return $eligibleUsers
            ->values()
            ->random();
    }

    /*
     * Balanced:
     * person having fewer leads TODAY gets
     * the next lead.
     */
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
