<?php

namespace Tests\Feature\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use App\Services\Kpi\SalesOutreachKpiResolver;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesOutreachKpiResolverTest extends TestCase
{
    public function test_zero_target_does_not_become_100_percent(): void
    {
        $user = new User();
        $user->id = (string) Str::uuid();

        $metric = new KpiMetric();

        $metric->code =
            'daily_outreach';

        $metric->target_value =
            0;

        $resolver =
            app(
                SalesOutreachKpiResolver::class
            );

        $result =
            $resolver->resolve(
                $user,
                $metric,
                Carbon::now(),
                22
            );

        $this->assertSame(
            0,
            (int) $result[
                'target_value'
            ]
        );

        $this->assertSame(
            0,
            (int) $result[
                'achievement_percent'
            ]
        );

        $this->assertSame(
            0,
            (int) $result[
                'evidence'
            ][
                'expected_to_date'
            ]
        );

        $this->assertArrayHasKey(
            'configuration_error',
            $result['evidence']
        );
    }
}