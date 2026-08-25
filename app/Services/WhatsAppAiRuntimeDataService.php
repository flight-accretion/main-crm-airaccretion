<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WhatsAppAiRuntimeDataService
{
    private const NOT_PROVIDED = 'Not provided by CRM';

    private const STATUS_LABELS = [
        0 => 'Initiated',
        1 => 'Active',
        2 => 'Cancelled',
        3 => 'Full Payment Received',
        4 => 'Partial Payment Received',
        5 => 'Confirm/Complete',
        6 => 'Pending',
        7 => 'Reschedule',
        8 => 'Approved',
        9 => 'Rejected',
    ];

    public function __construct(
        private WhatsAppAiPricingSheetService $pricingSheet
    ) {
    }

    public function build(
        WhatsAppConversation $conversation,
        Collection $messages,
        Collection $products,
        ?Collection $contextMessages = null
    ): array {
        $currentIst = now('Asia/Kolkata');
        $contact = $this->loadedRelation($conversation, 'contact');
        $lead = $this->loadedRelation($conversation, 'lead');
        $assignedUser =
            $this->loadedRelation($conversation, 'assignedUser')
            ?: $this->loadedRelation($lead, 'representative');
        $client = $this->loadedRelation($lead, 'client');
        $followup = $this->latestFollowup($lead);
        $ride = $this->latestRide($lead);
        $customerName = $this->firstNonEmpty([
            optional($contact)->name,
            optional($client)->name,
        ]);
        $customerNumber = $this->firstNonEmpty([
            optional($contact)->normalized_phone,
            optional($contact)->raw_phone,
            optional($client)->contact_number,
        ]);
        $previousService = $this->previousService($lead, $products);
        $cityOrRoute = $this->cityOrRoute($ride);
        $lastBookingDate = $this->lastBookingDate(
            $lead,
            $followup,
            $ride
        );
        $leadState = $this->leadState(
            $customerName,
            $customerNumber,
            $previousService,
            $cityOrRoute,
            $lastBookingDate,
            $lead,
            $followup
        );

        $runtime = [
            'CRM_CURRENT_DATETIME_IST' =>
                $currentIst->format('d-M-Y h:i A') . ' IST',
            'CRM_CURRENT_DATE_IST' =>
                $currentIst->format('d-M-Y'),
            'CRM_CURRENT_TIME_IST' =>
                $currentIst->format('h:i A') . ' IST',
            'CRM_CUSTOMER_NUMBER' =>
                $customerNumber ?: self::NOT_PROVIDED,
            'CRM_CUSTOMER_NAME' =>
                $customerName ?: self::NOT_PROVIDED,
            'CRM_LEAD_STATUS' =>
                $this->leadStatus($followup),
            'CRM_PREVIOUS_SERVICE' =>
                $previousService ?: self::NOT_PROVIDED,
            'CRM_LAST_BOOKING_DATE' =>
                $lastBookingDate ?: self::NOT_PROVIDED,
            'CRM_LEAD_STATE' =>
                json_encode($leadState, JSON_UNESCAPED_SLASHES),
            'CRM_MISSING_FIELDS' =>
                $this->missingFields($leadState),
            'CRM_NOTES' =>
                $this->notes($lead, $followup),
            'CRM_ASSIGNED_AGENT_NAME' =>
                optional($assignedUser)->name ?: self::NOT_PROVIDED,
            'CRM_ASSIGNED_AGENT_NUMBER' =>
                $this->agentNumber($assignedUser),
            'CRM_ACTIVE_PRODUCTS' =>
                $this->activeProducts($products),
            'CRM_SERVICE_DATA' =>
                $this->serviceData($products),
            'CRM_SERVICE_LOCATIONS' =>
                $this->serviceLocations($cityOrRoute),
            'CRM_PRICING_DATA' =>
                $this->pricingSheet->pricingData(),
            'CRM_AVAILABILITY_DATA' =>
                $this->configuredString('availability_data'),
            'CRM_PRODUCT_LINK' =>
                $this->productLink($previousService),
            'CRM_APPROVED_SELLING_FACTS' =>
                $this->configuredList('approved_selling_facts'),
            'CRM_CONVERSATION_HISTORY' =>
                $this->messageLines($contextMessages ?: collect()),
            'CRM_CURRENT_CUSTOMER_MESSAGE' =>
                $this->currentCustomerMessage($messages),
        ];

        return array_map(
            fn ($value) => trim((string) $value) !== ''
                ? (string) $value
                : self::NOT_PROVIDED,
            $runtime
        );
    }

    private function loadedRelation($model, string $relation)
    {
        if (!$model || !method_exists($model, 'relationLoaded')) {
            return null;
        }

        return $model->relationLoaded($relation)
            ? $model->getRelation($relation)
            : null;
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function latestFollowup(?Lead $lead): ?LeadFollowup
    {
        if (!$lead) {
            return null;
        }

        $followups = $this->loadedRelation($lead, 'leadFollowups');

        if ($followups instanceof Collection && $followups->isNotEmpty()) {
            return $followups
                ->sortByDesc(fn ($followup) =>
                    $this->dateSortValue(
                        $followup->created_at
                            ?: $followup->next_followup_date
                    )
                )
                ->first();
        }

        if (!$lead->exists) {
            return null;
        }

        try {
            return $lead->leadFollowups()
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function latestRide(?Lead $lead): ?LeadRide
    {
        if (!$lead) {
            return null;
        }

        $rides = $this->loadedRelation($lead, 'rideSegments');

        if ($rides instanceof Collection && $rides->isNotEmpty()) {
            return $rides
                ->sortByDesc(fn ($ride) =>
                    $this->dateSortValue($ride->from_date)
                )
                ->first();
        }

        if (!$lead->exists) {
            return null;
        }

        try {
            return $lead->rideSegments()
                ->orderByDesc('from_date')
                ->first();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function previousService(
        ?Lead $lead,
        Collection $products
    ): ?string {
        if (!$lead) {
            return null;
        }

        $ids = $lead->product_ids_array;

        if (empty($ids)) {
            return null;
        }

        $names = $products
            ->filter(fn ($product) => in_array(
                (string) $product->id,
                array_map('strval', $ids),
                true
            ))
            ->pluck('product')
            ->filter()
            ->values();

        return $names->isNotEmpty()
            ? $names->implode(', ')
            : null;
    }

    private function cityOrRoute(?LeadRide $ride): ?string
    {
        if (!$ride) {
            return null;
        }

        $from = trim((string) $ride->from_place);
        $to = trim((string) $ride->to_place);

        if ($from !== '' && $to !== '') {
            return $from . ' to ' . $to;
        }

        return $from !== '' ? $from : ($to !== '' ? $to : null);
    }

    private function lastBookingDate(
        ?Lead $lead,
        ?LeadFollowup $followup,
        ?LeadRide $ride
    ): ?string {
        foreach ([
            optional($ride)->from_date,
            optional($followup)->paid_date,
            optional($followup)->next_followup_date,
            optional($followup)->created_at,
            optional($lead)->created_at,
        ] as $date) {
            $formatted = $this->formatDate($date);

            if ($formatted) {
                return $formatted;
            }
        }

        return null;
    }

    private function formatDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return ($date instanceof Carbon ? $date : Carbon::parse($date))
                ->setTimezone('Asia/Kolkata')
                ->format('d-M-Y');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function dateSortValue($date): int
    {
        if (!$date) {
            return 0;
        }

        try {
            return ($date instanceof Carbon ? $date : Carbon::parse($date))
                ->timestamp;
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function leadStatus(?LeadFollowup $followup): string
    {
        if (!$followup) {
            return self::NOT_PROVIDED;
        }

        return self::STATUS_LABELS[(int) $followup->status]
            ?? 'Unknown';
    }

    private function leadState(
        ?string $customerName,
        ?string $customerNumber,
        ?string $previousService,
        ?string $cityOrRoute,
        ?string $lastBookingDate,
        ?Lead $lead,
        ?LeadFollowup $followup
    ): array {
        return [
            'customer_name' => $customerName,
            'phone' => $customerNumber,
            'product' => $previousService,
            'city_or_route' => $cityOrRoute,
            'date' => $lastBookingDate,
            'guests' => $lead ? $lead->number_of_passengers : null,
            'occasion' => $lead ? $lead->occasion : null,
            'lead_status' => $this->leadStatus($followup),
        ];
    }

    private function missingFields(array $leadState): string
    {
        $required = [
            'customer_name',
            'product',
            'city_or_route',
            'date',
            'guests',
        ];

        $missing = [];

        foreach ($required as $field) {
            if (
                !isset($leadState[$field])
                || trim((string) $leadState[$field]) === ''
            ) {
                $missing[] = $field;
            }
        }

        return empty($missing) ? 'none' : implode(', ', $missing);
    }

    private function notes(
        ?Lead $lead,
        ?LeadFollowup $followup
    ): string {
        $notes = array_filter([
            trim((string) optional($lead)->description),
            trim((string) optional($followup)->followup_note),
        ]);

        return empty($notes)
            ? self::NOT_PROVIDED
            : implode(' | ', $notes);
    }

    private function agentNumber(?User $user): string
    {
        return trim((string) optional($user)->contact_number)
            ?: self::NOT_PROVIDED;
    }

    private function activeProducts(Collection $products): string
    {
        $names = $products
            ->pluck('product')
            ->filter()
            ->values();

        return $names->isEmpty()
            ? self::NOT_PROVIDED
            : $names->implode(PHP_EOL);
    }

    private function serviceData(Collection $products): string
    {
        if ($products->isEmpty()) {
            return self::NOT_PROVIDED;
        }

        return $products
            ->map(function (Product $product) {
                $parts = [
                    $product->product,
                ];

                if (trim((string) $product->id) !== '') {
                    $parts[] = 'id: ' . $product->id;
                }

                if ((bool) $product->is_private) {
                    $parts[] = 'private';
                }

                if ((bool) $product->is_airambulance) {
                    $parts[] = 'medical';
                }

                return implode(' | ', array_filter($parts));
            })
            ->filter()
            ->values()
            ->implode(PHP_EOL);
    }

    private function serviceLocations(?string $cityOrRoute): string
    {
        return $cityOrRoute ?: self::NOT_PROVIDED;
    }

    private function configuredString(string $key): string
    {
        $value = config('whatcrm.' . $key);

        if (is_array($value)) {
            return empty($value)
                ? self::NOT_PROVIDED
                : json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value) !== ''
            ? trim((string) $value)
            : self::NOT_PROVIDED;
    }

    private function configuredList(string $key): string
    {
        $value = config('whatcrm.' . $key, []);

        if (is_array($value)) {
            return empty($value)
                ? self::NOT_PROVIDED
                : implode(PHP_EOL, array_filter($value));
        }

        return trim((string) $value) !== ''
            ? trim((string) $value)
            : self::NOT_PROVIDED;
    }

    private function productLink(?string $previousService): string
    {
        if (!$previousService) {
            return self::NOT_PROVIDED;
        }

        $links = config('whatcrm.product_links', []);

        if (!is_array($links) || empty($links)) {
            return self::NOT_PROVIDED;
        }

        return trim((string) ($links[$previousService] ?? ''))
            ?: self::NOT_PROVIDED;
    }

    private function messageLines(Collection $messages): string
    {
        if ($messages->isEmpty()) {
            return self::NOT_PROVIDED;
        }

        return $messages
            ->map(function (WhatsAppMessage $message) {
                $date = $message->message_at
                    ? $message->message_at
                        ->setTimezone('Asia/Kolkata')
                        ->format('d-M-Y h:i A')
                    : '-';

                return sprintf(
                    '[%s] %s: %s',
                    $date,
                    strtoupper((string) $message->direction),
                    trim((string) $message->body) !== ''
                        ? trim((string) $message->body)
                        : '[' . ($message->message_type ?: 'message') . ']'
                );
            })
            ->implode(PHP_EOL);
    }

    private function currentCustomerMessage(Collection $messages): string
    {
        if ($messages->isEmpty()) {
            return self::NOT_PROVIDED;
        }

        return $messages
            ->map(fn ($message) => trim((string) $message->body))
            ->filter()
            ->values()
            ->implode(PHP_EOL) ?: self::NOT_PROVIDED;
    }
}
