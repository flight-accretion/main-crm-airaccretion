<?php

namespace App\Console\Commands;

use App\Models\IvrCallType;
use App\Models\IvrSyncLog;
use App\Services\IvrImportService;
use App\Services\ViCpaasService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchViIvrLeads extends Command
{
    protected $signature = 'ivr:fetch-vi-leads {--from=} {--to=}';
    protected $description = 'Fetch VI CPaaS call reports and import them into CRM leads';

    public function handle(ViCpaasService $viService, IvrImportService $importService): int
    {
        $from = $this->option('from') ?: now()->subDay()->format('d/m/Y');
        $to = $this->option('to') ?: now()->format('d/m/Y');

        try {
            $fromDate = Carbon::createFromFormat('d/m/Y', $from)->startOfDay();
            $toDate = Carbon::createFromFormat('d/m/Y', $to)->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Dates must be in DD/MM/YYYY format.');
            return Command::FAILURE;
        }

        $syncLog = IvrSyncLog::create([
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $totals = ['fetched' => 0, 'created' => 0, 'duplicate_calls' => 0, 'repeat_leads' => 0, 'errors' => 0];

        try {
            $callTypes = IvrCallType::where('is_active', true)->orderBy('sort_order')->get();
            if ($callTypes->isEmpty()) {
                throw new \RuntimeException('No active IVR Call Type is configured. Add at least one call type from IVR Management.');
            }

            foreach ($callTypes as $callType) {
                $records = $viService->pullReport($callType, $fromDate->format('d/m/Y'), $toDate->format('d/m/Y'));
                $result = $importService->import($callType, $records);
                foreach ($totals as $key => $value) {
                    $totals[$key] += (int) ($result[$key] ?? 0);
                }
            }

            $syncLog->update([
                'status' => 'success',
                'records_fetched' => $totals['fetched'],
                'records_created' => $totals['created'],
                'duplicate_calls' => $totals['duplicate_calls'],
                'repeat_leads' => $totals['repeat_leads'],
                'errors' => $totals['errors'],
                'message' => 'VI IVR sync completed.',
                'finished_at' => now(),
            ]);

            $this->info(json_encode($totals));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $syncLog->update([
                'status' => 'failed',
                'records_fetched' => $totals['fetched'],
                'records_created' => $totals['created'],
                'duplicate_calls' => $totals['duplicate_calls'],
                'repeat_leads' => $totals['repeat_leads'],
                'errors' => $totals['errors'] + 1,
                'message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
            Log::error('VI IVR sync failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
