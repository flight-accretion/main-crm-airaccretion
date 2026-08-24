<?php

namespace App\Services;

use App\Jobs\ProcessWhatsAppAiReply;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppAiReplyBatch;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsAppAiBufferService
{
    private ?string $lastStatus = null;

    public function clearStatus(): void
    {
        $this->lastStatus = null;
    }

    public function lastStatus(): ?string
    {
        return $this->lastStatus;
    }

    public function queue(
        WhatsAppConversation $conversation,
        WhatsAppMessage $message
    ): ?WhatsAppAiReplyBatch {
        $this->lastStatus = null;

        if (
            !Schema::hasTable('whatsapp_ai_agent_settings')
            || !Schema::hasTable('whatsapp_ai_reply_batches')
        ) {
            return $this->skip(
                'ai_tables_missing',
                $conversation,
                $message
            );
        }

        $setting = WhatsAppAiAgentSetting::active();

        if (!$setting->isReady()) {
            return $this->skip(
                'ai_disabled_or_not_configured',
                $conversation,
                $message
            );
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

        $this->lastStatus = 'queued';
        $this->dispatchIfEnabled($batch);

        return $batch;
    }

    private function skip(
        string $status,
        WhatsAppConversation $conversation,
        WhatsAppMessage $message
    ): ?WhatsAppAiReplyBatch {
        $this->lastStatus = $status;

        Log::info(
            'WhatsApp AI buffer skipped',
            [
                'reason' => $status,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]
        );

        return null;
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
