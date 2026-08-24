<?php

namespace App\Jobs;

use App\Services\WhatsAppAiReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppAiReply implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $batchId
    ) {
    }

    public function handle(
        WhatsAppAiReplyService $service
    ): void {
        $service->processBatch($this->batchId);
    }
}
