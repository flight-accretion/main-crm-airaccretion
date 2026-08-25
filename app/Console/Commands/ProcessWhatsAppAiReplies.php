<?php

namespace App\Console\Commands;

use App\Services\WhatsAppAiReplyService;
use Illuminate\Console\Command;

class ProcessWhatsAppAiReplies extends Command
{
    protected $signature =
        'whatsapp:process-ai-replies {--limit=25} {--watch=0} {--sleep=1}';

    protected $description = 'Process due buffered WhatsApp AI replies.';

    public function handle(
        WhatsAppAiReplyService $service
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $watchSeconds = max(0.0, (float) $this->option('watch'));
        $sleepSeconds = max(0.01, (float) $this->option('sleep'));
        $deadline = microtime(true) + $watchSeconds;
        $totals = [
            'processed' => 0,
            'failed' => 0,
            'polls' => 0,
        ];

        do {
            $summary = $service->processDue($limit);

            $totals['processed'] += (int) ($summary['processed'] ?? 0);
            $totals['failed'] += (int) ($summary['failed'] ?? 0);
            $totals['polls']++;

            $this->info(json_encode($summary));

            if ($watchSeconds <= 0 || microtime(true) >= $deadline) {
                break;
            }

            $remainingSeconds = $deadline - microtime(true);

            if ($remainingSeconds <= 0) {
                break;
            }

            usleep(
                (int) round(
                    min($sleepSeconds, $remainingSeconds) * 1000000
                )
            );
        } while (true);

        if ($watchSeconds > 0) {
            $this->info(
                json_encode([
                    'watch_finished' => true,
                    'processed' => $totals['processed'],
                    'failed' => $totals['failed'],
                    'polls' => $totals['polls'],
                ])
            );
        }

        return self::SUCCESS;
    }
}
