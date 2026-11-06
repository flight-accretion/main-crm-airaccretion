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
    ?Carbon $from = null,
    bool $includeOutreachLeads = false
): Builder {
    $periodStart = ($from ?: $asOf->copy()->startOfMonth())
        ->copy()
        ->startOfDay();

    $periodEnd = $asOf->copy()->endOfDay();

    $query = Lead::query()
        ->where('representative_user_id', $user->id)
        ->whereBetween('created_at', [
            $periodStart,
            $periodEnd,
        ]);

    if (!$includeOutreachLeads) {
        $query->whereNotIn(
            'id',
            KpiOutreachAssignment::query()
                ->whereNotNull('created_lead_id')
                ->select('created_lead_id')
        );
    }

    return $query;
}

}
