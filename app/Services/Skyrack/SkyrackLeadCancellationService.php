<?php

namespace App\Services\Skyrack;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\SkyrackLeadCancellationRequest;
use App\Models\UserType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SkyrackLeadCancellationService
{
    public function __construct(private ExistingCrmLeadCancellation $crm)
    {
    }

    /** @return array{0: array, 1: int} */
    public function execute(array $data, Authenticatable $actor): array
    {
        $actorKey = $this->crm->actorKey($actor);
        $reason = trim($data['reason']);
        $remark = trim((string) ($data['remark'] ?? ''));
        $remark = $remark === '' ? null : $remark;
        $leadId = (string) ($data['lead_id'] ?? '');

        if ($leadId === '') {
            $leadId = (string) $this->resolveLeadFromPayload($data, $actor)->getKey();
        }

        $requestId = (string) ($data['request_id'] ?? '');
        if ($requestId === '') {
            $requestId = $this->deterministicRequestId($data, $leadId, $reason);
            $data['request_id'] = $requestId;
        }

        $hash = hash('sha256', json_encode([
            'lead_id' => strtolower($leadId),
            'reason' => $reason,
            'remark' => $remark,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        // One transaction serializes both the idempotency row and the lead row.
        // PostgreSQL's unique constraint handles simultaneous identical requests.
        return DB::transaction(function () use ($actor, $actorKey, $reason, $remark, $hash, $leadId, $requestId) {
            DB::table('skyrack_lead_cancellation_requests')->insertOrIgnore([
                'integration' => 'skyrack',
                'request_id' => $requestId,
                'lead_id' => $leadId,
                'actor_key' => $actorKey,
                'payload_hash' => $hash,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entry = SkyrackLeadCancellationRequest::query()
                ->where('integration', 'skyrack')
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (!hash_equals((string) $entry->payload_hash, $hash)
                || (string) $entry->actor_key !== $actorKey) {
                throw new HttpException(409, 'Idempotency key was already used for another request.');
            }

            // Reauthorize even for duplicates so saved responses cannot bypass current access.
            $lead = Lead::query()->whereKey($entry->lead_id)->lockForUpdate()->first();
            if (!$lead) {
                throw new HttpException(404, 'Lead not found.');
            }
            $this->crm->assertMayCancel($actor, $lead);

            if ($entry->state === 'completed') {
                return [$entry->response_json, (int) $entry->http_code];
            }

            // Check paid FIRST, including repeated requests and already-cancelled leads.
            if ($this->crm->hasAnyPayment($lead)) {
                throw new HttpException(409, 'Paid leads require the existing Operations cancellation workflow.');
            }

            if ($this->crm->isAlreadyCancelled($lead)) {
                $body = [
                    'success' => true,
                    'message' => 'Lead is already cancelled. No changes made.',
                    'data' => ['lead_id' => (string) $lead->getKey(), 'status' => 'cancelled', 'already_cancelled' => true],
                ];
                $this->complete($entry, $body, 200);
                return [$body, 200];
            }

            $this->crm->cancel($lead, $actor, $reason, $remark);

            // IMPORTANT: CRM cancel method must persist history with source=skyrack and
            // actor/time. Do not write duplicate history here.
            $body = [
                'success' => true,
                'message' => 'Lead cancelled successfully.',
                'data' => [
                    'lead_id' => (string) $lead->getKey(),
                    'status' => 'cancelled',
                    'already_cancelled' => false,
                    'source' => 'skyrack',
                    'cancelled_by' => $actorKey,
                    'cancelled_at' => now('Asia/Kolkata')->toIso8601String(),
                ],
            ];
            $this->complete($entry, $body, 200);
            return [$body, 200];
        }, 3);
    }

    private function resolveLeadFromPayload(array $data, Authenticatable $actor): Lead
    {
        $phone = $this->normalizePhone((string) ($data['phone_number'] ?? ''));

        if ($phone === '') {
            throw new HttpException(422, 'phone_number is required when lead_id is not provided.');
        }

        $clientIds = Client::query()
            ->get(['id', 'contact_number', 'alternate_number'])
            ->filter(function (Client $client) use ($phone) {
                return $this->normalizePhone((string) $client->contact_number) === $phone
                    || $this->normalizePhone((string) $client->alternate_number) === $phone;
            })
            ->pluck('id')
            ->values();

        if ($clientIds->isEmpty()) {
            throw new HttpException(404, 'No CRM lead found for phone_number.');
        }

        $query = Lead::query()
            ->whereIn('client_id', $clientIds)
            ->whereHas('leadFollowups', function ($query) {
                $query->whereNotIn('status', [
                    LeadFollowup::STATUS_CANCELLED,
                    LeadFollowup::STATUS_CONFIRMED,
                    LeadFollowup::STATUS_REJECTED,
                ]);
            });

        $role = $actor->userType->user_type ?? null;
        if (!in_array($role, UserType::ADMIN_ROLES, true)) {
            $query->where('representative_user_id', $actor->getAuthIdentifier());
        }

        $leads = $query
            ->with(['leadFollowups' => function ($query) {
                $query->orderByDesc('created_at')->orderByDesc('id');
            }])
            ->latest('created_at')
            ->get()
            ->filter(function (Lead $lead) {
                $latest = $lead->leadFollowups->first();

                return !$latest || !in_array((int) $latest->status, [
                    LeadFollowup::STATUS_CANCELLED,
                    LeadFollowup::STATUS_CONFIRMED,
                    LeadFollowup::STATUS_REJECTED,
                ], true);
            })
            ->values();

        if ($leads->isEmpty()) {
            throw new HttpException(404, 'No active CRM lead found for phone_number.');
        }

        if ($leads->count() > 1) {
            throw new HttpException(409, 'Multiple active CRM leads matched phone_number.');
        }

        return $leads->first();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }

    private function deterministicRequestId(array $data, string $leadId, string $reason): string
    {
        $hash = hash('sha256', json_encode([
            'lead_id' => strtolower($leadId),
            'phone_number' => $this->normalizePhone((string) ($data['phone_number'] ?? '')),
            'agent_phone' => $this->normalizePhone((string) (($data['agent_phone'] ?? '') ?: ($data['agent_number'] ?? ''))),
            'call_start_at' => (string) ($data['call_start_at'] ?? ''),
            'call_end_at' => (string) ($data['call_end_at'] ?? ''),
            'followup_recording_id' => (string) ($data['followup_recording_id'] ?? ''),
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    private function complete(SkyrackLeadCancellationRequest $entry, array $body, int $httpCode): void
    {
        $entry->state = 'completed';
        $entry->http_code = $httpCode;
        $entry->response_json = $body;
        $entry->completed_at = now();
        $entry->save();
    }
}
