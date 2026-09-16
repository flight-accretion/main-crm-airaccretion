<?php

namespace App\Services;

use App\Models\Product;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

class WhatsAppAiStateService
{
    public function defaults(): array
    {
        return [
            'service_family' => null,
            'product_id' => null,
            'product_name' => null,
            'product_link' => null,
            'city' => null,
            'origin' => null,
            'destination' => null,
            'date' => null,
            'departure_time' => null,
            'passengers' => null,
            'occasion' => null,
            'patient_location' => null,
            'date_or_urgency' => null,
            'budget' => null,
            'preferred_option' => null,
            'price_discussed' => null,
            'price_source' => null,
            'price_fetched_at' => null,
            'main_objection' => null,
            'unresolved_question' => null,
            'language' => null,
            'conversation_stage' => 'DISCOVERY',
            'initial_booking_intent' => false,
            'initial_payment_intent' => false,
            'customer_ready_to_book' => false,
            'customer_ready_to_pay' => false,
        ];
    }

    public function get(WhatsAppConversation $conversation): array
    {
        return array_merge(
            $this->defaults(),
            is_array($conversation->ai_state) ? $conversation->ai_state : []
        );
    }

    public function merge(
        WhatsAppConversation $conversation,
        array $updates
    ): array {
        $old = $this->get($conversation);
        $state = $old;
        $updates = $this->normalizeUpdates($updates);

        foreach ($updates as $key => $value) {
            if ($value !== null) {
                $state[$key] = $value;
            }
        }

        if (
            array_key_exists('city', $updates)
            && ($updates['city'] ?? null) !== null
            && ($updates['city'] ?? null) !== ($old['city'] ?? null)
        ) {
            foreach (
                [
                    'product_id',
                    'product_name',
                    'product_link',
                    'preferred_option',
                    'price_discussed',
                    'price_source',
                ] as $key
            ) {
                $state[$key] = null;
            }
        }

        if (
            array_key_exists('passengers', $updates)
            && ($updates['passengers'] ?? null) !== ($old['passengers'] ?? null)
        ) {
            $state['preferred_option'] = null;
        }

        if (
            empty($state['product_id'])
            && !empty($state['product_name'])
        ) {
            $product = Product::query()
                ->where('product', $state['product_name'])
                ->first();

            if ($product) {
                $state['product_id'] = $product->id;
            }
        }

        if ($this->supportsAiState()) {
            $conversation->ai_state = $state;
            $conversation->save();
        }

        return $state;
    }

    private function normalizeUpdates(array $updates): array
    {
        $extracted = $updates['extracted_fields'] ?? [];

        if (is_array($extracted)) {
            $updates = array_merge($updates, $extracted);
        }

        if (!empty($updates['product']) && empty($updates['product_name'])) {
            $updates['product_name'] = $updates['product'];
        }

        if (!empty($updates['guests']) && empty($updates['passengers'])) {
            $updates['passengers'] = $updates['guests'];
        }

        if (!empty($updates['service_date']) && empty($updates['date'])) {
            $updates['date'] = $updates['service_date'];
        }

        if (!empty($updates['route'])) {
            $parts = preg_split('/\s+(?:to|->)\s+/i', (string) $updates['route']);

            if (count($parts) === 2) {
                $updates['origin'] = $updates['origin'] ?? trim($parts[0]);
                $updates['destination'] = $updates['destination'] ?? trim($parts[1]);
            }
        }

        unset($updates['extracted_fields']);

        return $updates;
    }

    private function supportsAiState(): bool
    {
        return Schema::hasTable('whatsapp_conversations')
            && Schema::hasColumn('whatsapp_conversations', 'ai_state');
    }
}
