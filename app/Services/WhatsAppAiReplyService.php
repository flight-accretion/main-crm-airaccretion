<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppAiReplyBatch;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class WhatsAppAiReplyService
{
    public function __construct(
        private WhatsAppOpenAiClient $openAi,
        private WhatCrmOutboundMessageService $outbound,
        private WhatsAppProductAllocationService $allocator,
        private WhatsAppLeadFollowupService $followupService
    ) {
    }

    public function processDue(int $limit = 25): array
    {
        if (
            !Schema::hasTable('whatsapp_ai_agent_settings')
            || !Schema::hasTable('whatsapp_ai_reply_batches')
        ) {
            return [
                'processed' => 0,
                'failed' => 0,
                'skipped' => 'ai_tables_missing',
            ];
        }

        $setting = WhatsAppAiAgentSetting::active();

        if (!$setting->isReady()) {
            return [
                'processed' => 0,
                'failed' => 0,
                'skipped' => 'ai_disabled_or_not_configured',
            ];
        }

        $batches = WhatsAppAiReplyBatch::query()
            ->where('status', 'pending')
            ->where('process_after', '<=', now())
            ->orderBy('process_after')
            ->limit($limit)
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($batches as $batch) {
            try {
                if ($this->processBatch($batch->id, $setting)) {
                    $processed++;
                }
            } catch (\Throwable $exception) {
                $failed++;

                WhatsAppAiReplyBatch::query()
                    ->whereKey($batch->id)
                    ->update([
                        'status' => 'failed',
                        'error' => $exception->getMessage(),
                        'processed_at' => now(),
                        'updated_at' => now(),
                    ]);

                Log::error(
                    'WhatsApp AI reply batch failed',
                    [
                        'batch_id' => $batch->id,
                        'error' => $exception->getMessage(),
                    ]
                );
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    public function processBatch(
        string $batchId,
        ?WhatsAppAiAgentSetting $setting = null
    ): bool {
        $setting = $setting ?: WhatsAppAiAgentSetting::active();

        if (!$setting->isReady()) {
            return false;
        }

        $batch = WhatsAppAiReplyBatch::query()
            ->whereKey($batchId)
            ->where('status', 'pending')
            ->first();

        if (!$batch) {
            return false;
        }

        $batch->update([
            'status' => 'processing',
            'locked_at' => now(),
            'error' => null,
        ]);

        $conversation = WhatsAppConversation::query()
            ->with([
                'contact',
                'lead',
                'assignedUser',
            ])
            ->find($batch->conversation_id);

        if (!$conversation) {
            throw new RuntimeException(
                'WhatsApp conversation was not found.'
            );
        }

        $messages = $this->pendingIncomingMessages(
            $conversation
        );

        if ($messages->isEmpty()) {
            $batch->update([
                'status' => 'skipped',
                'processed_at' => now(),
                'message_ids' => [],
            ]);

            return false;
        }

        $contextMessages = $this->contextMessages(
            $conversation,
            $setting
        );

        $aiResult = $this->openAi->generateReply(
            $setting,
            $conversation,
            $messages,
            Product::query()
                ->where('status', 1)
                ->orderBy('product')
                ->get([
                    'id',
                    'product',
                ]),
            $contextMessages
        );

        $assignedUser = $this->applyProductAssignment(
            $conversation,
            $aiResult['product'],
            $messages
        );

        $outboundResult = $this->outbound->sendText([
            'number' =>
                optional($conversation->contact)->normalized_phone
                ?: optional($conversation->contact)->raw_phone,
            'name' => optional($conversation->contact)->name,
            'message' => $aiResult['reply'],
            'chat_id' => $conversation->whatcrm_chat_id,
            'agent_user_id' => optional($assignedUser)->id,
            'assigned_agent_user_id' => optional($assignedUser)->id,
            'assigned_agent' => optional($assignedUser)->name,
        ]);

        if (!($outboundResult['success'] ?? false)) {
            throw new RuntimeException(
                'WhatCRM did not accept the AI reply.'
            );
        }

        WhatsAppMessage::query()
            ->whereIn('id', $messages->pluck('id')->all())
            ->update([
                'ai_reply_batch_id' => $batch->id,
                'ai_processed_at' => now(),
                'updated_at' => now(),
            ]);

        $batch->update([
            'status' => 'sent',
            'processed_at' => now(),
            'response_message_id' =>
                $outboundResult['crm_message_id'] ?? null,
            'assigned_user_id' => optional($assignedUser)->id,
            'detected_product' => $aiResult['product'],
            'message_ids' => $messages->pluck('id')->values()->all(),
            'error' => null,
        ]);

        return true;
    }

    private function pendingIncomingMessages(
        WhatsAppConversation $conversation
    ): Collection {
        return WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->whereNull('ai_processed_at')
            ->orderBy('message_at')
            ->orderBy('created_at')
            ->get();
    }

    private function contextMessages(
        WhatsAppConversation $conversation,
        WhatsAppAiAgentSetting $setting
    ): Collection {
        return WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('message_at')
            ->orderByDesc('created_at')
            ->limit($setting->contextMessageLimit())
            ->get()
            ->reverse()
            ->values();
    }

    private function applyProductAssignment(
        WhatsAppConversation $conversation,
        ?string $detectedProduct,
        Collection $messages
    ): ?User {
        $product = $this->resolveProduct($detectedProduct);
        $lead = $conversation->lead;
        $user = null;

        if ($product) {
            if (
                $this->allocator
                    ->hasConfiguredProductMapping($product->id)
            ) {
                $user = $this->allocator->findUser($product->id);
            } else {
                $user = $this->allocator->findRetailUser();
            }
        } elseif (!$conversation->assigned_user_id) {
            $user = $this->allocator->findRetailUser();
        }

        if (!$user && $conversation->assignedUser) {
            $user = $conversation->assignedUser;
        }

        if (!$user && $lead && $lead->representative) {
            $user = $lead->representative;
        }

        if ($lead) {
            $this->updateLeadAssignment(
                $lead,
                $conversation,
                $product,
                $user,
                $detectedProduct
            );

            $lead->refresh();

            foreach ($messages as $message) {
                $this->followupService
                    ->createForIncomingMessage(
                        $lead,
                        $message,
                        $this->followupData(
                            $conversation,
                            $message
                        )
                    );
            }
        } elseif ($user) {
            $conversation->assigned_user_id = $user->id;
            $conversation->save();
        }

        return $user;
    }

    private function updateLeadAssignment(
        Lead $lead,
        WhatsAppConversation $conversation,
        ?Product $product,
        ?User $user,
        ?string $detectedProduct
    ): void {
        DB::transaction(function () use (
            $lead,
            $conversation,
            $product,
            $user,
            $detectedProduct
        ) {
            if ($product) {
                $lead->product_ids = [
                    $product->id,
                ];
            }

            if ($user) {
                $lead->representative_user_id = $user->id;
            }

            $lead->save();

            if ($user) {
                $conversation->assigned_user_id = $user->id;
            }

            $conversation->lead_id = $lead->id;
            $conversation->save();

            if ($user) {
                LeadAllocationLog::create([
                    'lead_id' => $lead->id,
                    'salesperson_id' => $user->id,
                    'action' => 'whatsapp_ai_assigned',
                    'result' => 'success',
                    'details' => $product
                        ? 'Assigned from WhatsApp AI product detection.'
                        : 'Assigned from WhatsApp AI empty-product routing: '
                            . ($detectedProduct ?: 'N/A'),
                ]);
            }
        });
    }

    private function resolveProduct(?string $productName): ?Product
    {
        $productName = trim((string) $productName);

        if (
            $productName === ''
            || mb_strtolower($productName) === 'n/a'
        ) {
            return null;
        }

        $product = Product::query()
            ->whereRaw(
                'LOWER(product) = ?',
                [
                    mb_strtolower($productName),
                ]
            )
            ->first();

        if ($product) {
            return $product;
        }

        return Product::query()
            ->where('product', 'LIKE', '%' . $productName . '%')
            ->first();
    }

    private function followupData(
        WhatsAppConversation $conversation,
        WhatsAppMessage $message
    ): array {
        return [
            'customer_name' =>
                optional($conversation->contact)->name,
            'normalized_phone' =>
                optional($conversation->contact)->normalized_phone,
            'raw_phone' =>
                optional($conversation->contact)->raw_phone,
            'body' => $message->body,
            'message_at' => $message->message_at ?: now(),
        ];
    }
}
