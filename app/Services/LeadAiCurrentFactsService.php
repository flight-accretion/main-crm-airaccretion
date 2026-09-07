<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Product;
use App\Models\Service;

class LeadAiCurrentFactsService
{
    public function build(
        Lead $lead
    ): array {
        $productNames = [];

        if (!empty($lead->product_ids_array)) {
            $productNames = Product::query()
                ->whereIn(
                    'id',
                    $lead->product_ids_array
                )
                ->pluck('product')
                ->filter()
                ->values()
                ->all();
        }

        $serviceNames = [];

        if (!empty($lead->service_ids_array)) {
            $serviceNames = Service::query()
                ->whereIn(
                    'id',
                    $lead->service_ids_array
                )
                ->pluck('service')
                ->filter()
                ->values()
                ->all();
        }

        $latestFollowup = LeadFollowup::query()
            ->where(
                'lead_id',
                $lead->id
            )
            ->orderByDesc('created_at')
            ->first();

        $amountFollowup = LeadFollowup::query()
            ->where(
                'lead_id',
                $lead->id
            )
            ->whereNotNull('total_amount')
            ->orderByDesc('created_at')
            ->first();

        $totalAmount =
            (float) (
                optional($amountFollowup)
                    ->total_amount
                ?? 0
            );

        $followupIds = LeadFollowup::query()
            ->where(
                'lead_id',
                $lead->id
            )
            ->pluck('id');

        $approvedReceived = 0;

        if ($followupIds->isNotEmpty()) {
            $approvedReceived =
                (float) PaymentAuditTrail::query()
                    ->whereIn(
                        'lead_followup_id',
                        $followupIds
                    )
                    ->where(
                        'payment_status',
                        1
                    )
                    ->sum('paid_amount');
        }

        $ride = $lead
            ->rideSegments()
            ->orderBy('from_date')
            ->first();

        $route = null;

        if ($ride) {
            $from = trim(
                (string) $ride->from_place
            );

            $to = trim(
                (string) $ride->to_place
            );

            if ($from || $to) {
                $route = trim(
                    $from
                    . ($from && $to ? ' to ' : '')
                    . $to
                );
            }
        }

        return [
            'products' =>
                $productNames,

            'services' =>
                $serviceNames,

            'lead_status' =>
                $this->statusName(
                    optional($latestFollowup)
                        ->status
                ),

            'passenger_count' =>
                $lead->number_of_passengers,

            'occasion' =>
                $lead->occasion,

            'total_amount' =>
                $totalAmount,

            'approved_received_amount' =>
                $approvedReceived,

            'pending_amount' =>
                max(
                    0,
                    $totalAmount
                    - $approvedReceived
                ),

            'service_date' =>
                $ride && $ride->from_date
                    ? (string) $ride->from_date
                    : null,

            'route' =>
                $route,
        ];
    }

    private function statusName(
        $status
    ): string {
        $statuses = [
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

        return $statuses[
            (int) $status
        ] ?? 'Unknown';
    }
}