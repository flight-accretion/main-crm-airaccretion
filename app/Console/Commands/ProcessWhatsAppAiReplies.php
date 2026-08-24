<?php

namespace App\Console\Commands;

use App\Services\WhatsAppAiReplyService;
use Illuminate\Console\Command;

class ProcessWhatsAppAiReplies extends Command
{
    protected $signature = 'whatsapp:process-ai-replies {--limit=25}';

    protected $description = 'Process due buffered WhatsApp AI replies.';

    public function handle(
        WhatsAppAiReplyService $service
    ): int {
        $summary = $service->processDue(
            max(1, (int) $this->option('limit'))
        );

        $this->info(json_encode($summary));

        return self::SUCCESS;
    }
}
