<?php

namespace App\Services\Kpi;

use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationsVoucherKpiEvidenceService
{
    public function vouchersForUser(
        User $user,
        Carbon $from,
        Carbon $to
    ): Collection {
        return Voucher::query()
            ->where('operation_team_user_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get([
                'id',
                'lead_id',
                'operation_team_user_id',
                'created_at',
                'customer_sent_at',
                'customer_sent_via',
            ]);
    }

    public function confirmedAtByLead(Collection $leadIds): Collection
    {
        $leadIds = $leadIds
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($leadIds->isEmpty()) {
            return collect();
        }

        return DB::table('payment_audit_trail as payment')
            ->join(
                'lead_followups as followup',
                'followup.id',
                '=',
                'payment.lead_followup_id'
            )
            ->where('payment.payment_status', 1)
            ->whereIn('followup.lead_id', $leadIds)
            ->selectRaw(
                'followup.lead_id, MIN(COALESCE(payment.updated_at, payment.created_at)) AS confirmed_at'
            )
            ->groupBy('followup.lead_id')
            ->pluck('confirmed_at', 'followup.lead_id');
    }
}