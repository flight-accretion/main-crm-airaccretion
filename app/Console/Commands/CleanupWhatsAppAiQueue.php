<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppAiReply;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CleanupWhatsAppAiQueue extends Command
{
    protected $signature = 'whatsapp:cleanup-ai-queue {--limit=500 : Maximum queued jobs to inspect per run} {--dry-run : Report without changing anything}';

    protected $description = 'Skip abandoned processing WhatsApp AI batches and remove no-op queued AI jobs. Never sends messages.';

    // A queued job only does work for these batches; for every other status it is a no-op.
    private const ACTIVE = ['pending', 'processing'];

    public function handle(): int
    {
        if (!Schema::hasTable('jobs') || !Schema::hasTable('whatsapp_ai_reply_batches')) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $staleMinutes = max(15, (int) config('whatcrm.ai_processing_stale_minutes', 30));
        $cutoff = now()->subMinutes($staleMinutes);

        /*
         * A batch stays "processing" only when the process died mid-run
         * (timeout, restart, kill). Mark it skipped, never pending, so an
         * old customer message cannot trigger a late AI reply.
         */
        $stale = DB::table('whatsapp_ai_reply_batches')
            ->where('status', 'processing')
            ->where('locked_at', '<', $cutoff)
            ->orderBy('locked_at')
            ->limit(100)
            ->pluck('id');

        $skipped = 0;

        if (!$dryRun) {
            foreach ($stale as $id) {
                $skipped += DB::table('whatsapp_ai_reply_batches')
                    ->where('id', $id)
                    ->where('status', 'processing')
                    ->where('locked_at', '<', $cutoff)
                    ->update([
                        'status' => 'skipped',
                        'processed_at' => now(),
                        'updated_at' => now(),
                        'error' => 'Abandoned processing lock older than '.$staleMinutes.' minutes; no message sent.',
                    ]);
            }
        }

        $removable = [];
        $retained = 0;

        if (config('queue.connections.'.config('whatcrm.ai_queue_connection', 'database').'.driver') === 'database') {
            $jobs = DB::table('jobs')
                ->where('queue', config('whatcrm.ai_queue', 'whatsapp-ai'))
                ->whereNull('reserved_at')
                ->orderBy('id')
                ->limit(max(1, min(5000, (int) $this->option('limit'))))
                ->get(['id', 'payload']);

            $batchIds = [];

            foreach ($jobs as $job) {
                $batchIds[$job->id] = $this->batchId($job->payload);
            }

            $statuses = DB::table('whatsapp_ai_reply_batches')
                ->whereIn('id', array_values(array_filter(array_unique($batchIds))))
                ->pluck('status', 'id');

            foreach ($batchIds as $jobId => $batchId) {
                // Unknown payloads and jobs for live batches are kept.
                if (!$batchId || in_array($statuses[$batchId] ?? null, self::ACTIVE, true)) {
                    $retained++;
                    continue;
                }

                $removable[] = $jobId;
            }

            if (!$dryRun) {
                foreach (array_chunk($removable, 200) as $ids) {
                    DB::table('jobs')->whereIn('id', $ids)->whereNull('reserved_at')->delete();
                }
            }
        }

        $summary = [
            'dry_run' => $dryRun,
            'stale_processing_batches' => $stale->count(),
            'batches_skipped' => $skipped,
            'noop_jobs_removed' => $dryRun ? 0 : count($removable),
            'noop_jobs_found' => count($removable),
            'jobs_retained' => $retained,
        ];

        if (!$dryRun && ($skipped || $removable)) {
            Log::info('whatsapp.ai_queue_cleanup', $summary + ['skipped_batch_ids' => $stale->all()]);
        }

        $this->info(json_encode($summary));

        return self::SUCCESS;
    }

    private function batchId(string $payload): ?string
    {
        $data = json_decode($payload, true);
        $class = ProcessWhatsAppAiReply::class;

        if (!is_array($data) || ($data['data']['commandName'] ?? null) !== $class) {
            return null;
        }

        $serialized = $data['data']['command'] ?? null;

        // Do not unserialize queue payloads; read only the explicit public batchId.
        if (!is_string($serialized)
            || preg_match_all('/s:7:"batchId";s:36:"([0-9a-fA-F-]{36})";/', $serialized, $matches) !== 1) {
            return null;
        }

        return Str::isUuid($matches[1][0]) ? strtolower($matches[1][0]) : null;
    }
}
