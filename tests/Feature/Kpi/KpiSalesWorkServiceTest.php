<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use App\Services\Kpi\KpiActivityRecorder;
use App\Services\Kpi\KpiDashboardDetailService;
use App\Services\Kpi\KpiSalesWorkService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KpiSalesWorkServiceTest extends KpiFeatureTestCase
{
    public function test_work_done_requires_note_and_status_change_for_same_user_lead_and_day(): void
    {
        $agent = $this->createUserWithRole(
            'Primary Sales Agent',
            UserType::SALES_EXECUTIVE
        );
        $otherAgent = $this->createUserWithRole(
            'Other Sales Agent',
            UserType::SALES_EXECUTIVE
        );

        $noteOnly = $this->createLeadFor($agent, 'Note Only');
        $this->insertKpiEvent($agent, $noteOnly, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 09:00:00', 'note-only');

        $statusOnly = $this->createLeadFor($agent, 'Status Only');
        $this->insertKpiEvent($agent, $statusOnly, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 09:05:00', 'status-only');

        $complete = $this->createLeadFor($agent, 'Complete Work');
        $this->insertKpiEvent($agent, $complete, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 10:00:00', 'complete-note');
        $this->insertKpiEvent($agent, $complete, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 10:10:00', 'complete-status');

        $splitUsers = $this->createLeadFor($agent, 'Split Users');
        $this->insertKpiEvent($agent, $splitUsers, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 11:00:00', 'split-note');
        $this->insertKpiEvent($otherAgent, $splitUsers, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 11:10:00', 'split-status');

        $splitDates = $this->createLeadFor($agent, 'Split Dates');
        $this->insertKpiEvent($agent, $splitDates, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 12:00:00', 'split-date-note');
        $this->insertKpiEvent($agent, $splitDates, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-16 12:10:00', 'split-date-status');

        $multiple = $this->createLeadFor($agent, 'Multiple Same Day');
        $this->insertKpiEvent($agent, $multiple, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 13:00:00', 'multi-note-1');
        $this->insertKpiEvent($agent, $multiple, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 13:05:00', 'multi-note-2');
        $this->insertKpiEvent($agent, $multiple, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 13:10:00', 'multi-status-1');
        $this->insertKpiEvent($agent, $multiple, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 13:15:00', 'multi-status-2');

        $summary = app(KpiSalesWorkService::class)->summary(
            [$agent->id],
            now()->startOfDay(),
            now()->endOfDay(),
            'all'
        );

        $this->assertSame(2, $summary['work_done']);
        $this->assertSame(6, $summary['activity_score']);
    }

    public function test_lead_source_filter_limits_sales_work_summary(): void
    {
        $agent = $this->createUserWithRole(
            'Source Filter Sales Agent',
            UserType::SALES_EXECUTIVE
        );

        $manualLead = $this->createLeadFor($agent, 'Manual Lead');
        $this->insertKpiEvent($agent, $manualLead, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 09:00:00', 'manual-note');
        $this->insertKpiEvent($agent, $manualLead, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 09:05:00', 'manual-status');

        $whatsAppLead = $this->createLeadFor($agent, 'WhatsApp Lead');
        DB::table('whatsapp_lead_integrations')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $whatsAppLead,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertKpiEvent($agent, $whatsAppLead, KpiActivityRecorder::EVENT_NOTE_ADDED, '2026-09-17 10:00:00', 'whatsapp-note');
        $this->insertKpiEvent($agent, $whatsAppLead, KpiActivityRecorder::EVENT_STATUS_CHANGED, '2026-09-17 10:05:00', 'whatsapp-status');

        $summary = app(KpiSalesWorkService::class)->summary(
            [$agent->id],
            now()->startOfDay(),
            now()->endOfDay(),
            'whatsapp'
        );

        $this->assertSame(1, $summary['work_done']);
        $this->assertSame(2, $summary['activity_score']);
        $this->assertSame(1, $summary['total_leads']);
    }

    public function test_sales_work_summary_uses_postgres_safe_lead_activity_join(): void
    {
        $queries = $this->pretendOnPostgres(function () {
            app(KpiSalesWorkService::class)->summary(
                [(string) Str::uuid()],
                now()->startOfDay(),
                now()->endOfDay(),
                'all'
            );
        });

        $sql = collect($queries)
            ->pluck('query')
            ->implode("\n");

        $this->assertStringContainsString(
            'CAST("l"."id" AS TEXT) = "e"."entity_id"',
            $sql
        );

        $this->assertStringNotContainsString(
            '"l"."id" = "e"."entity_id"',
            $sql
        );
    }

    public function test_sales_dashboard_details_use_postgres_safe_lead_activity_join(): void
    {
        $queries = $this->pretendOnPostgres(function () {
            $service = app(KpiDashboardDetailService::class);

            $service->sales(
                'conversations',
                [(string) Str::uuid()],
                now()->startOfDay(),
                now()->endOfDay(),
                'all'
            );

            $service->sales(
                'work_done',
                [(string) Str::uuid()],
                now()->startOfDay(),
                now()->endOfDay(),
                'all'
            );
        });

        $sql = collect($queries)
            ->pluck('query')
            ->implode("\n");

        $this->assertStringContainsString(
            'CAST("l"."id" AS TEXT) = "e"."entity_id"',
            $sql
        );

        $this->assertStringNotContainsString(
            '"l"."id" = "e"."entity_id"',
            $sql
        );
    }

    private function pretendOnPostgres(Closure $callback): array
    {
        $connection = 'kpi_pgsql_compile';

        config()->set('database.connections.' . $connection, [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'compile_only',
            'username' => 'compile_only',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        $original = DB::getDefaultConnection();
        DB::setDefaultConnection($connection);

        try {
            return DB::connection($connection)->pretend($callback);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge($connection);
        }
    }
}
