<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health';

    protected $description = 'Show queue and failed job health summary.';

    public function handle(): int
    {
        $jobsCount = Schema::hasTable('jobs')
            ? DB::table('jobs')->count()
            : 0;

        $failedJobsCount = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;

        $oldestJob = null;

        if (Schema::hasTable('jobs') && $jobsCount > 0) {
            $oldestJob = DB::table('jobs')
                ->orderBy('created_at')
                ->value('created_at');
        }

        $this->info('Queue health');
        $this->line('Jobs: ' . $jobsCount);
        $this->line('Failed jobs: ' . $failedJobsCount);
        $this->line('Oldest job created_at: ' . ($oldestJob ?: 'N/A'));

        $jobsWarning = (int) config('crm.queue_warning_jobs', 1000);
        $failedWarning = (int) config('crm.failed_jobs_warning', 50);

        if ($jobsCount >= $jobsWarning) {
            Log::warning('crm.queue.high_pending_jobs', [
                'jobs_count' => $jobsCount,
                'threshold' => $jobsWarning,
            ]);
        }

        if ($failedJobsCount >= $failedWarning) {
            Log::warning('crm.queue.high_failed_jobs', [
                'failed_jobs_count' => $failedJobsCount,
                'threshold' => $failedWarning,
            ]);
        }

        if (Schema::hasTable('jobs') && $jobsCount > 0) {
            $counts = [];

            DB::table('jobs')
                ->select('payload')
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->each(function ($job) use (&$counts) {
                    $payload = json_decode($job->payload, true);
                    $name = $payload['displayName'] ?? 'unknown';
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                });

            arsort($counts);

            $this->line('Top pending job classes:');

            foreach (array_slice($counts, 0, 10, true) as $name => $count) {
                $this->line($count . ' ' . $name);
            }
        }

        return self::SUCCESS;
    }
}
