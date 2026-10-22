<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatsAppAiReply;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RepairWhatsAppAiBacklog extends Command
{
    protected $signature = 'crm:repair-whatsapp-ai-backlog {--apply : Apply the approved repair after an encrypted backup}';

    protected $description = 'Dry-run the October 2026 WhatsApp backlog repair; never dispatch or retry messages.';

    // Incident scope approved by the operator; other processing batches are never reset.
    private const LOCKS = ['2026-09-26 14:01:50', '2026-10-07 16:07:05'];
    private const TERMINAL = ['sent', 'skipped', 'handoff'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $connection = config('whatcrm.ai_queue_connection', 'database');
        $queueConfig = config('queue.connections.'.$connection, []);
        if (($queueConfig['driver'] ?? null) !== 'database'
            || ($queueConfig['table'] ?? null) !== 'jobs'
            || ($queueConfig['connection'] ?? DB::getDefaultConnection()) !== DB::getDefaultConnection()
            || config('whatcrm.ai_queue', 'whatsapp-ai') !== 'whatsapp-ai') {
            $this->error('Refused: expected whatsapp-ai on the current database jobs table.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('jobs') || !Schema::hasTable('whatsapp_ai_reply_batches')) {
            $this->error('Required tables are missing. No changes made.');
            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($apply) {
                $cutoff = now()->subDay();
                $batches = DB::table('whatsapp_ai_reply_batches')
                    ->where('status', 'processing')->whereIn('locked_at', self::LOCKS)
                    ->where('locked_at', '<', $cutoff)->whereColumn('updated_at', '<=', 'locked_at')
                    ->where('process_after', '<', $cutoff)->orderBy('id')->lockForUpdate()->get();
                if ($batches->count() > 2 || $batches->groupBy('locked_at')->contains(fn ($rows) => $rows->count() > 1)) {
                    throw new RuntimeException('Ambiguous incident batch locks. No changes made.');
                }
                $approvedIds = $batches->pluck('id')->all();
                $jobs = [];
                $unmatched = 0;
                // Lock only old unreserved jobs. Pending, retried, fresh and unknown work is retained.
                DB::table('jobs')->where('queue', 'whatsapp-ai')->whereNull('reserved_at')->where('attempts', 0)
                    ->where('created_at', '<', $cutoff->timestamp)->where('available_at', '<=', now()->timestamp)
                    ->orderBy('id')->lockForUpdate()->chunkById(100, function ($rows) use (&$jobs, &$unmatched, $approvedIds) {
                        foreach ($rows as $row) {
                            $batchId = $this->batchId($row->payload);
                            if (!$batchId) { $unmatched++; continue; }
                            $batch = DB::table('whatsapp_ai_reply_batches')->where('id', $batchId)->lockForUpdate()->first();
                            if (!$batch || (!in_array($batch->status, self::TERMINAL, true) && !in_array($batchId, $approvedIds, true))) {
                                $unmatched++;
                                continue;
                            }
                            $jobs[] = (array) $row;
                            if (count($jobs) > 750) throw new RuntimeException('Cleanup exceeds approved 750-job bound. No changes made.');
                        }
                    });

                $backup = null;
                if ($apply && ($batches->isNotEmpty() || $jobs)) {
                    $backup = 'private/repairs/whatsapp-ai-'.now()->format('Ymd-His').'-'.Str::uuid().'.json.enc';
                    $snapshot = json_encode(['incident' => 'whatsapp-ai-october-2026', 'created_at' => now()->toIso8601String(),
                        'batches' => $batches->map(fn ($row) => (array) $row)->all(), 'jobs' => $jobs], JSON_THROW_ON_ERROR);
                    $encrypted = Crypt::encryptString($snapshot);
                    $disk = Storage::disk('local');
                    if (!$disk->put($backup, $encrypted, 'private') || $disk->get($backup) !== $encrypted) {
                        throw new RuntimeException('Backup write/verification failed. No database changes made.');
                    }
                    foreach ($batches as $batch) {
                        DB::table('whatsapp_ai_reply_batches')->where('id', $batch->id)->where('status', 'processing')
                            ->where('locked_at', $batch->locked_at)->update([
                                'status' => 'skipped', 'processed_at' => now(), 'updated_at' => now(),
                                'error' => 'Approved October 2026 repair: abandoned processing lock; no message sent. Backup: '.$backup,
                            ]);
                    }
                    foreach (array_chunk(array_column($jobs, 'id'), 100) as $ids) {
                        DB::table('jobs')->whereIn('id', $ids)->where('queue', 'whatsapp-ai')
                            ->whereNull('reserved_at')->where('attempts', 0)->delete();
                    }
                }
                return ['batches' => $approvedIds, 'jobs' => count($jobs), 'unmatched' => $unmatched, 'backup' => $backup];
            });
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }

        $this->info($apply ? 'Repair transaction committed.' : 'DRY RUN: no database or backup writes.');
        $this->line('Approved stale batches: '.count($result['batches']));
        foreach ($result['batches'] as $id) $this->line('Batch: '.$id);
        $this->line('Confirmed obsolete old jobs: '.$result['jobs']);
        $this->line('Old candidate jobs retained/unmatched: '.$result['unmatched']);
        if ($result['backup']) $this->line('Encrypted backup (local disk): '.$result['backup']);
        $this->line('No messages dispatched. Failed jobs, business records and other queues unchanged.');
        return self::SUCCESS;
    }

    private function batchId(string $payload): ?string
    {
        $data = json_decode($payload, true);
        $class = ProcessWhatsAppAiReply::class;
        if (!is_array($data) || ($data['displayName'] ?? null) !== $class
            || ($data['data']['commandName'] ?? null) !== $class) return null;
        $serialized = $data['data']['command'] ?? null;
        if (!is_string($serialized) || !str_starts_with($serialized, 'O:'.strlen($class).':"'.$class.'":')) return null;
        // Do not unserialize queue payloads: only accept one explicit public UUID batchId.
        if (preg_match_all('/s:7:"batchId";s:36:"([0-9a-fA-F-]{36})";/', $serialized, $matches) !== 1) return null;
        return Str::isUuid($matches[1][0]) ? $matches[1][0] : null;
    }
}
