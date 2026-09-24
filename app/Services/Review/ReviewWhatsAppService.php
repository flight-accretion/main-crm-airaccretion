<?php

namespace App\Services\Review;

use App\Models\ReviewConversation;
use App\Models\User;
use App\Services\WhatCrmOutboundMessageService;
use Illuminate\Support\Facades\Log;

class ReviewWhatsAppService
{
    public function __construct(
        private WhatCrmOutboundMessageService $outbound
    ) {
    }

    public function sendInitialTemplate(ReviewConversation $review): bool
    {
        return $this->sendTemplate(
            $review,
            $this->setting($review, 'initial_template_name'),
            'initial review request'
        );
    }

    public function sendReminder(ReviewConversation $review): bool
    {
        return $this->sendTemplate(
            $review,
            $this->setting($review, 'reminder_template_name'),
            'review reminder'
        );
    }

    public function sendText(ReviewConversation $review, string $message): bool
    {
        if (trim($message) === '') {
            return false;
        }

        try {
            $result = $this->outbound->sendText([
                'number' => $review->customer_phone,
                'message' => $message,
                'lead_id' => $review->lead_id,
            ]);

            $this->attachConversation($review, $result);

            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            Log::error('Review WhatsApp text send failed', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendReviewLink(ReviewConversation $review): bool
    {
        $url = trim((string) $this->setting($review, 'review_url'));

        if ($url === '') {
            Log::warning('Review URL not configured', ['review_id' => $review->id]);

            return false;
        }

        return $this->sendText($review, $url);
    }

    public function notifyOperations(
        ReviewConversation $review,
        string $operationType,
        string $summary
    ): bool {
        if ($review->operations_notified_at) {
            return false;
        }

        if (!$this->boolSetting($review, 'notify_operations', true)) {
            return false;
        }

        $template = trim((string) $this->setting($review, 'operations_template_name'));

        if ($template === '') {
            Log::warning('Review Operations WhatsApp template not configured', [
                'review_id' => $review->id,
            ]);

            return false;
        }

        $recipients = $this->operationsRecipientNumbers($review);

        if (empty($recipients)) {
            Log::warning('Review Operations WhatsApp recipients not configured', [
                'review_id' => $review->id,
            ]);

            return false;
        }

        $sent = false;

        foreach ($recipients as $number) {
            try {
                $result = $this->outbound->sendTemplate([
                    'number' => $number,
                    'template_name' => $template,
                    'body_values' => $this->operationsBodyValues(
                        $review,
                        $operationType,
                        $summary
                    ),
                    'lead_id' => $review->lead_id,
                    'rendered_body' => 'New Operations Review: ' . $summary,
                ]);

                $sent = $sent || (bool) ($result['success'] ?? false);
            } catch (\Throwable $e) {
                Log::error('Review Operations WhatsApp notification failed', [
                    'review_id' => $review->id,
                    'recipient' => $number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sent) {
            $review->update(['operations_notified_at' => now()]);
        }

        return $sent;
    }

    private function sendTemplate(
        ReviewConversation $review,
        ?string $template,
        string $label
    ): bool {
        $template = trim((string) $template);

        if ($template === '') {
            Log::warning('Review WhatsApp template not configured', [
                'review_id' => $review->id,
                'template_label' => $label,
            ]);

            return false;
        }

        try {
            $result = $this->outbound->sendTemplate([
                'number' => $review->customer_phone,
                'template_name' => $template,
                'body_values' => $this->bodyValues($review),
                'lead_id' => $review->lead_id,
                'rendered_body' => 'Review ' . $label . ' sent.',
            ]);

            $this->attachConversation($review, $result);

            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            Log::error('Review WhatsApp template send failed', [
                'review_id' => $review->id,
                'template_label' => $label,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function bodyValues(ReviewConversation $review): array
    {
        $review->loadMissing('lead.client');

        return [
            optional(optional($review->lead)->client)->name ?: 'Customer',
        ];
    }

    private function operationsBodyValues(
        ReviewConversation $review,
        string $operationType,
        string $summary
    ): array {
        $review->loadMissing('lead.client');

        return [
            optional(optional($review->lead)->client)->name ?: 'Customer',
            $this->serviceLabel($review),
            ucfirst(str_replace('_', ' ', $operationType)),
            $summary !== '' ? $summary : 'Review requires Operations attention.',
        ];
    }

    private function operationsRecipientNumbers(ReviewConversation $review): array
    {
        $numbers = $this->listSetting($review, 'operations_recipient_numbers');
        $userIds = $this->listSetting($review, 'operations_recipient_user_ids');

        if (!empty($userIds)) {
            $numbers = array_merge(
                $numbers,
                User::query()
                    ->whereIn('id', $userIds)
                    ->whereNotNull('contact_number')
                    ->pluck('contact_number')
                    ->all()
            );
        }

        return collect($numbers)
            ->map(fn ($number) => preg_replace('/\D+/', '', (string) $number))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function serviceLabel(ReviewConversation $review): string
    {
        try {
            $names = optional($review->lead)->service_names ?: [];
        } catch (\Throwable) {
            $names = [];
        }

        return empty($names)
            ? 'N/A'
            : implode(', ', array_slice($names, 0, 3));
    }

    private function boolSetting(
        ReviewConversation $review,
        string $key,
        bool $default = false
    ): bool {
        $value = $this->setting($review, $key, $default);

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? (bool) $value;
    }

    private function listSetting(ReviewConversation $review, string $key): array
    {
        $value = $this->setting($review, $key, []);

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $value
        )));
    }

    private function setting(ReviewConversation $review, string $key, $default = null)
    {
        $settings = optional($review->aiAgent)->settings ?: [];

        return $settings[$key]
            ?? config('review.' . $key)
            ?? $default;
    }

    private function attachConversation(ReviewConversation $review, array $result): void
    {
        $conversationId = $result['conversation_id'] ?? null;

        if ($conversationId && !$review->whatsapp_conversation_id) {
            $review->update(['whatsapp_conversation_id' => $conversationId]);
        }
    }
}
