<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SkyrackLeadSync;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SkyrackLeadSyncService
{
    private const INITIAL_BACKFILL_STATE_KEY = 'initial_backfill_queued_at';

    public function __construct(
        private SkyrackLeadPayloadBuilder $payloadBuilder,
        private CrmLeadCodeService $leadCodeService
    ) {
    }

    public function queueLead($lead, string $reason = 'updated'): ?SkyrackLeadSync
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $leadModel =
            $lead instanceof Lead
                ? $lead
                : Lead::query()->find($lead);

        if (!$leadModel || empty($leadModel->id)) {
            return null;
        }

        $this->leadCodeService->ensureCode($leadModel);

        return SkyrackLeadSync::query()->updateOrCreate(
            [
                'lead_id' => $leadModel->id,
            ],
            [
                'status' => 'pending',
                'reason' => $reason,
                'next_attempt_at' => now(),
                'last_error' => null,
            ]
        );
    }

    public function processPending(int $limit = 100): array
    {
        $result = [
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
            'backfill_queued' => 0,
        ];

        if (!$this->isEnabled() || !$this->hasCredentials()) {
            return $result;
        }

        $syncs =
            SkyrackLeadSync::query()
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'failed',
                    ]
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('created_at')
                ->limit($limit)
                ->get();

        foreach ($syncs as $sync) {
            $result['processed']++;

            try {
                $lead =
                    Lead::query()
                        ->with([
                            'client',
                            'representative',
                            'rideSegments',
                            'latestFollowup',
                        ])
                        ->find($sync->lead_id);

                if (!$lead) {
                    $this->markFailed(
                        $sync,
                        'Lead no longer exists in CRM.'
                    );

                    $result['skipped']++;

                    continue;
                }

                $payload =
                    $this->payloadBuilder->build($lead);

                $response =
                    Http::timeout(
                        (int) config('services.skyrack.timeout', 10)
                    )
                        ->acceptJson()
                        ->withToken(
                            (string) config('services.skyrack.leads_api_token')
                        )
                        ->post(
                            (string) config('services.skyrack.leads_api_url'),
                            $payload
                        );

                if ($response->successful()) {
                    $sync->fill([
                        'status' => 'synced',
                        'attempt_count' => ((int) $sync->attempt_count) + 1,
                        'last_payload_hash' => $this->payloadHash($payload),
                        'last_error' => null,
                        'last_payload' => $payload,
                        'last_response' => $this->responsePayload($response),
                        'synced_at' => now(),
                        'next_attempt_at' => null,
                    ]);

                    $sync->save();

                    $result['synced']++;
                    $result['backfill_queued'] +=
                        $this->queueInitialBackfillIfNeeded($lead->id);

                    continue;
                }

                $this->markFailed(
                    $sync,
                    'Skyrack API returned HTTP '
                        . $response->status()
                        . ': '
                        . Str::limit($response->body(), 1000),
                    $payload,
                    $response
                );

                $result['failed']++;
            } catch (\Throwable $e) {
                $this->markFailed(
                    $sync,
                    $e->getMessage()
                );

                Log::warning(
                    'Skyrack lead sync failed.',
                    [
                        'sync_id' => $sync->id,
                        'lead_id' => $sync->lead_id,
                        'error' => $e->getMessage(),
                    ]
                );

                $result['failed']++;
            }
        }

        return $result;
    }

    public function queueInitialBackfillIfNeeded(?string $excludeLeadId = null): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        return DB::transaction(function () use ($excludeLeadId) {
            $existing =
                DB::table('skyrack_lead_sync_states')
                    ->where('key', self::INITIAL_BACKFILL_STATE_KEY)
                    ->lockForUpdate()
                    ->first();

            if ($existing) {
                return 0;
            }

            DB::table('skyrack_lead_sync_states')->insert([
                'key' => self::INITIAL_BACKFILL_STATE_KEY,
                'value' => now()->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $query =
                Lead::query()
                    ->orderByDesc('created_at')
                    ->limit(
                        (int) config('services.skyrack.backfill_limit', 1000)
                    );

            if (!empty($excludeLeadId)) {
                $query->where('id', '!=', $excludeLeadId);
            }

            $count = 0;

            foreach ($query->get() as $lead) {
                $this->queueLead(
                    $lead,
                    'initial_backfill'
                );

                $count++;
            }

            return $count;
        });
    }

    private function markFailed(
        SkyrackLeadSync $sync,
        string $message,
        ?array $payload = null,
        ?Response $response = null
    ): void {
        $attemptCount =
            ((int) $sync->attempt_count) + 1;

        $updates = [
            'status' => 'failed',
            'attempt_count' => $attemptCount,
            'last_error' => Str::limit($message, 5000),
            'next_attempt_at' => now()->addMinutes(
                min(
                    60,
                    max(1, $attemptCount)
                )
            ),
        ];

        if ($payload !== null) {
            $updates['last_payload'] = $payload;
            $updates['last_payload_hash'] = $this->payloadHash($payload);
        }

        if ($response !== null) {
            $updates['last_response'] = $this->responsePayload($response);
        }

        $sync->fill($updates);
        $sync->save();
    }

    private function responsePayload(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'status' => $response->status(),
            'body' => Str::limit($response->body(), 2000),
        ];
    }

    private function payloadHash(array $payload): string
    {
        return hash(
            'sha256',
            json_encode($payload)
        );
    }

    private function isEnabled(): bool
    {
        return filter_var(
            config('services.skyrack.enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function hasCredentials(): bool
    {
        return !empty(config('services.skyrack.leads_api_url'))
            && !empty(config('services.skyrack.leads_api_token'));
    }
}
