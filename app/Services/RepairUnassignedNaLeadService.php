<?php

namespace App\Services;

use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\LeadAllocationQueue;
use App\Models\LeadAllocationSetting;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppLeadIntegration;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RepairUnassignedNaLeadService
{
    private const ACTIVE_BLOCKING_TERMINAL_STATUSES = [2, 5];
    private const KNOWN_FOLLOWUP_STATUSES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

    public function __construct(
        private LeadAllocationService $leadAllocationService,
        private LeadProductRoutingService $productRouter,
        private WhatsAppProductAllocationService $whatsAppAllocator,
        private EmailLeadAllocationService $emailAllocator,
        private DtmfAllocationService $dtmfAllocator,
        private LeadSourceFollowupService $sourceFollowups
    ) {
    }

    public function repair(int $days = 10, bool $commit = false): array
    {
        $days = max(1, $days);
        $summary = $this->emptySummary($commit, $days);

        $this->candidateLeads($days)
            ->each(function (Lead $lead) use ($commit, &$summary) {
                $summary['scanned']++;

                $phone = $this->normalizedPhone(
                    optional($lead->client)->contact_number
                );

                if ($phone === '') {
                    $summary['skipped']++;
                    $summary['items'][] = $this->item(
                        $lead,
                        'skipped',
                        'missing_phone'
                    );

                    return;
                }

                $activeDuplicate = $this->activeDuplicateForPhone(
                    $lead,
                    $phone
                );

                if ($activeDuplicate) {
                    $this->handleDuplicate(
                        $lead,
                        $activeDuplicate,
                        $commit,
                        $summary
                    );

                    return;
                }

                $this->handleSingleLead(
                    $lead,
                    $commit,
                    $summary
                );
            });

        return $summary;
    }

    private function candidateLeads(int $days): Collection
    {
        $from = Carbon::now('Asia/Kolkata')
            ->subDays($days)
            ->startOfDay();

        return Lead::query()
            ->with([
                'client',
                'leadFollowups' => function ($query) {
                    $query->orderByDesc('created_at');
                },
            ])
            ->whereNull('representative_user_id')
            ->where('created_at', '>=', $from)
            ->orderBy('created_at')
            ->get()
            ->filter(function (Lead $lead) {
                return $this->hasNaStatus($lead);
            })
            ->values();
    }

    private function hasNaStatus(Lead $lead): bool
    {
        $latest = $lead->leadFollowups->first();

        if (!$latest) {
            return true;
        }

        return !$this->isKnownFollowupStatus($latest->status);
    }

    private function handleDuplicate(
        Lead $badLead,
        Lead $activeLead,
        bool $commit,
        array &$summary
    ): void {
        $unsafeReason = $this->unsafeDuplicateReason(
            $badLead,
            $activeLead
        );

        if ($unsafeReason) {
            $summary['skipped']++;
            $summary['items'][] = $this->item(
                $badLead,
                'skipped',
                $unsafeReason,
                ['active_lead_id' => $activeLead->id]
            );

            return;
        }

        $summary['would_delete_duplicates']++;
        $summary['items'][] = $this->item(
            $badLead,
            $commit ? 'deleted_duplicate' : 'would_delete_duplicate',
            'active_duplicate_exists',
            ['active_lead_id' => $activeLead->id]
        );

        if (!$commit) {
            return;
        }

        DB::transaction(function () use ($badLead, $activeLead, &$summary) {
            $this->mergeDuplicateSourceData(
                $badLead,
                $activeLead
            );

            $this->createMergedDuplicateFollowup(
                $badLead,
                $activeLead
            );

            $this->deleteEmptyDuplicateLead($badLead);

            $summary['deleted_duplicates']++;
        });
    }

    private function handleSingleLead(
        Lead $lead,
        bool $commit,
        array &$summary
    ): void {
        $assignment = $this->resolveAssignment($lead);
        $summary['would_activate']++;

        if (!$commit) {
            if ($assignment['user']) {
                $summary['would_assign']++;
            } else {
                $summary['would_queue']++;
            }

            $summary['items'][] = $this->item(
                $lead,
                $assignment['user']
                    ? 'would_activate_assign'
                    : 'would_activate_queue',
                $assignment['reason'],
                [
                    'assigned_user_id' =>
                        optional($assignment['user'])->id,
                ]
            );

            return;
        }

        DB::transaction(function () use ($lead, $assignment, &$summary) {
            $lead = Lead::query()
                ->whereKey($lead->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!empty($lead->representative_user_id)) {
                $summary['skipped']++;
                $summary['items'][] = $this->item(
                    $lead,
                    'skipped',
                    'already_assigned'
                );

                return;
            }

            if ($assignment['product'] && empty($lead->product_ids_array)) {
                $lead->product_ids = [$assignment['product']->id];
                $lead->save();
            }

            if ($assignment['user']) {
                $lead->representative_user_id =
                    $assignment['user']->id;
                $lead->save();

                $this->markSourceAssigned(
                    $lead,
                    $assignment['user'],
                    $assignment['product']
                );

                LeadAllocationLog::create([
                    'lead_id' => $lead->id,
                    'salesperson_id' => $assignment['user']->id,
                    'action' => 'repair_assigned',
                    'result' => 'success',
                    'details' =>
                        'Old unassigned N/A lead repaired and assigned.',
                ]);

                $summary['assigned']++;
            } else {
                $this->ensureQueued(
                    $lead,
                    $assignment['reason']
                );

                $summary['queued']++;
            }

            if ($this->ensureActiveFollowup($lead)) {
                $summary['activated']++;
            }
        });
    }

    private function resolveAssignment(Lead $lead): array
    {
        $settings = LeadAllocationSetting::getActiveSettings();
        $profile = $this->sourceProfile($lead);
        $product = $this->leadProduct($lead)
            ?: $this->productRouter->resolveProduct($profile['text']);

        if (
            !$settings->auto_allocation_enabled
            || !$this->leadAllocationService
                ->isOfficeOpenForDebug($settings, now())
        ) {
            return [
                'user' => null,
                'product' => $product,
                'reason' => $this->queueReason(
                    $profile['type'],
                    $product,
                    $profile['text']
                ),
            ];
        }

        $user = match ($profile['type']) {
            'email' => $this->emailAllocator
                ->pickSalesperson($lead, $settings),

            'ivr' => $this->resolveIvrUser($lead),

            default => $this->whatsAppAllocator
                ->findUserForAssignment($product, $profile['text']),
        };

        return [
            'user' => $user,
            'product' => $product,
            'reason' => $this->queueReason(
                $profile['type'],
                $product,
                $profile['text']
            ),
        ];
    }

    private function resolveIvrUser(Lead $lead): ?User
    {
        $callLog = $this->latestIvrCallLog($lead);

        if (!$callLog) {
            return null;
        }

        if (app(IvrFollowupService::class)->isSuccessfulStatus(
            $callLog->dial_status
        )) {
            $user = $this->dtmfAllocator
                ->mappedUserForSuccessfulAgent(
                    $callLog->agent_number,
                    $callLog->agent_name
                );

            if ($user) {
                return $user;
            }
        }

        return $this->dtmfAllocator
            ->pickAvailableUser(
                $callLog->ivr_call_type_id,
                $callLog->raw_dtmf
            );
    }

    private function sourceProfile(Lead $lead): array
    {
        if ($this->latestEmailLog($lead)) {
            $emailLog = $this->latestEmailLog($lead);

            return [
                'type' => 'email',
                'source' => 'Email',
                'text' => trim(
                    (string) (
                        $emailLog->service_name
                        ?: $emailLog->email_body
                        ?: $lead->description
                    )
                ),
            ];
        }

        if ($this->latestIvrCallLog($lead)) {
            $callLog = $this->latestIvrCallLog($lead);

            return [
                'type' => 'ivr',
                'source' => 'VI CPaaS IVR',
                'text' => trim(
                    implode(' ', array_filter([
                        $callLog->call_type_code,
                        $callLog->raw_dtmf,
                        $lead->description,
                    ]))
                ),
            ];
        }

        $integration = $this->whatsAppIntegration($lead);

        if ($integration) {
            return [
                'type' => 'whatsapp',
                'source' => 'WhatsApp / WhatCRM',
                'text' => $this->whatsAppSourceText(
                    $integration->payload
                ) ?: (string) $lead->description,
            ];
        }

        $conversation = $this->whatsAppConversation($lead);

        if ($conversation) {
            return [
                'type' => 'whatsapp',
                'source' => 'WhatsApp / WhatCRM',
                'text' => trim(
                    (string) (
                        $conversation->last_message
                        ?: $lead->description
                    )
                ),
            ];
        }

        return [
            'type' => 'whatsapp',
            'source' => 'Lead Repair',
            'text' => trim((string) $lead->description),
        ];
    }

    private function queueReason(
        string $sourceType,
        ?Product $product,
        ?string $sourceText
    ): string {
        if ($sourceType === 'email') {
            return $this->productRouter
                ->isCharterProduct($product, $sourceText)
                ? 'email_charter_lead'
                : 'email_new_lead';
        }

        if ($sourceType === 'ivr') {
            return 'ivr_new_lead';
        }

        $route = $this->whatsAppAllocator
            ->assignmentRoute($product, $sourceText);

        if ($route === 'charter') {
            return 'whatsapp_charter_waiting';
        }

        if ($route === 'product') {
            return 'whatsapp_product_waiting';
        }

        return 'whatsapp_retail_waiting';
    }

    private function ensureActiveFollowup(Lead $lead): bool
    {
        $latest = $lead->leadFollowups()
            ->orderByDesc('created_at')
            ->first();

        if ($latest) {
            $changed = false;

            if (!$this->isKnownFollowupStatus($latest->status)) {
                $latest->status = 1;
                $changed = true;
            }

            if (!$latest->next_followup_date) {
                $latest->next_followup_date = now()->addMinutes(5);
                $changed = true;
            }

            if (
                empty($latest->followed_by)
                && !empty($lead->representative_user_id)
            ) {
                $latest->followed_by = $lead->representative_user_id;
                $changed = true;
            }

            if ($changed) {
                $latest->save();
            }

            return $changed;
        }

        $this->sourceFollowups->create(
            $lead,
            'Lead Repair',
            'Old unassigned N/A lead repaired. Default status set to Active.',
            [],
            true
        );

        return true;
    }

    private function ensureQueued(Lead $lead, string $reason): void
    {
        $queue = LeadAllocationQueue::query()
            ->where('lead_id', $lead->id)
            ->first();

        if ($queue) {
            $queue->status = 'queued';
            $queue->assigned_to = null;
            $queue->reason = $reason;
            $queue->processed_at = null;
            $queue->save();

            return;
        }

        $this->leadAllocationService
            ->queueLead($lead, $reason);
    }

    private function activeDuplicateForPhone(
        Lead $candidate,
        string $phone
    ): ?Lead {
        $expression = $this->digitsSql('clients.contact_number');

        $leads = Lead::query()
            ->with('leadFollowups')
            ->join('clients', 'clients.id', '=', 'leads.client_id')
            ->where('leads.id', '<>', $candidate->id)
            ->whereRaw("{$expression} LIKE ?", ['%' . $phone])
            ->select('leads.*')
            ->orderByDesc('leads.created_at')
            ->get();

        foreach ($leads as $lead) {
            $latest = $lead->leadFollowups()
                ->orderByDesc('created_at')
                ->first();

            if (
                $latest
                && $this->isActiveBlockingStatus($latest->status)
            ) {
                return $lead;
            }
        }

        return null;
    }

    private function unsafeDuplicateReason(
        Lead $badLead,
        Lead $activeLead
    ): ?string {
        $unsafeTables = [
            'lead_followups' => 'lead_id',
            'lead_passengers' => 'lead_id',
            'vouchers' => 'lead_id',
            'lead_vendor_payments' => 'lead_id',
            'lead_transfers' => 'lead_id',
        ];

        foreach ($unsafeTables as $table => $column) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && DB::table($table)
                    ->where($column, $badLead->id)
                    ->exists()
            ) {
                return 'unsafe_related_data_' . $table;
            }
        }

        if (
            Schema::hasTable('whatsapp_lead_integrations')
            && WhatsAppLeadIntegration::query()
                ->where('lead_id', $badLead->id)
                ->exists()
            && WhatsAppLeadIntegration::query()
                ->where('lead_id', $activeLead->id)
                ->exists()
        ) {
            return 'unsafe_whatsapp_integration_conflict';
        }

        if (
            Schema::hasTable('skyrack_lead_syncs')
            && DB::table('skyrack_lead_syncs')
                ->where('lead_id', $badLead->id)
                ->exists()
            && DB::table('skyrack_lead_syncs')
                ->where('lead_id', $activeLead->id)
                ->exists()
        ) {
            return 'unsafe_skyrack_sync_conflict';
        }

        return null;
    }

    private function mergeDuplicateSourceData(
        Lead $badLead,
        Lead $activeLead
    ): void {
        if (Schema::hasTable('email_lead_logs')) {
            DB::table('email_lead_logs')
                ->where('lead_id', $badLead->id)
                ->update(['lead_id' => $activeLead->id]);
        }

        if (Schema::hasTable('ivr_call_logs')) {
            DB::table('ivr_call_logs')
                ->where('lead_id', $badLead->id)
                ->update(['lead_id' => $activeLead->id]);
        }

        if (Schema::hasTable('whatsapp_conversations')) {
            DB::table('whatsapp_conversations')
                ->where('lead_id', $badLead->id)
                ->update([
                    'lead_id' => $activeLead->id,
                    'assigned_user_id' =>
                        $activeLead->representative_user_id,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('whatsapp_lead_integrations')) {
            DB::table('whatsapp_lead_integrations')
                ->where('lead_id', $badLead->id)
                ->update([
                    'lead_id' => $activeLead->id,
                    'status' => $activeLead->representative_user_id
                        ? 'assigned'
                        : 'existing_lead',
                    'assigned_user_id' =>
                        $activeLead->representative_user_id,
                    'assigned_at' => $activeLead->representative_user_id
                        ? now()
                        : null,
                    'updated_at' => now(),
                ]);
        }

        if (
            Schema::hasTable('lead_rides')
            && !DB::table('lead_rides')
                ->where('lead_id', $activeLead->id)
                ->exists()
        ) {
            DB::table('lead_rides')
                ->where('lead_id', $badLead->id)
                ->update([
                    'lead_id' => $activeLead->id,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('lead_allocation_logs')) {
            DB::table('lead_allocation_logs')
                ->where('lead_id', $badLead->id)
                ->update([
                    'lead_id' => $activeLead->id,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('skyrack_lead_syncs')) {
            DB::table('skyrack_lead_syncs')
                ->where('lead_id', $badLead->id)
                ->update([
                    'lead_id' => $activeLead->id,
                    'updated_at' => now(),
                ]);
        }
    }

    private function createMergedDuplicateFollowup(
        Lead $badLead,
        Lead $activeLead
    ): void {
        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $activeLead->id,
            'next_followup_date' => now()->addMinutes(5),
            'followup_note' => implode(PHP_EOL, array_filter([
                'Merged old duplicate N/A lead into this active lead.',
                'Duplicate Lead ID: ' . $badLead->id,
                trim((string) $badLead->description) !== ''
                    ? 'Duplicate Note: ' . trim((string) $badLead->description)
                    : null,
            ])),
            'followed_by' =>
                $activeLead->representative_user_id ?: null,
            'status' => 1,
        ]);
    }

    private function deleteEmptyDuplicateLead(Lead $lead): void
    {
        $safeDeleteTables = [
            'lead_allocation_queue',
            'lead_allocation_logs',
            'lead_rides',
            'skyrack_lead_syncs',
        ];

        foreach ($safeDeleteTables as $table) {
            if (
                Schema::hasTable($table)
                && Schema::hasColumn($table, 'lead_id')
            ) {
                DB::table($table)
                    ->where('lead_id', $lead->id)
                    ->delete();
            }
        }

        DB::table('leads')
            ->where('id', $lead->id)
            ->delete();
    }

    private function markSourceAssigned(
        Lead $lead,
        User $user,
        ?Product $product
    ): void {
        if (Schema::hasTable('whatsapp_conversations')) {
            DB::table('whatsapp_conversations')
                ->where('lead_id', $lead->id)
                ->update([
                    'assigned_user_id' => $user->id,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('whatsapp_lead_integrations')) {
            DB::table('whatsapp_lead_integrations')
                ->where('lead_id', $lead->id)
                ->update([
                    'product_id' => optional($product)->id,
                    'status' => 'assigned',
                    'assigned_user_id' => $user->id,
                    'assigned_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('email_lead_logs')) {
            DB::table('email_lead_logs')
                ->where('lead_id', $lead->id)
                ->update([
                    'processing_status' => 'lead_repaired_assigned',
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('ivr_call_logs')) {
            DB::table('ivr_call_logs')
                ->where('lead_id', $lead->id)
                ->update([
                    'processing_status' => 'lead_repaired_assigned',
                    'updated_at' => now(),
                ]);
        }
    }

    private function leadProduct(Lead $lead): ?Product
    {
        $ids = $lead->product_ids_array;

        if (empty($ids)) {
            return null;
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->first();
    }

    private function latestEmailLog(Lead $lead)
    {
        if (!Schema::hasTable('email_lead_logs')) {
            return null;
        }

        return $lead->emailLeadLogs()
            ->orderByDesc('received_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function latestIvrCallLog(Lead $lead): ?IvrCallLog
    {
        if (!Schema::hasTable('ivr_call_logs')) {
            return null;
        }

        return $lead->ivrCallLogs()
            ->orderByDesc('call_start_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function whatsAppIntegration(
        Lead $lead
    ): ?WhatsAppLeadIntegration {
        if (!Schema::hasTable('whatsapp_lead_integrations')) {
            return null;
        }

        return WhatsAppLeadIntegration::query()
            ->where('lead_id', $lead->id)
            ->first();
    }

    private function whatsAppConversation(Lead $lead)
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return null;
        }

        return DB::table('whatsapp_conversations')
            ->where('lead_id', $lead->id)
            ->orderByDesc('last_message_at')
            ->first();
    }

    private function whatsAppSourceText(?array $payload): ?string
    {
        foreach (['service', 'message', 'body'] as $key) {
            $value = trim((string) data_get($payload, $key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizedPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return '';
        }

        return strlen($digits) > 10
            ? substr($digits, -10)
            : $digits;
    }

    private function isKnownFollowupStatus($status): bool
    {
        if ($status === null || !is_numeric($status)) {
            return false;
        }

        return in_array(
            (int) $status,
            self::KNOWN_FOLLOWUP_STATUSES,
            true
        );
    }

    private function isActiveBlockingStatus($status): bool
    {
        if (!$this->isKnownFollowupStatus($status)) {
            return false;
        }

        return !in_array(
            (int) $status,
            self::ACTIVE_BLOCKING_TERMINAL_STATUSES,
            true
        );
    }

    private function digitsSql(string $column): string
    {
        if (config('database.default') === 'pgsql') {
            return "regexp_replace({$column}, '[^0-9]', '', 'g')";
        }

        return
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE({$column}, '+', '')," .
            " '-', '')," .
            " ' ', '')," .
            " '(', '')," .
            " ')', '')";
    }

    private function emptySummary(bool $commit, int $days): array
    {
        return [
            'dry_run' => !$commit,
            'days' => $days,
            'scanned' => 0,
            'would_delete_duplicates' => 0,
            'deleted_duplicates' => 0,
            'would_activate' => 0,
            'activated' => 0,
            'would_assign' => 0,
            'assigned' => 0,
            'would_queue' => 0,
            'queued' => 0,
            'skipped' => 0,
            'items' => [],
        ];
    }

    private function item(
        Lead $lead,
        string $action,
        string $reason,
        array $extra = []
    ): array {
        return array_merge([
            'lead_id' => $lead->id,
            'client_id' => $lead->client_id,
            'action' => $action,
            'reason' => $reason,
        ], $extra);
    }
}
