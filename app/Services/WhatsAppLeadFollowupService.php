<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppLeadFollowupService
{
    private const MAX_NOTE_LENGTH = 1000;

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

        $note = $this->note(
            $lead,
            $data,
            $message,
            $conversation
        );

        $existingFollowup =
            $this->matchingSummaryFollowup(
                $lead,
                $note
            );

        if ($existingFollowup) {
            $message->lead_followup_id = $existingFollowup->id;
            $message->save();

            return null;
        }

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now(),
            'followup_note' => $note,
            'followed_by' => $lead->representative_user_id ?: null,
            'status' => 1,
        ]);

        $message->lead_followup_id = $followup->id;
        $message->save();

        return $followup;
    }

    private function note(
        Lead $lead,
        array $data,
        WhatsAppMessage $message,
        ?WhatsAppConversation $conversation
    ): string
    {
        $messageAt =
            $this->messageDate(
                $data['message_at'] ?? $message->message_at
            );

        $lines = [
            'WhatsApp customer message received.',
            'Customer: ' . (
                $this->customerName($lead, $data, $conversation)
                ?: '-'
            ),
            'Phone: ' . (
                $this->phone($lead, $data, $conversation)
                ?: '-'
            ),
        ];

        $this->appendIfFilled(
            $lines,
            'Product',
            $this->productName($lead, $data)
        );
        $this->appendIfFilled(
            $lines,
            'Service',
            $this->serviceName($lead, $data)
        );
        $this->appendIfFilled(
            $lines,
            'Service Date',
            $this->serviceDate($lead, $data)
        );
        $this->appendIfFilled(
            $lines,
            'Guests',
            $this->guestCount($lead, $data)
        );
        $this->appendIfFilled(
            $lines,
            'Route',
            $this->route($lead, $data)
        );
        $this->appendIfFilled(
            $lines,
            'City',
            $this->city($data)
        );
        $this->appendIfFilled(
            $lines,
            'Occasion',
            $data['occasion'] ?? ($data['ocassion'] ?? null)
        );

        $lines[] =
            'Latest Customer Message: '
            . $this->latestCustomerMessage(
                $message,
                $data
            );

        $lines[] = 'Received: ' . $messageAt->format('d-m-Y h:i A');
        $lines[] = 'Source: WhatsApp / WhatCRM';

        return Str::limit(
            implode(PHP_EOL, $lines),
            self::MAX_NOTE_LENGTH
        );
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

    private function matchingSummaryFollowup(
        Lead $lead,
        string $note
    ): ?LeadFollowup {
        $fingerprint = $this->noteFingerprint($note);

        return $lead
            ->leadFollowups()
            ->where(
                'followup_note',
                'like',
                'WhatsApp customer message received.%'
            )
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->first(fn (LeadFollowup $followup) =>
                $this->noteFingerprint(
                    (string) $followup->followup_note
                ) === $fingerprint
            );
    }

    private function noteFingerprint(string $note): string
    {
        $lines = preg_split('/\R/', $note) ?: [];

        $normalized = collect($lines)
            ->map(fn (string $line) =>
                trim(preg_replace('/\s+/', ' ', $line) ?: '')
            )
            ->filter(fn (string $line) =>
                $line !== ''
                && !Str::startsWith(
                    Str::lower($line),
                    'received:'
                )
            )
            ->map(fn (string $line) => Str::lower($line))
            ->values()
            ->implode("\n");

        return sha1($normalized);
    }

    private function customerName(
        Lead $lead,
        array $data,
        ?WhatsAppConversation $conversation
    ): ?string {
        return $this->firstFilled([
            $data['customer_name'] ?? null,
            optional(optional($conversation)->contact)->name,
            optional($lead->client)->name,
        ]);
    }

    private function phone(
        Lead $lead,
        array $data,
        ?WhatsAppConversation $conversation
    ): ?string {
        return $this->firstFilled([
            $data['normalized_phone'] ?? null,
            $data['raw_phone'] ?? null,
            optional(optional($conversation)->contact)->normalized_phone,
            optional(optional($conversation)->contact)->raw_phone,
            optional($lead->client)->contact_number,
            optional($lead->client)->alternate_number,
        ]);
    }

    private function productName(
        Lead $lead,
        array $data
    ): ?string {
        return $this->firstFilled([
            $data['product'] ?? null,
            $this->leadProductNames($lead),
        ]);
    }

    private function serviceName(
        Lead $lead,
        array $data
    ): ?string {
        return $this->firstFilled([
            $data['service'] ?? null,
            $data['service_name'] ?? null,
            $this->leadServiceNames($lead),
        ]);
    }

    private function serviceDate(
        Lead $lead,
        array $data
    ): ?string {
        $payloadDate = $this->firstFilled([
            $data['service_date'] ?? null,
            $data['date'] ?? null,
            $data['departure_date'] ?? null,
            $data['travel_date'] ?? null,
            $data['ride_date'] ?? null,
            $data['from_date'] ?? null,
        ]);

        if ($payloadDate) {
            return $payloadDate;
        }

        $ride = $this->leadRide($lead);

        if (!$ride || $this->looksLikeDefaultSourceRide($lead, $ride)) {
            return null;
        }

        return $ride->from_date
            ? $this->messageDate($ride->from_date)->format('d-m-Y')
            : null;
    }

    private function guestCount(
        Lead $lead,
        array $data
    ): ?string {
        $payloadGuest = $this->firstFilled([
            $data['guest'] ?? null,
            $data['guests'] ?? null,
            $data['passengers'] ?? null,
        ]);

        if ($payloadGuest) {
            return $payloadGuest;
        }

        $leadGuests = (int) $lead->number_of_passengers;

        return $leadGuests > 1
            ? (string) $leadGuests
            : null;
    }

    private function route(
        Lead $lead,
        array $data
    ): ?string {
        $payloadRoute = $this->firstFilled([
            $data['route'] ?? null,
            $data['travel_route'] ?? null,
            $data['city_route'] ?? null,
        ]);

        if ($payloadRoute) {
            return $payloadRoute;
        }

        $origin = $this->firstFilled([
            $data['origin'] ?? null,
            $data['from_place'] ?? null,
            $data['departure_city'] ?? null,
            $data['pickup_city'] ?? null,
        ]);
        $destination = $this->firstFilled([
            $data['destination'] ?? null,
            $data['to_place'] ?? null,
            $data['arrival_city'] ?? null,
            $data['drop_city'] ?? null,
        ]);

        if ($origin && $destination) {
            return $origin . ' to ' . $destination;
        }

        $ride = $this->leadRide($lead);

        if (!$ride) {
            return null;
        }

        $rideOrigin = $this->cleanText($ride->from_place ?? null);
        $rideDestination = $this->cleanText($ride->to_place ?? null);

        if ($rideOrigin && $rideDestination) {
            return $rideOrigin . ' to ' . $rideDestination;
        }

        return null;
    }

    private function city(array $data): ?string
    {
        return $this->firstFilled([
            $data['city'] ?? null,
            $data['service_city'] ?? null,
            $data['location'] ?? null,
        ]);
    }

    private function latestCustomerMessage(
        WhatsAppMessage $message,
        array $data
    ): string {
        return $this->cleanText($message->body)
            ?: $this->cleanText($data['body'] ?? null)
            ?: '[' . ($message->message_type ?: 'message') . ' message]';
    }

    private function leadServiceNames(Lead $lead): ?string
    {
        $ids = $lead->service_ids_array;

        if (
            empty($ids)
            || !Schema::hasTable('services')
        ) {
            return null;
        }

        return Service::query()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->pluck('service')
            ->map(fn ($service) => $this->cleanText($service))
            ->filter()
            ->implode(', ') ?: null;
    }

    private function leadProductNames(Lead $lead): ?string
    {
        $ids = $lead->product_ids_array;

        if (
            empty($ids)
            || !Schema::hasTable('products')
        ) {
            return null;
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->where('status', 1)
            ->pluck('product')
            ->map(fn ($product) => $this->cleanText($product))
            ->filter()
            ->implode(', ') ?: null;
    }

    private function leadRide(Lead $lead)
    {
        if (!Schema::hasTable('lead_rides')) {
            return null;
        }

        return $lead
            ->rideSegments()
            ->whereNotNull('from_date')
            ->orderBy('from_date')
            ->first();
    }

    private function looksLikeDefaultSourceRide(
        Lead $lead,
        $ride
    ): bool {
        if (
            $this->cleanText($ride->from_place ?? null)
            || $this->cleanText($ride->to_place ?? null)
            || !$lead->created_at
            || !$ride->created_at
            || !$ride->from_date
        ) {
            return false;
        }

        $createdCloseTogether =
            abs(
                $lead->created_at->getTimestamp()
                - $ride->created_at->getTimestamp()
            ) <= 10;

        return $createdCloseTogether
            && $this->messageDate($ride->from_date)->toDateString()
                === $lead->created_at->toDateString();
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $clean = $this->cleanText($value);

            if ($clean) {
                return $clean;
            }
        }

        return null;
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

        $normalized = Str::lower($value);

        if (
            in_array(
                $normalized,
                [
                    '-',
                    'n/a',
                    'na',
                    'none',
                    'null',
                    'not available',
                    'not provided',
                ],
                true
            )
        ) {
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
