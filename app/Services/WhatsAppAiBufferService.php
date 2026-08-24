<?php

namespace App\Services;

use App\Jobs\ProcessWhatsAppAiReply;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppAiReplyBatch;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;

class WhatsAppAiBufferService
{
    public function queue(
        WhatsAppConversation $conversation,
        WhatsAppMessage $message
    ): ?WhatsAppAiReplyBatch {
        if (
            !Schema::hasTable('whatsapp_ai_agent_settings')
            || !Schema::hasTable('whatsapp_ai_reply_batches')
        ) {
            return null;
        }

        $setting = WhatsAppAiAgentSetting::active();

        if (!$setting->isReady()) {
            return null;
        }

        $processAfter = now()->addSeconds(
            max(1, (int) $setting->buffer_seconds)
        );

        $batch = WhatsAppAiReplyBatch::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'pending')
            ->first();

        if ($batch) {
            $messageIds = array_values(
                array_unique(
                    array_merge(
                        $batch->message_ids ?: [],
                        [
                            $message->id,
                        ]
                    )
                )
            );

            $batch->update([
                'process_after' => $processAfter,
                'message_ids' => $messageIds,
                'error' => null,
            ]);
        } else {
            $batch = WhatsAppAiReplyBatch::create([
                'conversation_id' => $conversation->id,
                'status' => 'pending',
                'process_after' => $processAfter,
                'message_ids' => [
                    $message->id,
                ],
            ]);
        }

        $this->dispatchIfEnabled($batch);

        return $batch;
    }

    private function dispatchIfEnabled(
        WhatsAppAiReplyBatch $batch
    ): void {
        if (
            !filter_var(
                config('whatcrm.ai_auto_dispatch', true),
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            return;
        }

        if (!Schema::hasTable('jobs')) {
            return;
        }

        ProcessWhatsAppAiReply::dispatch($batch->id)
            ->delay($batch->process_after)
            ->onConnection(
                config('whatcrm.ai_queue_connection', 'database')
            )
            ->onQueue(config('whatcrm.ai_queue', 'whatsapp-ai'));
    }
}
