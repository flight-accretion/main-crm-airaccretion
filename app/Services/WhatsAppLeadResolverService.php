<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\Product;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Str;

class WhatsAppLeadResolverService
{
    public function __construct(
        private ActiveLeadService $activeLeadService,
        private WhatsAppProductAllocationService $allocator,
        private LeadAllocationService $leadAllocationService,
        private LeadProductRoutingService $productRouter
    ) {
    }

    public function resolveForIncoming(
        WhatsAppContact $contact,
        WhatsAppConversation $conversation,
        array $data
    ): ?Lead {
        $existingLead = $this->activeLeadService
            ->findByPhone($contact->normalized_phone);

        if ($existingLead) {
            $this->linkConversationToLead(
                $conversation,
                $existingLead
            );

            return $existingLead;
        }

        $client = $this->findOrCreateClient(
            $contact,
            $data
        );

        $existingLead = $this->activeLeadService
            ->findByPhone($contact->normalized_phone);

        if ($existingLead) {
            $this->linkConversationToLead(
                $conversation,
                $existingLead
            );

            return $existingLead;
        }

        $serviceText =
            $data['service']
            ?? null;

        $product = $this->productRouter
            ->resolveProduct(
                $serviceText
            );

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => null,
            'service_ids' => null,
            'product_ids' => $product ? [$product->id] : null,
            'number_of_passengers' => $this->guestCount($data),
            'description' => $this->description($data),
            'occasion' => $data['occasion'] ?? null,
        ]);

        $salesperson = $this->resolveSalesperson(
            $product,
            $serviceText
        );

        if ($salesperson) {
            $lead->representative_user_id = $salesperson->id;
            $lead->save();

            LeadAllocationLog::create([
                'lead_id' => $lead->id,
                'salesperson_id' => $salesperson->id,
                'action' => 'whatsapp_message_assigned',
                'result' => 'success',
                'details' => $this->assignmentDetails(
                    $product,
                    $serviceText
                ),
            ]);
        } else {
            $this->leadAllocationService
                ->queueLead(
                    $lead,
                    $this->queueReason(
                        $product,
                        $serviceText
                    )
                );
        }

        $this->linkConversationToLead(
            $conversation,
            $lead
        );

        return $lead;
    }

    private function findOrCreateClient(
        WhatsAppContact $contact,
        array $data
    ): Client {
        $client = $this->findClientByPhone(
            $contact->normalized_phone
        );

        $name =
            $data['customer_name']
            ?: 'WhatsApp Lead ' . $contact->normalized_phone;

        if (!$client) {
            return Client::create([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'email' => null,
                'contact_number' => $contact->normalized_phone,
                'alternate_number' => null,
                'status' => 1,
                'created_by' => null,
            ]);
        }

        if (
            $data['customer_name']
            && (
                empty($client->name)
                || str_starts_with(
                    (string) $client->name,
                    'WhatsApp Lead '
                )
            )
        ) {
            $client->name = $data['customer_name'];
            $client->save();
        }

        return $client;
    }

    private function findClientByPhone(?string $phone): ?Client
    {
        if (!$phone) {
            return null;
        }

        $contactExpression = $this->digitsSql('contact_number');
        $alternateExpression = $this->digitsSql('alternate_number');

        return Client::query()
            ->whereRaw(
                "{$contactExpression} LIKE ?",
                ['%' . $phone]
            )
            ->orWhereRaw(
                "{$alternateExpression} LIKE ?",
                ['%' . $phone]
            )
            ->first();
    }

    private function resolveSalesperson(
        ?Product $product,
        ?string $serviceText
    )
    {
        $assignmentRoute =
            $this->assignmentRoute(
                $product,
                $serviceText
            );

        if (
            $assignmentRoute !== 'retail'
            &&
            $product
            && $this->allocator
                ->hasConfiguredProductMapping($product->id)
        ) {
            return $this->allocator->findUser($product->id);
        }

        if ($assignmentRoute === 'charter') {
            return $this->allocator
                ->findCharterUser(
                    optional($product)->id
                );
        }

        return $this->allocator->findRetailUser();
    }

    private function assignmentRoute(
        ?Product $product,
        ?string $serviceText
    ): string {
        $isCharter =
            $this->productRouter
                ->isCharterProduct(
                    $product,
                    $serviceText
                );

        if (
            $product
            && $this->allocator
                ->hasConfiguredProductMapping($product->id)
        ) {
            return $isCharter
                ? 'charter'
                : 'product';
        }

        if (
            !$product
            && $isCharter
            && $this->allocator
                ->hasConfiguredCharterMapping()
        ) {
            return 'charter';
        }

        return 'retail';
    }

    private function assignmentDetails(
        ?Product $product,
        ?string $serviceText
    ): string {
        $assignmentRoute =
            $this->assignmentRoute(
                $product,
                $serviceText
            );

        if ($assignmentRoute === 'charter') {
            return 'Assigned from WhatCRM message using charter product routing.';
        }

        if ($assignmentRoute === 'product') {
            return 'Assigned from WhatCRM message using product routing.';
        }

        return 'Assigned from WhatCRM message using retail routing.';
    }

    private function queueReason(
        ?Product $product,
        ?string $serviceText
    ): string {
        $assignmentRoute =
            $this->assignmentRoute(
                $product,
                $serviceText
            );

        if ($assignmentRoute === 'charter') {
            return 'whatsapp_message_charter_waiting';
        }

        if ($assignmentRoute === 'product') {
            return 'whatsapp_message_product_waiting';
        }

        return 'whatsapp_message_retail_waiting';
    }

    private function linkConversationToLead(
        WhatsAppConversation $conversation,
        Lead $lead
    ): void {
        $conversation->lead_id = $lead->id;
        $conversation->assigned_user_id =
            $lead->representative_user_id;
        $conversation->save();
    }

    private function guestCount(array $data): int
    {
        if (is_numeric($data['guest'] ?? null)) {
            return max(1, (int) $data['guest']);
        }

        return 1;
    }

    private function description(array $data): string
    {
        $values = [
            'Lead received automatically from WhatsApp / WhatCRM message.',
        ];

        foreach (
            [
                'service' => 'Service',
                'date' => 'Date',
                'city' => 'City',
                'guest' => 'Guests',
            ]
            as $key => $label
        ) {
            if (
                isset($data[$key])
                && trim((string) $data[$key]) !== ''
            ) {
                $values[] =
                    $label
                    . ': '
                    . trim((string) $data[$key]);
            }
        }

        if (!empty($data['occasion'])) {
            $values[] = 'Occasion: ' . $data['occasion'];
        }

        if (!empty($data['body'])) {
            $values[] = 'Message: ' . $data['body'];
        }

        return implode(PHP_EOL, $values);
    }

    private function digitsSql(string $column): string
    {
        if (config('database.default') === 'pgsql') {
            return "regexp_replace({$column}, '[^0-9]', '', 'g')";
        }

        return
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE(" .
            "REPLACE({$column}, '+', '')," .
            " '-', '')," .
            " ' ', '')," .
            " '(', '')," .
            " ')', '')";
    }
}
