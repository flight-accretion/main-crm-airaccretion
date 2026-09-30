<?php

namespace App\Services\Skyrack;

use App\Models\Lead;
use App\Models\SkyrackLeadCancellationRequest;
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

        $hash = hash('sha256', json_encode([
            'lead_id' => strtolower($data['lead_id']),
            'reason' => $reason,
            'remark' => $remark,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        // One transaction serializes both the idempotency row and the lead row.
        // PostgreSQL's unique constraint handles simultaneous identical requests.
        return DB::transaction(function () use ($data, $actor, $actorKey, $reason, $remark, $hash) {
            DB::table('skyrack_lead_cancellation_requests')->insertOrIgnore([
                'integration' => 'skyrack',
                'request_id' => $data['request_id'],
                'lead_id' => $data['lead_id'],
                'actor_key' => $actorKey,
                'payload_hash' => $hash,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entry = SkyrackLeadCancellationRequest::query()
                ->where('integration', 'skyrack')
                ->where('request_id', $data['request_id'])
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

    private function complete(SkyrackLeadCancellationRequest $entry, array $body, int $httpCode): void
    {
        $entry->state = 'completed';
        $entry->http_code = $httpCode;
        $entry->response_json = $body;
        $entry->completed_at = now();
        $entry->save();
    }
}
