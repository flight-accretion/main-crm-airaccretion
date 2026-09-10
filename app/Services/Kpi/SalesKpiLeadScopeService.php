<?php

namespace App\Services\Kpi;

use App\Models\KpiOutreachAssignment;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SalesKpiLeadScopeService
{
    public function incoming(User $user, Carbon $asOf): Builder
    {
        $monthStart = $asOf->copy()->startOfMonth();

        $outreachLeadIds = KpiOutreachAssignment::query()
            ->whereNotNull('created_lead_id')
            ->pluck('created_lead_id');

        return Lead::query()
            ->where('representative_user_id', $user->id)
            ->whereBetween('created_at', [
                $monthStart,
                $asOf->copy()->endOfDay(),
            ])
            ->when(
                $outreachLeadIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $outreachLeadIds)
            );
    }
}
