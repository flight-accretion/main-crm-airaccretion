<?php

namespace App\Services;

use App\Models\LeadFollowup;
use Illuminate\Validation\ValidationException;

class ExtraServiceQuoteCalculator
{
    /**
     * Replace extras on an itemized quote while preserving the other line prices.
     * A manual total or an unitemized historical extra cannot be split reliably.
     */
    public function replaceExtras(LeadFollowup $quote, array $extras): array
    {
        $lines = $quote->service_details_array;
        $base = [];
        $oldExtraIds = [];
        foreach ($lines as $line) {
            if (($line['type'] ?? null) === 'extra_service') {
                $oldExtraIds[] = (string) $line['id'];
            } else {
                $base[] = $line;
            }
        }

        $expectedExtraIds = $quote->extra_service_ids_array;
        sort($expectedExtraIds);
        sort($oldExtraIds);
        $gross = array_sum(array_column($lines, 'original_amount'));
        $discount = array_sum(array_column($lines, 'discount_amount'));

        if (!$lines || $oldExtraIds !== $expectedExtraIds
            || round($gross, 2) !== round((float) $quote->service_amount, 2)
            || round($discount, 2) !== round((float) $quote->discount_amount, 2)
            || round(max(0, $gross - $discount), 2) !== round((float) $quote->total_amount, 2)) {
            throw ValidationException::withMessages([
                'extra_services' => 'Extra services require an itemized current quote with a reconciled total.',
            ]);
        }

        $details = array_merge($base, $extras);
        $gross = round(array_sum(array_column($details, 'original_amount')), 2);
        $discount = round(array_sum(array_column($details, 'discount_amount')), 2);

        return [
            'extra_service_ids' => array_values(array_unique(array_map('strval', array_column($extras, 'id')))),
            'service_details' => $details,
            'service_amount' => $gross,
            'discount_amount' => $discount,
            'total_amount' => max(0, round($gross - $discount, 2)),
        ];
    }
}
