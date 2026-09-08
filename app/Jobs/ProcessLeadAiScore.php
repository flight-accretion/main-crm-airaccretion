<?php

namespace App\Jobs;

use App\Models\LeadAiScore;
use App\Services\LeadAiScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessLeadAiScore implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;

    public $timeout = 45;

    public function __construct(
        public string $scoreId
    ) {
    }

    public function handle(
        LeadAiScoringService $service
    ): void {
        $score =
            LeadAiScore::query()
                ->find(
                    $this->scoreId
                );

        if (!$score) {
            return;
        }

        if (
            $score->status
            === 'completed'
        ) {
            return;
        }

        /*
         * ------------------------------------------------
         * PRESERVE INCREMENTAL ORDER
         * ------------------------------------------------
         *
         * If an older AI state for the same lead
         * is still waiting/processing, this job waits.
         *
         * This prevents:
         *
         * Follow-up #3 scoring before Follow-up #2.
         */
        $olderPending =
            LeadAiScore::query()
                ->where(
                    'lead_id',
                    $score->lead_id
                )
                ->where(
                    'created_at',
                    '<',
                    $score->created_at
                )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'processing',
                    ]
                )
                ->exists();

        if ($olderPending) {
            $this->release(5);

            return;
        }

        $service->process(
            $this->scoreId
        );
    }

    public function backoff(): array
    {
        return [
            15,
            60,
            180,
        ];
    }
}
