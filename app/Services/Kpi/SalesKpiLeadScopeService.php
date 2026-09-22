<?php

namespace App\Services\Kpi;

use App\Models\KpiOutreachAssignment;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SalesKpiLeadScopeService
{
   public function incoming(
    User $user,
    Carbon $asOf,
    ?Carbon $from = null
): Builder {
    $from = ($from ?: $asOf->copy()->startOfMonth())
        ->copy()
        ->startOfDay();

    $to = $asOf->copy()->endOfDay();

    $outreachLeadIds = KpiOutreachAssignment::query()
        ->whereNotNull('created_lead_id')
        ->pluck('created_lead_id');

    return Lead::query()
        ->where('representative_user_id', $user->id)
        ->whereBetween('created_at', [
            $from,
            $to,
        ])
        ->when(
            $outreachLeadIds->isNotEmpty(),
            fn ($query) =>
                $query->whereNotIn(
                    'id',
                    $outreachLeadIds
                )
        );
}
}
