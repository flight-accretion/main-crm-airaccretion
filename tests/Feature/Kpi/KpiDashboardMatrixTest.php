<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use Illuminate\Support\Facades\DB;

class KpiDashboardMatrixTest extends KpiFeatureTestCase
{
    public function test_super_admin_can_sync_default_sales_kpis_and_view_all_sales_executives_in_matrix(): void
    {
        $admin = $this->createUserWithRole(
            'KPI Super Admin',
            UserType::SUPER_ADMIN
        );
        $firstSales = $this->createUserWithRole(
            'First Sales Executive',
            UserType::SALES_EXECUTIVE
        );
        $secondSales = $this->createUserWithRole(
            'Second Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $this->actingAs($admin)
            ->withSession(['_token' => 'kpi-test'])
            ->post('/admin/kpi-management/automation/sync', [
                '_token' => 'kpi-test',
                'department' => 'sales',
            ])
            ->assertRedirect();

        $salesTemplate = DB::table('kpi_templates')
            ->where('department', 'sales')
            ->where('name', 'Retail Sales KPI')
            ->first();

        $this->assertNotNull($salesTemplate);
        $this->assertSame(
            7,
            DB::table('kpi_metrics')
                ->where('template_id', $salesTemplate->id)
                ->where('active', true)
                ->count()
        );
        $this->assertSame(
            100.0,
            (float) DB::table('kpi_metrics')
                ->where('template_id', $salesTemplate->id)
                ->sum('weightage')
        );

        foreach ([$firstSales, $secondSales] as $salesUser) {
            $this->assertDatabaseHas('kpi_user_assignments', [
                'user_id' => $salesUser->id,
                'template_id' => $salesTemplate->id,
                'active' => true,
            ]);
        }

        $this->actingAs($admin)
               ->get('/admin/kpi?department=sales')
                ->assertOk()
                ->assertSee('Retail Department')
                ->assertSee('Scope of Work')
                ->assertSee('Weightage')
                ->assertSee('Daily Outreach')
                ->assertSee('Lead Conversion Rate')
                ->assertSee('Achieve Monthly Target')
                ->assertSee('Response Time')
                ->assertSee('Follow-Up')
                ->assertSee('Payment Collection')
                ->assertSee('Attendance')
                ->assertSee('Total Weightage')
                ->assertSee('First Sales Executive')
                ->assertSee('Second Sales Executive');
    }
}
