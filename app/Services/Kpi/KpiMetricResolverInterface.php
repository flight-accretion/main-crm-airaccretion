<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use Carbon\Carbon;

interface KpiMetricResolverInterface
{
    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array;
}
