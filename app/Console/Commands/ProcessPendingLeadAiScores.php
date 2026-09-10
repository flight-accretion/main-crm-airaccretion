<?php

namespace App\Console\Commands;

use App\Models\LeadAiScore;
use App\Services\LeadAiScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingLeadAiScores extends Command
{
    protected $signature =
        'lead-ai:process-pending
        {--limit=5 : Maximum pending AI score rows to process}';

    protected $description =
        'Process pending lead AI scores when queue workers have not picked them up.';

    public function handle(
        LeadAiScoringService $service
    ): int {
        $limit =
            max(
                1,
                min(
                    50,
                    (int) $this->option('limit')
                )
            );

        $staleProcessingCutoff =
            now()->subMinutes(5);

        $scores =
            LeadAiScore::query()
                ->where(function ($query) use (
                    $staleProcessingCutoff
                ) {
                    $query->where(
                        'status',
                        'pending'
                    )
                    ->orWhere(function ($query) use (
                        $staleProcessingCutoff
                    ) {
                        $query
                            ->where(
                                'status',
                                'processing'
                            )
                            ->where(
                                'updated_at',
                                '<=',
                                $staleProcessingCutoff
                            );
                    });
                })
                ->orderBy('created_at')
                ->limit($limit)
                ->get();

        $processed = 0;
        $completed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($scores as $score) {
            if (
                $this->hasOlderBlockingScore(
                    $score,
                    $staleProcessingCutoff
                )
            ) {
                $skipped++;
                continue;
            }

            try {
                $service->process(
                    $score->id
                );

                $processed++;

                $score->refresh();

                if ($score->status === 'completed') {
                    $completed++;
                } elseif ($score->status === 'failed') {
                    $failed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $processed++;
                $failed++;

                Log::error(
                    'Pending lead AI score processing failed.',
                    [
                        'score_id' =>
                            $score->id,

                        'lead_id' =>
                            $score->lead_id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }
        }

        $this->info(
            'Processed: '
            . $processed
            . '; completed: '
            . $completed
            . '; failed: '
            . $failed
            . '; skipped: '
            . $skipped
        );

        return Command::SUCCESS;
    }

    private function hasOlderBlockingScore(
        LeadAiScore $score,
        $staleProcessingCutoff
    ): bool {
        return LeadAiScore::query()
            ->where(
                'lead_id',
                $score->lead_id
            )
            ->where(
                'created_at',
                '<',
                $score->created_at
            )
            ->where(function ($query) use (
                $staleProcessingCutoff
            ) {
                $query->where(
                    'status',
                    'pending'
                )
                ->orWhere(function ($query) use (
                    $staleProcessingCutoff
                ) {
                    $query
                        ->where(
                            'status',
                            'processing'
                        )
                        ->where(
                            'updated_at',
                            '>',
                            $staleProcessingCutoff
                        );
                });
            })
            ->exists();
    }
}
