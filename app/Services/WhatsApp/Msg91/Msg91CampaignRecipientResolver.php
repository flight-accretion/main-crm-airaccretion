<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Lead;
use App\Models\Msg91WhatsAppSuppression;
use App\Models\User;
use App\Services\LeadTableProducts;
use Illuminate\Support\Collection;

class Msg91CampaignRecipientResolver
{
    public function __construct(private Msg91CampaignAccess $access, private Msg91CampaignPhone $phones, private LeadTableProducts $products)
    {
    }

    /** @param array<int,string> $leadIds */
    public function resolve(User $user, array $leadIds): array
    {
        $leadIds = array_values(array_unique(array_filter(array_map('strval', $leadIds))));
        $query = Lead::query()->with([
    'client.country',
    'representative',
    'rideSegments',
    'latestFollowup',
    ]);
        $this->access->scopeLeads($query, $user);
        $leads = $query->whereIn('leads.id', $leadIds)->get()
            ->sortByDesc(fn (Lead $lead) => sprintf('%s|%s', optional($lead->created_at)->format('YmdHis.u'), $lead->id))
            ->values();

       
            $resolved = [];

            $excluded = [
                'unauthorized' => max(0, count($leadIds) - $leads->count()),
                'invalid' => 0,
                'suppressed' => 0,
                'duplicates' => 0,
            ];

            $seen = [];
            $candidates = [];

            // Keep the existing newest-lead-first ordering.
            foreach ($leads as $lead) {
                if (!$lead->client) {
                    $excluded['invalid']++;
                    continue;
                }

                $phone = $this->phones->forClient($lead->client);

                if (!$phone) {
                    $excluded['invalid']++;
                    continue;
                }

                if (isset($seen[$phone])) {
                    $excluded['duplicates']++;
                    continue;
                }

                $seen[$phone] = true;

                $candidates[] = [
                    'lead' => $lead,
                    'client' => $lead->client,
                    'phone' => $phone,
                ];
            }

            // Fetch suppressed numbers in batches rather than
            // querying the database for each recipient.
            $suppressed = [];

            foreach (array_chunk(array_keys($seen), 500) as $phones) {
                $numbers = Msg91WhatsAppSuppression::query()
                    ->whereIn('normalized_phone', $phones)
                    ->pluck('normalized_phone');

                foreach ($numbers as $number) {
                    $suppressed[(string) $number] = true;
                }
            }

            foreach ($candidates as $candidate) {
                if (isset($suppressed[$candidate['phone']])) {
                    $excluded['suppressed']++;
                    continue;
                }

                $resolved[] = [
                    'lead' => $candidate['lead'],
                    'client' => $candidate['client'],
                    'phone' => $candidate['phone'],
                    'variables' => $this->variables($candidate['lead']),
                ];
            }


        return ['recipients' => collect($resolved), 'excluded' => $excluded, 'selected_count' => count($leadIds)];
    }

    private function variables(Lead $lead): array
    {
        $ride = $lead->rideSegments->sortBy('from_date')->first();
        $productNames = $this->products->forLeads(collect([$lead]))[$lead->id] ?? '';
        return [
            'customer_name' => (string) optional($lead->client)->name,
            'customer_email' => (string) optional($lead->client)->email,
            'product_name' => (string) $productNames,
            'service_date' => optional($ride?->from_date)->format('d M Y') ?? '',
            'next_followup_date' => optional($lead->latestFollowup?->next_followup_date)->format('d M Y, H:i') ?? '',
            'salesperson_name' => (string) optional($lead->representative)->name,
        ];
    }
}
