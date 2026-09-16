<?php

namespace App\Console\Commands;

use App\Models\WhatsAppConversation;
use App\Services\WhatsAppPreLeadHandoffService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class HandoffInactiveWhatsAppAiConversations extends Command
{
    protected $signature =
        'whatsapp:handoff-inactive-ai-conversations {--limit=50}';

    protected $description =
        'Hand off inactive WhatsApp AI pre-lead conversations to CRM sales.';

    public function handle(
        WhatsAppPreLeadHandoffService $handoffService
    ): int {
        if (
            !Schema::hasTable('whatsapp_conversations')
            || !Schema::hasColumn('whatsapp_conversations', 'conversation_owner')
            || !Schema::hasColumn('whatsapp_conversations', 'last_conversation_activity_at')
            || !Schema::hasColumn('whatsapp_conversations', 'human_handoff_at')
        ) {
            $this->info(json_encode([
                'processed' => 0,
                'skipped' => 'prelead_columns_missing',
            ]));

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $conversations = WhatsAppConversation::query()
            ->where('conversation_owner', 'AI')
            ->whereNull('human_handoff_at')
            ->whereNotNull('last_conversation_activity_at')
            ->where(
                'last_conversation_activity_at',
                '<=',
                now()->subMinutes(10)
            )
            ->orderBy('last_conversation_activity_at')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($conversations as $conversation) {
            $lead = $handoffService->handoff(
                $conversation,
                [],
                '10_minute_inactivity',
                'P4'
            );

            if ($lead) {
                $processed++;
            }
        }

        $this->info(json_encode([
            'processed' => $processed,
        ]));

        return self::SUCCESS;
    }
}
