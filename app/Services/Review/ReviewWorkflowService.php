<?php

namespace App\Services\Review;

use App\Jobs\Review\SendInitialReviewTemplate;
use App\Models\AiAgent;
use App\Models\Lead;
use App\Models\ReviewConversation;
use App\Services\Operations\OperationCaseService;
use Illuminate\Support\Facades\DB;

class ReviewWorkflowService
{
    public function __construct(
        private OperationCaseService $operations
    ) {
    }

    public function startForCompletedRide(
        Lead $lead,
        ?string $aiAgentId = null
    ): ReviewConversation {
        return DB::transaction(function () use ($lead, $aiAgentId) {
            $lead->loadMissing('client');

            $existing = ReviewConversation::query()
                ->where('lead_id', $lead->id)
                ->whereNull('completed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $case = $this->operations->open(
                $lead,
                'review',
                ['source' => 'ride_completed']
            );

            $review = ReviewConversation::create([
                'lead_id' => $lead->id,
                'operation_case_id' => $case->id,
                'ai_agent_id' => $aiAgentId ?: optional($this->activeReviewAgent())->id,
                'customer_phone' => $this->customerPhone($lead),
                'status' => 'waiting_for_reply',
                'customer_replied' => false,
                'reminder_count' => 0,
            ]);

            SendInitialReviewTemplate::dispatch($review->id)->afterCommit();

            return $review;
        });
    }

    private function activeReviewAgent(): ?AiAgent
    {
        return AiAgent::query()
            ->where('agent_type', 'review')
            ->where('enabled', true)
            ->with('modelProfile')
            ->orderBy('name')
            ->first();
    }

    private function customerPhone(Lead $lead): string
    {
        $phone = optional($lead->client)->contact_number
            ?: optional($lead->client)->alternate_number;

        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            throw new \RuntimeException('Customer phone not found for review workflow.');
        }

        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }
}
