<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\SkyrackLeadSyncService;
use App\Services\WhatCrmAssignmentCustomerMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        $this->queue($lead, 'created');

        if (!empty($lead->representative_user_id)) {
            $this->notifyCustomerAfterAssignment($lead);
        }
    }

    public function updated(Lead $lead): void
    {
        $this->queue($lead, 'updated');

        if (
            $lead->wasChanged('representative_user_id')
            && !empty($lead->representative_user_id)
        ) {
            $this->notifyCustomerAfterAssignment($lead);
        }
    }

    private function queue(Lead $lead, string $reason): void
    {
        try {
            app(SkyrackLeadSyncService::class)->queueLead(
                $lead,
                $reason
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to queue Skyrack lead sync.',
                [
                    'lead_id' => $lead->id,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    private function notifyCustomerAfterAssignment(Lead $lead): void
    {
        if (
            !config(
                'whatcrm.assignment_customer_message_enabled',
                true
            )
        ) {
            return;
        }

        $leadId = (string) $lead->id;

        $notify = function () use ($leadId): void {
            try {
                app(WhatCrmAssignmentCustomerMessageService::class)
                    ->sendForLeadId($leadId);
            } catch (\Throwable $e) {
                Log::warning(
                    'Unable to send lead assignment customer WhatsApp template.',
                    [
                        'lead_id' => $leadId,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        };

        try {
            if (DB::connection()->transactionLevel() > 0) {
                DB::afterCommit($notify);

                return;
            }
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to defer lead assignment customer WhatsApp template.',
                [
                    'lead_id' => $leadId,
                    'error' => $e->getMessage(),
                ]
            );
        }

        $notify();
    }
}
