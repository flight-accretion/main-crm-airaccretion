<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;

class SkyrackLeadPayloadBuilder
{
    private const STATUS_LABELS = [
        0 => 'Initiated',
        1 => 'Active',
        2 => 'Cancelled',
        3 => 'Full Payment Received',
        4 => 'Partial Payment Received',
        5 => 'Confirmed',
        6 => 'Pending',
        7 => 'Rescheduled',
        8 => 'Approved',
        9 => 'Rejected',
    ];

    public function __construct(
        private CrmLeadCodeService $leadCodeService
    ) {
    }

    public function build(Lead $lead): array
    {
        $lead->loadMissing([
            'client',
            'representative',
            'rideSegments',
            'latestFollowup',
        ]);

        $crmLeadCode =
            $this->leadCodeService->ensureCode($lead);

        return [
            'lead_id' => (string) $lead->id,
            'crm_lead_code' => $crmLeadCode,
            'client_name' => (string) optional($lead->client)->name,
            'client_email' => optional($lead->client)->email,
            'client_phone' => $this->normalizePhone(
                optional($lead->client)->contact_number
            ),
            'Sales_Executive_name' => (string) optional($lead->representative)->name,
            'Sales_Executive_number' => $this->normalizePhone(
                optional($lead->representative)->contact_number
            ),
            'service_date' => $this->serviceDate($lead),
            'service_name' => $this->serviceName($lead),
            'lead_status' => $this->leadStatus($lead),
        ];
    }

    private function serviceDate(Lead $lead): ?string
    {
        $ride =
            $lead
                ->rideSegments
                ->filter(function ($ride) {
                    return !empty($ride->from_date);
                })
                ->sortBy('from_date')
                ->first();

        if (!$ride) {
            return null;
        }

        return Carbon::parse($ride->from_date)->format('Y-m-d');
    }

    private function serviceName(Lead $lead): ?string
    {
        $serviceIds =
            array_values(
                array_filter(
                    $lead->service_ids_array
                )
            );

        if (!empty($serviceIds)) {
            $services =
                Service::query()
                    ->whereIn('id', $serviceIds)
                    ->pluck('service')
                    ->filter()
                    ->values()
                    ->all();

            if (!empty($services)) {
                return implode(', ', $services);
            }
        }

        $productIds =
            array_values(
                array_filter(
                    $lead->product_ids_array
                )
            );

        if (empty($productIds)) {
            return null;
        }

        $products =
            Product::query()
                ->whereIn('id', $productIds)
                ->pluck('product')
                ->filter()
                ->values()
                ->all();

        return !empty($products)
            ? implode(', ', $products)
            : null;
    }

    private function leadStatus(Lead $lead): string
    {
        $followup =
            $lead->relationLoaded('latestFollowup')
                ? $lead->latestFollowup
                : $lead->latestFollowup()->first();

        if (!$followup) {
            return 'Active';
        }

        return self::STATUS_LABELS[(int) $followup->status]
            ?? (string) $followup->status;
    }

    private function normalizePhone(?string $phone): string
    {
        $digits =
            preg_replace(
                '/\D+/',
                '',
                (string) $phone
            );

        if (!$digits) {
            return '';
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return $digits;
    }
}
