<?php

namespace App\Jobs;

use App\Models\WhatsAppAiReplyBatch;
use App\Services\WhatsAppAiEligibilityService;
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
        WhatsAppAiReplyService $service,
        WhatsAppAiEligibilityService $eligibility
    ): void {
        $batch = WhatsAppAiReplyBatch::query()
            ->with('conversation')
            ->find($this->batchId);

        if (
            !$batch
            || !$batch->conversation
            || !$eligibility->canAiOwn($batch->conversation)
        ) {
            if ($batch) {
                $batch->update([
                    'status' => 'skipped',
                    'processed_at' => now(),
                    'error' => null,
                ]);
            }

            return;
        }

        $service->processBatch($this->batchId);
    }
}
