<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppAiReplyBatch;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppAiReplyService
{
    public function __construct(
        private WhatsAppOpenAiClient $openAi,
        private WhatCrmOutboundMessageService $outbound,
        private WhatsAppProductAllocationService $allocator,
        private WhatsAppLeadFollowupService $followupService,
        private LeadProductRoutingService $productRouter,
        private LeadSourceDataHydrationService $sourceDataHydrator
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

        $relations = [
            'contact',
            'lead.client',
            'lead.representative',
            'lead.leadFollowups',
            'assignedUser',
        ];

        if (Schema::hasTable('lead_rides')) {
            $relations[] = 'lead.rideSegments';
        }

        $conversation = WhatsAppConversation::query()
            ->with($relations)
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
            $aiResult,
            $messages,
            $contextMessages
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
        array $aiResult,
        Collection $messages,
        Collection $contextMessages
    ): ?User {
        $detectedProduct = $aiResult['product'] ?? null;
        $assignmentText = $this->assignmentText(
            $aiResult,
            $messages,
            $contextMessages
        );

        $product =
            $this->productRouter
                ->resolveProduct($detectedProduct);

        if (!$product) {
            $product =
                $this->productRouter
                    ->resolveProduct($assignmentText);
        }

        $lead = $conversation->lead;
        $user = null;

        if (
            $product
            || !$conversation->assigned_user_id
            || $this->allocator
                ->assignmentRoute(
                    $product,
                    $assignmentText
                ) === 'charter'
        ) {
            $user = $this->allocator
                ->findUserForAssignment(
                    $product,
                    $assignmentText
                );
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
                $detectedProduct,
                $aiResult,
                $contextMessages
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
        ?string $detectedProduct,
        array $aiResult,
        Collection $contextMessages
    ): void {
        $service = $this->resolveService(
            $aiResult,
            $product,
            $contextMessages
        );
        $guestCount = $this->guestCountFromAi($aiResult);
        $occasion = $this->cleanText(
            $aiResult['occasion'] ?? null
        );
        $hydrationData = $this->hydrationData(
            $aiResult,
            $service
        );

        DB::transaction(function () use (
            $lead,
            $conversation,
            $product,
            $service,
            $user,
            $detectedProduct,
            $aiResult,
            $contextMessages,
            $guestCount,
            $occasion,
            $hydrationData
        ) {
            if ($product) {
                $lead->product_ids = [
                    $product->id,
                ];
            }

            if (
                $service
                && empty($lead->service_ids_array)
            ) {
                $lead->service_ids = [
                    $service->id,
                ];
            }

            if (
                $guestCount
                && (
                    empty($lead->number_of_passengers)
                    || (int) $lead->number_of_passengers <= 1
                )
            ) {
                $lead->number_of_passengers = $guestCount;
            }

            if (
                $occasion
                && trim((string) $lead->occasion) === ''
            ) {
                $lead->occasion = $occasion;
            }

            if ($user) {
                $lead->representative_user_id = $user->id;
            }

            if ($this->shouldRefreshWhatsAppDescription($lead)) {
                $lead->description =
                    $this->leadDescription(
                        $lead,
                        $conversation,
                        $aiResult,
                        $product,
                        $service,
                        $contextMessages
                    );
            }

            $lead->save();

            $this->sourceDataHydrator->hydrate(
                $lead,
                $hydrationData
            );

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

    private function assignmentText(
        array $aiResult,
        Collection $messages,
        Collection $contextMessages
    ): string {
        return collect([
            $aiResult['product'] ?? null,
            $aiResult['service'] ?? null,
            $aiResult['route'] ?? null,
            $aiResult['origin'] ?? null,
            $aiResult['destination'] ?? null,
            $aiResult['city'] ?? null,
            $messages
                ->pluck('body')
                ->filter()
                ->implode(' '),
            $contextMessages
                ->pluck('body')
                ->filter()
                ->implode(' '),
        ])
            ->filter(fn ($value) =>
                trim((string) $value) !== ''
            )
            ->implode(' ');
    }

    private function resolveService(
        array $aiResult,
        ?Product $product,
        Collection $contextMessages
    ): ?Service {
        if (!Schema::hasTable('services')) {
            return null;
        }

        $services = Service::query()
            ->where('status', 1)
            ->orderBy('service')
            ->get();

        if ($services->isEmpty()) {
            return null;
        }

        $productServices = $product
            ? $services
                ->filter(fn (Service $service) =>
                    $this->serviceBelongsToProduct(
                        $service,
                        $product
                    )
                )
                ->values()
            : collect();

        $candidateServices = $productServices->isNotEmpty()
            ? $productServices
            : $services;

        $serviceText = $this->cleanText(
            $aiResult['service'] ?? null
        );
        $contextText = $this->normalize(
            collect([
                $serviceText,
                $aiResult['product'] ?? null,
                $aiResult['route'] ?? null,
                $aiResult['origin'] ?? null,
                $aiResult['destination'] ?? null,
                $aiResult['city'] ?? null,
                $contextMessages
                    ->pluck('body')
                    ->filter()
                    ->implode(' '),
            ])
                ->filter(fn ($value) =>
                    trim((string) $value) !== ''
                )
                ->implode(' ')
        );

        if ($serviceText) {
            $normalizedServiceText = $this->normalize($serviceText);
            $exact = $candidateServices
                ->first(fn (Service $service) =>
                    $this->normalize($service->service)
                        === $normalizedServiceText
                );

            if ($exact) {
                return $exact;
            }

            $phrase = $candidateServices
                ->first(function (Service $service) use (
                    $normalizedServiceText
                ) {
                    $serviceName =
                        $this->normalize($service->service);

                    return $serviceName !== ''
                        && (
                            Str::contains(
                                $normalizedServiceText,
                                $serviceName
                            )
                            || (
                                mb_strlen(
                                    $normalizedServiceText
                                ) >= 4
                                && Str::contains(
                                    $serviceName,
                                    $normalizedServiceText
                                )
                            )
                        );
                });

            if ($phrase) {
                return $phrase;
            }
        }

        $scored = $candidateServices
            ->map(function (Service $service) use ($contextText) {
                $serviceName = $this->normalize($service->service);

                return [
                    'service' => $service,
                    'score' =>
                        $this->matchScore(
                            $serviceName,
                            $contextText
                        ),
                ];
            })
            ->filter(fn ($item) => $item['score'] >= 2)
            ->sortByDesc('score')
            ->values();

        if ($scored->isNotEmpty()) {
            return $scored->first()['service'];
        }

        if (
            !$serviceText
            && $productServices->count() === 1
        ) {
            return $productServices->first();
        }

        return null;
    }

    private function serviceBelongsToProduct(
        Service $service,
        Product $product
    ): bool {
        $productIds = $service->product_ids;

        if (is_string($productIds)) {
            $productIds =
                json_decode($productIds, true) ?: [];
        }

        return in_array(
            (string) $product->id,
            array_map('strval', (array) $productIds),
            true
        );
    }

    private function matchScore(
        string $serviceName,
        string $contextText
    ): int {
        if ($serviceName === '' || $contextText === '') {
            return 0;
        }

        if (
            Str::contains($contextText, $serviceName)
            || Str::contains($serviceName, $contextText)
        ) {
            return 10;
        }

        $ignored = [
            'a',
            'an',
            'and',
            'by',
            'for',
            'from',
            'in',
            'of',
            'ride',
            'service',
            'the',
            'to',
        ];

        return collect(explode(' ', $serviceName))
            ->filter(fn ($word) =>
                mb_strlen($word) >= 3
                && !in_array($word, $ignored, true)
            )
            ->unique()
            ->filter(fn ($word) =>
                Str::contains($contextText, $word)
            )
            ->count();
    }

    private function hydrationData(
        array $aiResult,
        ?Service $service
    ): array {
        return [
            'service' =>
                optional($service)->service
                ?: ($aiResult['service'] ?? null),
            'service_date' =>
                $aiResult['service_date'] ?? null,
            'date' =>
                $aiResult['service_date']
                ?? ($aiResult['date'] ?? null),
            'guest' =>
                $aiResult['guests'] ?? null,
            'occasion' =>
                $aiResult['occasion'] ?? null,
            'route' =>
                $aiResult['route'] ?? null,
            'origin' =>
                $aiResult['origin'] ?? null,
            'destination' =>
                $aiResult['destination'] ?? null,
            'city' =>
                $aiResult['city'] ?? null,
        ];
    }

    private function guestCountFromAi(array $aiResult): ?int
    {
        $value = $aiResult['guests'] ?? null;

        if (!is_scalar($value)) {
            return null;
        }

        if (
            preg_match(
                '/\d+/',
                (string) $value,
                $matches
            )
        ) {
            return max(1, (int) $matches[0]);
        }

        return null;
    }

    private function shouldRefreshWhatsAppDescription(
        Lead $lead
    ): bool {
        $description = trim((string) $lead->description);

        return $description === ''
            || Str::startsWith(
                $description,
                [
                    'Lead received automatically from WhatsApp / WhatCRM',
                    'WhatsApp / WhatCRM lead qualification',
                ]
            );
    }

    private function leadDescription(
        Lead $lead,
        WhatsAppConversation $conversation,
        array $aiResult,
        ?Product $product,
        ?Service $service,
        Collection $contextMessages
    ): string {
        $contact = $conversation->contact;
        $lines = [
            'Lead received automatically from WhatsApp / WhatCRM message.',
            'Customer: ' . (
                optional($contact)->name
                ?: optional($lead->client)->name
                ?: '-'
            ),
            'Phone: ' . (
                optional($contact)->normalized_phone
                ?: optional($contact)->raw_phone
                ?: optional($lead->client)->contact_number
                ?: '-'
            ),
        ];

        $detailLines = [
            'Product' => optional($product)->product
                ?: ($aiResult['product'] ?? null),
            'Service' => optional($service)->service
                ?: ($aiResult['service'] ?? null),
            'Date' => $aiResult['service_date'] ?? null,
            'Guests' => $aiResult['guests'] ?? null,
            'Route' => $aiResult['route'] ?? null,
            'From' => $aiResult['origin'] ?? null,
            'To' => $aiResult['destination'] ?? null,
            'City' => $aiResult['city'] ?? null,
            'Occasion' => $aiResult['occasion'] ?? null,
        ];

        foreach ($detailLines as $label => $value) {
            $value = $this->cleanText($value);

            if ($value) {
                $lines[] = $label . ': ' . $value;
            }
        }

        $conversationLines = $contextMessages
            ->take(-50)
            ->map(function (WhatsAppMessage $message) {
                $body = $this->cleanText($message->body)
                    ?: '[' . ($message->message_type ?: 'message') . ']';

                $date = $message->message_at
                    ? $message->message_at->format('d-m-Y h:i A')
                    : '-';

                return sprintf(
                    '[%s] %s: %s',
                    $date,
                    strtoupper((string) $message->direction),
                    $body
                );
            })
            ->filter()
            ->values();

        if ($conversationLines->isNotEmpty()) {
            $lines[] = 'Conversation:';
            $lines = array_merge(
                $lines,
                $conversationLines->all()
            );
        }

        return $this->limitText(
            implode(PHP_EOL, $lines),
            60000
        );
    }

    private function cleanText($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if (
            $value === ''
            || in_array(
                strtolower($value),
                [
                    'n/a',
                    'na',
                    'none',
                    'null',
                    'not provided',
                    'not available',
                ],
                true
            )
        ) {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?: null;
    }

    private function normalize($value): string
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value)
            ?: '';

        return trim(
            preg_replace('/\s+/', ' ', $value) ?: ''
        );
    }

    private function limitText(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 3) . '...';
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
