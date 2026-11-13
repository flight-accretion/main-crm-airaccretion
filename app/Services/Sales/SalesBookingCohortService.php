<?php

namespace App\Services\Sales;

use App\Models\PaymentAuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the month in which a sale first became a booking.
 *
 * A lead belongs to the period of its first approved customer payment. Later
 * partial or repeat payments must not make the same booking appear again.
 */
class SalesBookingCohortService
{
    public function leadIdsForPeriod(
        Carbon $from,
        Carbon $to,
        ?array $representativeIds = null
    ): Collection {
        $from = $from->copy()->startOfDay();

        // Exclusive upper bound prevents missing payments
        // recorded during the final fractional second of a day.
        $toExclusive = $to->copy()->startOfDay()->addDay();

        if ($representativeIds !== null && empty($representativeIds)) {
            return collect();
        }
    $query = DB::table('payment_audit_trail as pat')
        ->join('lead_followups as lf', 'lf.id', '=', 'pat.lead_followup_id')
        ->join('leads as l', 'l.id', '=', 'lf.lead_id')
        ->where(
            'pat.payment_status',
            PaymentAuditTrail::STATUS_APPROVED
        )
        ->where('pat.paid_amount', '>', 0)
        ->whereNotNull('pat.paid_date');

        if ($representativeIds !== null) {
            $query->whereIn('l.representative_user_id', $representativeIds);
        }

        return $query
            ->groupBy('lf.lead_id')
            ->havingRaw('MIN(pat.paid_date) >= ?', [$from])
            ->havingRaw('MIN(pat.paid_date) < ?', [$toExclusive])
            ->pluck('lf.lead_id')
            ->values();
    }
}
