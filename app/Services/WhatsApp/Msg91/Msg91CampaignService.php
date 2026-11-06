<?php

namespace App\Services\WhatsApp\Msg91;

use App\Jobs\DispatchMsg91CampaignRecipient;
use App\Models\Msg91WhatsAppCampaign;
use App\Models\Msg91WhatsAppCampaignEvent;
use App\Models\Msg91WhatsAppCampaignRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Msg91CampaignService
{
    public function __construct(
        private Msg91CampaignAccess $access,
        private Msg91CampaignRecipientResolver $recipients,
        private Msg91CampaignTemplateResolver $templates
    ) {
    }

    /** @param array<int,string> $leadIds */
    public function preview(User $user, array $leadIds): array
    {
        $sender = $this->access->assertCanSend($user);
        $resolved = $this->recipients->resolve($user, $leadIds);
        return array_merge($resolved, [
            'sender' => $sender,
            'templates' => $this->templates->approvedFor($user, $sender)->get(['id', 'name', 'language', 'category', 'components', 'variable_schema']),
        ]);
    }

    /** @param array<int,string> $leadIds */
    public function create(User $user, string $name, string $templateId, array $leadIds): Msg91WhatsAppCampaign
    {
        $sender = $this->access->assertCanSend($user);
        $template = $this->templates->assertApproved($user, $sender, $templateId);
        $resolved = $this->recipients->resolve($user, $leadIds);
        abort_if($resolved['recipients']->isEmpty(), 422, 'No eligible WhatsApp recipients were found.');

        return DB::transaction(function () use ($user, $sender, $template, $resolved, $name, $leadIds) {
            $campaign = Msg91WhatsAppCampaign::create([
                'created_by' => $user->id,
                'sender_id' => $sender->id,
                'template_id' => $template->id,
                'name' => $name,
                'status' => 'queued',
                'selection_snapshot' => [
                    'lead_ids' => array_values(array_unique($leadIds)),
                    'created_at' => now()->toIso8601String(),
                    'filter_note' => 'Explicit lead IDs were authorized and frozen at confirmation.',
                ],
                'selected_count' => $resolved['selected_count'],
                'eligible_count' => $resolved['recipients']->count(),
                'duplicate_count' => $resolved['excluded']['duplicates'],
                'invalid_count' => $resolved['excluded']['invalid'] + $resolved['excluded']['unauthorized'],
                'suppressed_count' => $resolved['excluded']['suppressed'],
            ]);

            foreach ($resolved['recipients'] as $recipient) {
                $row = Msg91WhatsAppCampaignRecipient::create([
                    'campaign_id' => $campaign->id,
                    'lead_id' => $recipient['lead']->id,
                    'client_id' => $recipient['client']->id,
                    'normalized_phone' => $recipient['phone'],
                    'rendered_variables' => $recipient['variables'],
                    'status' => 'queued',
                ]);
                DispatchMsg91CampaignRecipient::dispatch($row->id)->afterCommit();
            }

            Msg91WhatsAppCampaignEvent::create([
                'campaign_id' => $campaign->id,
                'event_type' => 'created',
                'status' => 'queued',
                'payload' => ['eligible_count' => $campaign->eligible_count],
                'occurred_at' => now(),
            ]);

            return $campaign;
        });
    }

  
public function cancel(User $user, Msg91WhatsAppCampaign $campaign): void
{
    abort_unless(
        $this->access->canViewCampaign($user, $campaign)
        && (
            (string) $campaign->created_by === (string) $user->id
            || $this->access->canViewCompany($user)
        ),
        403
    );

    DB::transaction(function () use ($user, $campaign) {
        $campaign = Msg91WhatsAppCampaign::query()
            ->lockForUpdate()
            ->findOrFail($campaign->id);

        if ($campaign->cancelled_at) {
            return;
        }

        abort_if(
            !in_array($campaign->status, ['queued', 'processing'], true),
            422,
            'This campaign cannot be cancelled.'
        );

        $campaign->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ])->save();

        $campaign->recipients()
            ->whereIn('status', ['queued', 'retry_scheduled'])
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        Msg91WhatsAppCampaignEvent::create([
            'campaign_id' => $campaign->id,
            'event_type' => 'cancelled',
            'status' => 'cancelled',
            'occurred_at' => now(),
        ]);
    });
}


public function retryFailed(
    User $user,
    Msg91WhatsAppCampaign $campaign
): int {
    abort_unless(
        $this->access->canViewCampaign($user, $campaign)
        && (
            (string) $campaign->created_by === (string) $user->id
            || $this->access->canViewCompany($user)
        ),
        403
    );

    $this->access->assertCanSend($campaign->sender->user);

    return DB::transaction(function () use ($campaign) {
        $lockedCampaign = Msg91WhatsAppCampaign::query()
            ->lockForUpdate()
            ->findOrFail($campaign->id);

        abort_if(
            $lockedCampaign->cancelled_at !== null,
            422,
            'Cancelled campaigns cannot be retried.'
        );

        // Only failures known to occur before provider submission.
        $recipients = $lockedCampaign->recipients()
            ->where('status', 'failed')
            ->whereIn('failure_code', [
                'sender_unavailable',
                'dispatch_authorization_failed',
            ])
            ->where('attempts', 0)
            ->lockForUpdate()
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->forceFill([
                'status' => 'queued',
                'failure_code' => null,
                'failure_reason' => null,
                'next_retry_at' => null,
            ])->save();

            DispatchMsg91CampaignRecipient::dispatch($recipient->id)
                ->afterCommit();
        }

        if ($recipients->isNotEmpty()) {
            Msg91WhatsAppCampaignEvent::create([
                'campaign_id' => $lockedCampaign->id,
                'event_type' => 'manual_retry',
                'status' => 'queued',
                'payload' => [
                    'recipient_count' => $recipients->count(),
                ],
                'occurred_at' => now(),
            ]);

            $this->refreshCounters($lockedCampaign);
        }

        return $recipients->count();
    });
}

 
    public function refreshCounters(Msg91WhatsAppCampaign $campaign): void
    {
        $counts = $campaign->recipients()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $queued = (int) ($counts['queued'] ?? 0);
        $retryScheduled = (int) ($counts['retry_scheduled'] ?? 0);

        $campaign->forceFill([
            'accepted_count' => (int) ($counts['accepted'] ?? 0),
            'sent_count' => (int) ($counts['sent'] ?? 0),
            'delivered_count' => (int) ($counts['delivered'] ?? 0),
            'read_count' => (int) ($counts['read'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
            'unknown_count' => (int) ($counts['unknown'] ?? 0),
            'status' => $campaign->cancelled_at
                ? 'cancelled'
                : (
                    $queued > 0 || $retryScheduled > 0
                        ? 'processing'
                        : 'completed'
                ),
        ])->save();
    }

}
