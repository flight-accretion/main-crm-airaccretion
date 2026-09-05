<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WhatsAppLeadFollowupService
{
    private const MAX_NOTE_LENGTH = 1000;
    private const MAX_CONTEXT_MESSAGES = 12;

    public function createForIncomingMessage(
        Lead $lead,
        WhatsAppMessage $message,
        array $data,
        ?WhatsAppConversation $conversation = null
    ): ?LeadFollowup {
        if (
            !empty($message->lead_followup_id)
        ) {
            return null;
        }

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now(),
            'followup_note' => $this->note(
                $data,
                $message,
                $conversation
            ),
            'followed_by' => $lead->representative_user_id ?: null,
            'status' => 1,
        ]);

        $message->lead_followup_id = $followup->id;
        $message->save();

        return $followup;
    }

    private function note(
        array $data,
        WhatsAppMessage $message,
        ?WhatsAppConversation $conversation
    ): string
    {
        $messageAt =
            $this->messageDate(
                $data['message_at'] ?? $message->message_at
            );
        $messages =
            $this->recentMessages(
                $message,
                $conversation
            );

        $lines = [
            'WhatsApp message received.',
            'WhatsApp conversation summary.',
            'Customer: ' . (($data['customer_name'] ?? null) ?: '-'),
            'Phone: ' . (
                ($data['normalized_phone'] ?? null)
                ?: (($data['raw_phone'] ?? null) ?: '-')
            ),
            'Customer need: '
                . $this->latestIncomingMessage(
                    $messages,
                    $message,
                    $data
                ),
        ];

        $this->appendIfFilled(
            $lines,
            'Service',
            $data['service'] ?? null
        );
        $this->appendIfFilled(
            $lines,
            'Date',
            $data['date'] ?? null
        );
        $this->appendIfFilled(
            $lines,
            'Guests',
            $data['guest'] ?? null
        );
        $this->appendIfFilled(
            $lines,
            'Occasion',
            $data['occasion'] ?? ($data['ocassion'] ?? null)
        );

        $lines[] = 'Received: ' . $messageAt->format('d-m-Y h:i A');

        $conversationLines = $messages
            ->map(fn (WhatsAppMessage $item) =>
                $this->conversationLine($item)
            )
            ->filter()
            ->values();

        if ($conversationLines->isNotEmpty()) {
            $lines[] = 'Recent conversation:';
            $lines = array_merge(
                $lines,
                $conversationLines->all()
            );
        }

        $lines[] = 'Source: WhatsApp / WhatCRM';

        return Str::limit(
            implode(PHP_EOL, $lines),
            self::MAX_NOTE_LENGTH
        );
    }

    private function recentMessages(
        WhatsAppMessage $message,
        ?WhatsAppConversation $conversation
    ): Collection {
        $conversationId =
            optional($conversation)->id
            ?: $message->conversation_id;

        if (!$conversationId) {
            return collect([$message]);
        }

        $messages = WhatsAppMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('message_at')
            ->orderByDesc('created_at')
            ->limit(self::MAX_CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        return $messages->isNotEmpty()
            ? $messages
            : collect([$message]);
    }

    private function latestIncomingMessage(
        Collection $messages,
        WhatsAppMessage $message,
        array $data
    ): string {
        $latest = $messages
            ->filter(fn (WhatsAppMessage $item) =>
                $item->direction === 'incoming'
                && $this->cleanText($item->body)
            )
            ->last();

        return $this->cleanText(optional($latest)->body)
            ?: $this->cleanText($message->body)
            ?: $this->cleanText($data['body'] ?? null)
            ?: '-';
    }

    private function conversationLine(
        WhatsAppMessage $message
    ): ?string {
        $body = $this->cleanText($message->body)
            ?: '[' . ($message->message_type ?: 'message') . ']';

        $speaker = $message->direction === 'outgoing'
            ? 'Agent'
            : 'Customer';

        return $speaker . ': ' . $body;
    }

    private function appendIfFilled(
        array &$lines,
        string $label,
        $value
    ): void {
        $value = $this->cleanText($value);

        if ($value) {
            $lines[] = $label . ': ' . $value;
        }
    }

    private function cleanText($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $value) ?: null;
    }

    private function messageDate($value)
    {
        if (
            is_object($value)
            && method_exists($value, 'format')
        ) {
            return $value;
        }

        if ($value) {
            try {
                return \Carbon\Carbon::parse($value);
            } catch (\Throwable $exception) {
                return now();
            }
        }

        return now();
    }
}
