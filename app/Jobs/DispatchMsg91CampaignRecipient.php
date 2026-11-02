<?php

namespace App\Jobs;

use App\Models\Msg91WhatsAppCampaignEvent;
use App\Models\Msg91WhatsAppCampaignRecipient;
use App\Models\Msg91WhatsAppSuppression;
use App\Services\WhatsApp\Msg91\Msg91AmbiguousSubmissionException;
use App\Services\WhatsApp\Msg91\Msg91CampaignAccess;
use App\Services\WhatsApp\Msg91\Msg91CampaignService;
use App\Services\WhatsApp\Msg91\Msg91CampaignTemplateResolver;
use App\Services\WhatsApp\Msg91\Msg91Client;
use App\Services\WhatsApp\Msg91\Msg91ProviderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

class DispatchMsg91CampaignRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1;
    public $timeout = 60;

    public function __construct(public string $recipientId) {}

    public function handle(
        Msg91Client $client,
        Msg91CampaignService $campaigns,
        Msg91CampaignAccess $access,
        Msg91CampaignTemplateResolver $templates
    ): void
    {
        $recipient = Msg91WhatsAppCampaignRecipient::query()->with(['campaign.sender.user.userType', 'campaign.template'])->find($this->recipientId);
        if (!$recipient || !in_array($recipient->status, ['queued', 'retry_scheduled'], true)) return;

        $campaign = $recipient->campaign;
        if (!$campaign || $campaign->cancelled_at || $campaign->status === 'cancelled') return;
        $sender = $campaign->sender;
        if (!$sender || !$sender->isVerifiedActive() || !$sender->user) { $this->terminal($recipient, 'sender_unavailable', 'Sender is no longer active.'); return; }
        if (Msg91WhatsAppSuppression::query()->where('normalized_phone', $recipient->normalized_phone)->exists()) { $this->terminal($recipient, 'suppressed', 'Recipient opted out before dispatch.'); return; }
        if (!RateLimiter::attempt('msg91-campaign-account', (int) config('services.msg91_campaigns.account_per_minute', 50), fn () => true, 60)) { $this->reschedule($recipient, 'rate_limited', 'Shared MSG91 rate limit reached.'); return; }
        if (!RateLimiter::attempt('msg91-campaign-sender:' . $sender->id, (int) config('services.msg91_campaigns.sender_per_minute', 15), fn () => true, 60)) { $this->reschedule($recipient, 'rate_limited', 'Sender rate limit reached.'); return; }

        $lock = Cache::lock('msg91-campaign-recipient:' . $recipient->id, 120);
        if (!$lock->get()) return;
        try {
            $recipient->refresh();
            if (!in_array($recipient->status, ['queued', 'retry_scheduled'], true)) return;
            try {
                $access->assertCanSend($sender->user);
                $template = $templates->assertApproved($sender->user, $sender, $campaign->template_id);
                $components = $this->components($recipient, $template->variable_schema);
            } catch (Throwable $exception) {
                $this->terminal($recipient, 'dispatch_authorization_failed', 'Sender subscription, template approval, or template mapping is no longer valid.');
                return;
            }

            $response = $client->sendTemplate(
                $sender->integrated_number,
                ['name' => $template->name, 'language' => $template->language],
                $recipient->normalized_phone,
                $components
            );
            $recipient->forceFill(['status' => 'accepted', 'attempts' => $recipient->attempts + 1, 'provider_request_id' => $response['request_id'], 'accepted_at' => now(), 'failure_code' => null, 'failure_reason' => null])->save();
            $this->event($recipient, 'accepted', 'accepted', ['request_id' => $response['request_id']]);
        } catch (Msg91AmbiguousSubmissionException $e) {
            $recipient->forceFill(['status' => 'unknown', 'attempts' => $recipient->attempts + 1, 'failure_code' => 'ambiguous_submission', 'failure_reason' => 'Submission outcome is unknown and will not be resent automatically.'])->save();
            $this->event($recipient, 'unknown', 'unknown');
        } catch (Msg91ProviderException $e) {
            $e->isRetryable() ? $this->reschedule($recipient, 'provider_retryable', $e->getMessage()) : $this->terminal($recipient, 'provider_rejected', $e->getMessage());
        } finally {
            optional($lock)->release();
            $campaign->refresh();
            $campaigns->refreshCounters($campaign);
        }
    }

    private function components(Msg91WhatsAppCampaignRecipient $recipient, ?array $schema): array
    {
        // Static MSG91 templates have no variables and therefore require no
        // component payload. They are valid to send as-is.
        if (empty($schema)) {
            return [];
        }

        $components = [];
        foreach ((array) $schema as $componentKey => $mapping) {
            if (!is_array($mapping)) {
                continue;
            }

            // `source` was used by the first CRM template form. Preserve
            // those saved mappings while new records use the explicit field.
            $component = $mapping['component'] ?? $componentKey;
            $field = $mapping['field'] ?? $mapping['source'] ?? null;
            $type = $mapping['type'] ?? 'text';
            if (
                !is_string($component)
                || !preg_match('/^(body|header|button)_[0-9]+$/', $component)
                || !is_string($field)
                || !array_key_exists($field, (array) $recipient->rendered_variables)
                || !in_array($type, ['text'], true)
            ) {
                throw new RuntimeException('Template mapping is incomplete or unsupported.');
            }

            $components[$component] = [
                'type' => $type,
                'value' => (string) $recipient->rendered_variables[$field],
            ];
        }

        return $components;
    }
    private function reschedule(Msg91WhatsAppCampaignRecipient $recipient, string $code, string $reason): void
    {
        $attempts = $recipient->attempts + 1;
        if ($attempts > (int) config('services.msg91_campaigns.max_attempts', 3)) { $this->terminal($recipient, $code, $reason); return; }
        $delay = min(900, (2 ** $attempts) * 15 + random_int(0, 10));
        $recipient->forceFill(['status' => 'retry_scheduled', 'attempts' => $attempts, 'failure_code' => $code, 'failure_reason' => $reason, 'next_retry_at' => now()->addSeconds($delay)])->save();
        $this->event($recipient, 'retry_scheduled', 'retry_scheduled');
        static::dispatch($recipient->id)->delay(now()->addSeconds($delay));
    }
    private function terminal(Msg91WhatsAppCampaignRecipient $recipient, string $code, string $reason): void { $recipient->forceFill(['status' => 'failed', 'failure_code' => $code, 'failure_reason' => $reason])->save(); $this->event($recipient, 'failed', 'failed'); }
    private function event(Msg91WhatsAppCampaignRecipient $recipient, string $type, string $status, array $payload = []): void { Msg91WhatsAppCampaignEvent::create(['campaign_recipient_id' => $recipient->id, 'campaign_id' => $recipient->campaign_id, 'event_type' => $type, 'status' => $status, 'payload' => $payload, 'occurred_at' => now()]); }
}
