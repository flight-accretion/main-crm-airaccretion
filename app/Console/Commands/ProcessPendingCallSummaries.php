<?php

namespace App\Console\Commands;

use App\Models\CallSummaryIntegration;
use App\Services\CallSummaryIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingCallSummaries extends Command
{
    protected $signature =
        'call-summary:process-pending';


    protected $description =
        'Retry pending call summaries and attach them to CRM leads when a safe match becomes available.';


    public function handle(
        CallSummaryIntegrationService $service
    ) {

        $maxAttempts =
            (int)
            config(
                'call_summary.max_attempts',
                360
            );


        $records =
            CallSummaryIntegration::query()
                ->whereIn(
                    'status',
                    [
                        'received',
                        'pending_lead',
                        'failed',
                    ]
                )
                ->where(
                    'attempt_count',
                    '<',
                    $maxAttempts
                )
                ->orderBy(
                    'created_at'
                )
                ->limit(
                    100
                )
                ->get();


        $processed = 0;

        $completed = 0;


        foreach (
            $records
            as
            $record
        ) {

            try {

                $result =
                    $service->process(
                        $record
                    );


                $processed++;


                if (
                    $result->status
                    ===
                    'followup_created'
                ) {

                    $completed++;
                }


            } catch (
                \Throwable $e
            ) {

                Log::error(
                    'Pending Call Summary retry failed.',
                    [
                        'integration_id' =>
                            $record->id,

                        'error' =>
                            $e
                                ->getMessage(),
                    ]
                );
            }
        }


        $this->info(
            "Processed: {$processed}; followups created: {$completed}"
        );


        return 0;
    }
}