<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;

class LeadAiCurrentFactsService
{
    private const ACTIVE_STATUS = 1;

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

        $latestFollowup =
            $this->latestFollowup(
                $lead
            );

        $isEmailSource =
            $this->isEmailSource(
                $lead
            );

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
                    ->where(
                        'paid_amount',
                        '>',
                        0
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

            'lead_status_code' =>
                optional($latestFollowup)->status !== null
                    ? (int) optional($latestFollowup)->status
                    : null,

            'lead_source' =>
                $isEmailSource
                    ? 'email'
                    : 'crm',

            'email_source_lead' =>
                $isEmailSource,

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

    public function isBookedClosed(
        Lead $lead
    ): bool {
        $facts = $this->build($lead);

        return (float) ($facts['approved_received_amount'] ?? 0) > 0;
    }

    public function hasActiveStatus(
        Lead $lead
    ): bool {
        $latestFollowup =
            $this->latestFollowup(
                $lead
            );

        return
            $latestFollowup
            &&
            (int) $latestFollowup->status
                === self::ACTIVE_STATUS;
    }

    public function isEmailSource(
        Lead $lead
    ): bool {
        if (!Schema::hasTable('email_lead_logs')) {
            return false;
        }

        return $lead
            ->emailLeadLogs()
            ->exists();
    }

    private function latestFollowup(
        Lead $lead
    ): ?LeadFollowup {
        if (
            $lead->relationLoaded('latestFollowup')
        ) {
            return $lead->latestFollowup;
        }

        return LeadFollowup::query()
            ->where(
                'lead_id',
                $lead->id
            )
            ->orderByDesc('created_at')
            ->first();
    }

    private function statusName(
        $status
    ): string {
        return self::STATUS_LABELS[
            (int) $status
        ] ?? 'Unknown';
    }
}
