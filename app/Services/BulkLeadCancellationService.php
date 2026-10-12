<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SkyrackLeadCancellationRequest;
use App\Models\User;
use App\Services\Skyrack\ExistingCrmLeadCancellation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class BulkLeadCancellationService
{
    private const INTEGRATION = 'crm_bulk_cancel';

    public function __construct(
        private ExistingCrmLeadCancellation $cancellation
    ) {
    }

    public function run(array $leadIds, string $reason, string $operationId, User $actor): array
    {
        $result = [
            'cancelled' => [],
            'skipped' => [],
            'failed' => [],
        ];

        foreach (array_values(array_unique($leadIds)) as $leadId) {
            try {
                $entryResult = $this->cancelOne($leadId, $reason, $operationId, $actor);
                $bucket = $entryResult['bucket'];

                $result[$bucket][] = $entryResult['detail'];
            } catch (Throwable $e) {
                Log::error('Bulk lead cancellation failed', [
                    'lead_id' => $leadId,
                    'operation_id' => $operationId,
                    'actor_id' => $actor->id,
                    'error' => $e->getMessage(),
                ]);

                $result['failed'][] = [
                    'lead_id' => $leadId,
                    'reason' => 'Unexpected error; review application logs',
                ];
            }
        }

        return $result;
    }

    private function cancelOne(string $leadId, string $reason, string $operationId, User $actor): array
    {
        $requestId = $this->requestId($operationId, $leadId);
        $payloadHash = hash('sha256', json_encode([
            'lead_id' => strtolower($leadId),
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($leadId, $reason, $actor, $requestId, $payloadHash) {
            DB::table('skyrack_lead_cancellation_requests')->insertOrIgnore([
                'integration' => self::INTEGRATION,
                'request_id' => $requestId,
                'lead_id' => $leadId,
                'actor_key' => (string) $actor->id,
                'payload_hash' => $payloadHash,
                'state' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $entry = SkyrackLeadCancellationRequest::query()
                ->where('integration', self::INTEGRATION)
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !hash_equals((string) $entry->payload_hash, $payloadHash)
                || (string) $entry->actor_key !== (string) $actor->id
            ) {
                throw new HttpException(409, 'Bulk cancellation operation id was already used for different data.');
            }

            if ($entry->state === 'completed' && is_array($entry->response_json)) {
                return $entry->response_json;
            }

            $lead = Lead::query()
                ->whereKey($leadId)
                ->lockForUpdate()
                ->first();

            if (!$lead) {
                return $this->complete($entry, 'skipped', [
                    'lead_id' => $leadId,
                    'reason' => 'Lead not found',
                ]);
            }

            $this->cancellation->assertMayCancel($actor, $lead);

            if ($this->cancellation->hasAnyPayment($lead)) {
                return $this->complete($entry, 'skipped', [
                    'lead_id' => $leadId,
                    'reason' => 'Paid leads require the existing Operations cancellation workflow.',
                ]);
            }

            if ($this->cancellation->isAlreadyCancelled($lead)) {
                return $this->complete($entry, 'skipped', [
                    'lead_id' => $leadId,
                    'reason' => 'Lead is already cancelled.',
                ]);
            }

            $this->cancellation->cancel(
                $lead,
                $actor,
                $reason,
                null,
                'CRM Bulk Cancel',
                'crm_bulk_cancel'
            );

            return $this->complete($entry, 'cancelled', [
                'lead_id' => $leadId,
                'reason' => 'Lead cancelled successfully.',
            ]);
        }, 3);
    }

    private function complete(SkyrackLeadCancellationRequest $entry, string $bucket, array $detail): array
    {
        $response = [
            'bucket' => $bucket,
            'detail' => $detail,
        ];

        $entry->state = 'completed';
        $entry->http_code = 200;
        $entry->response_json = $response;
        $entry->completed_at = now();
        $entry->save();

        return $response;
    }

    private function requestId(string $operationId, string $leadId): string
    {
        $hash = hash('sha256', 'crm-bulk-cancel:' . $operationId . ':' . strtolower($leadId));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
