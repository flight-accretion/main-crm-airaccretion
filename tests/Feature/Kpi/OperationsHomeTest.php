<?php

namespace Tests\Feature\Kpi;

use App\Models\{OperationCase, UserType};
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class OperationsHomeTest extends KpiFeatureTestCase
{
    private $ops;
    private $other;

    protected function setUp(): void
    {
        parent::setUp();
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        Schema::table('operation_cases', fn ($t) => $t->timestamp('next_followup_at')->nullable());
        Schema::table('targets', function ($t) { $t->boolean('incentive_enabled')->default(false); $t->decimal('incentive_multiplier', 12, 8)->nullable(); $t->string('incentive_basis')->nullable(); $t->text('incentive_product_ids')->nullable(); });
        Schema::create('review_conversations', function ($t) { $t->uuid('id')->primary(); $t->uuid('operation_case_id'); $t->timestamp('initial_message_sent_at')->nullable(); $t->boolean('customer_replied')->default(false); });
        Schema::create('lead_rides', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->dateTime('from_date')->nullable(); $t->string('from_place')->nullable(); $t->string('to_place')->nullable(); $t->boolean('is_tba')->default(false); });
        Schema::create('vouchers', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); });
        Schema::create('vendor_refunds', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->integer('status')->nullable(); $t->decimal('refund_amount'); });
        Schema::create('operations_lead_assignments', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->uuid('operations_user_id'); $t->boolean('is_active'); $t->timestamp('assigned_at')->nullable(); });
        $this->ops = $this->createUserWithRole('Operations one', UserType::OPERATIONS_EXECUTIVE);
        $this->other = $this->createUserWithRole('Operations two', UserType::OPERATIONS_EXECUTIVE);
    }

    private function caseFor($user, int $days, array $extra = []): OperationCase
    {
        return OperationCase::create($extra + ['lead_id' => $this->createLeadFor($user, '<script>Customer</script>'), 'type' => 'review', 'status' => 'pending', 'assigned_to' => $user->id, 'opened_at' => now()->subDays($days)]);
    }

    public function test_dashboard_is_additive_and_personal_cases_are_scoped_without_writes(): void
    {
        $this->assertTrue(app('router')->getRoutes()->hasNamedRoute('admin.operations.dashboard'), 'New dashboard route is missing');
        $this->assertSame('/admin/operations', parse_url(route('admin.operations.index'), PHP_URL_PATH));
        $old = $this->caseFor($this->ops, 9, ['next_followup_at' => now()]);
        $this->caseFor($this->ops, 3);
        $this->caseFor($this->ops, 1, ['status' => 'completed', 'completed_at' => now()]);
        $this->caseFor($this->other, 20);
        $before = OperationCase::all()->toJson();
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertSame(2, $data['openCount']);
        $this->assertSame(1, $data['dueToday']);
        $this->assertSame($old->id, $data['oldest']->first()->id);
        $this->assertSame(['recent' => 0, 'week' => 1, 'older' => 1], $data['age']);
        $this->assertSame($before, OperationCase::all()->toJson());
        $due = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops, 'due');
        $this->assertSame(1, $due['caseRows']->total());
        $this->assertSame($old->id, $due['caseRows']->first()->id);
        $admin = $this->createUserWithRole('Admin home', UserType::SUPER_ADMIN);
        $this->assertSame(3, app(\App\Services\Operations\OperationsHomeService::class)->forUser($admin)['openCount']);
    }

    public function test_managers_see_team_work_but_keep_their_own_kpi_and_incentive(): void
    {
        $own = $this->caseFor($this->ops, 2, ['type' => 'vendor_followup', 'next_followup_at' => now()]);
        $this->caseFor($this->other, 9);
        $this->caseFor($this->other, 3, ['assigned_to' => null]);
        $this->caseFor($this->other, 1, ['status' => 'completed', 'completed_at' => now()]);
        DB::table('lead_rides')->insert(['id' => Str::uuid(), 'lead_id' => $own->lead_id, 'from_date' => now()->addHour()]);
        DB::table('vouchers')->insert(['id' => Str::uuid(), 'lead_id' => $own->lead_id]);
        DB::table('vendor_refunds')->insert(['id' => Str::uuid(), 'lead_id' => $own->lead_id, 'status' => 1, 'refund_amount' => 500]);
        $before = OperationCase::all()->toJson();

        foreach ([UserType::OPERATIONS_MANAGER, UserType::SENIOR_OPERATIONS_MANAGER] as $role) {
            $manager = $this->createUserWithRole('Dashboard '.$role, $role);
            $targetId = (string) Str::uuid();
            DB::table('targets')->insert(['id' => $targetId, 'sales_executive_id' => $manager->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 100000, 'status' => 'active', 'incentive_enabled' => true]);
            $kpi = \Mockery::mock(\App\Services\Kpi\KpiDashboardService::class);
            $kpi->shouldReceive('forUser')->twice()->withArgs(fn ($user, $asOf, $from) => $user->id === $manager->id)->andReturn(['overall_score' => 3.5]);
            app()->instance(\App\Services\Kpi\KpiDashboardService::class, $kpi);
            $calculator = \Mockery::mock(\App\Services\TargetIncentiveService::class);
            $calculator->shouldReceive('forTarget')->once()->with(\Mockery::on(fn ($target) => $target->id === $targetId))->andReturn(['incentive' => 125]);
            app()->instance(\App\Services\TargetIncentiveService::class, $calculator);

            $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($manager, 'due');
            $this->assertTrue($data['team']);
            $this->assertTrue($data['personalKpi']);
            $this->assertSame(3, $data['openCount']);
            $this->assertSame(1, $data['dueToday']);
            $this->assertSame(1, $data['caseRows']->total());
            $this->assertSame($own->id, $data['caseRows']->first()->id);
            $this->assertSame(1, $data['vendorCount']);
            $this->assertSame(1, $data['todayRides']);
            $this->assertSame($own->lead_id, $data['nextRide']->lead_id);
            $this->assertEquals(500, $data['vendorRefund']);
            $this->assertSame(['day' => 3.5, 'month' => 3.5], $data['kpi']);
            $this->assertSame(['status' => 'configured', 'amount' => 125.0], $data['incentive']);
            $this->actingAs($manager);
            view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
            $html = view('admin.pages.operations.home', $data)->render();
            $this->assertStringContainsString('Team workspace', $html);
            $this->assertStringContainsString('Existing KPI score', $html);
            $this->assertStringContainsString('125.00', $html);
            $this->assertStringNotContainsString('operations_user_id=', $html);
        }
        $this->assertSame($before, OperationCase::all()->toJson());
    }

    public function test_executive_with_no_assignment_does_not_inherit_team_records(): void
    {
        $this->caseFor($this->other, 1);
        $this->caseFor($this->other, 2, ['assigned_to' => null]);
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops, 'all');
        $this->assertFalse($data['team']);
        $this->assertTrue($data['personalKpi']);
        $this->assertSame(0, $data['openCount']);
        $this->assertSame(0, $data['caseRows']->total());
        $this->assertSame(0, $data['todayRides']);
        $this->assertNull($data['nextRide']);
    }

    public function test_money_totals_are_scoped_to_assigned_leads_and_samples_are_not_real_payouts(): void
    {
        $own = $this->caseFor($this->ops, 2);
        $outside = $this->caseFor($this->other, 2);
        foreach ([$own, $outside] as $index => $case) {
            DB::table('vendor_refunds')->insert(['id' => Str::uuid(), 'lead_id' => $case->lead_id, 'status' => 1, 'refund_amount' => $index ? 9000 : 500]);
        }
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertEquals(500, $data['vendorRefund']);
        $this->assertEquals(0, $data['customerRefund']);
        $this->assertNull($data['kpi']['day']);
    }

    public function test_rides_follow_assignment_and_cancelled_rides_are_excluded(): void
    {
        foreach ([$this->ops, $this->other] as $user) {
            $lead = $this->createLeadFor($user);
            DB::table('lead_rides')->insert(['id' => Str::uuid(), 'lead_id' => $lead, 'from_date' => now()->addHour(), 'from_place' => 'Mumbai', 'to_place' => 'Pune', 'is_tba' => false]);
            DB::table('vouchers')->insert(['id' => Str::uuid(), 'lead_id' => $lead]);
            DB::table('operations_lead_assignments')->insert(['id' => Str::uuid(), 'lead_id' => $lead, 'operations_user_id' => $user->id, 'is_active' => true, 'assigned_at' => now()]);
        }
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertSame(1, $data['todayRides']);
        $this->assertSame('Mumbai', $data['nextRide']->from_place);
        $ownLead = $data['nextRide']->lead_id;
        DB::table('lead_followups')->insert(['id' => Str::uuid(), 'lead_id' => $ownLead, 'status' => 2, 'created_at' => now()]);
        $this->assertSame(0, app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops)['todayRides']);
    }

    public function test_latest_status_controls_confirmation_and_partial_balance_not_historical_status(): void
    {
        $case = $this->caseFor($this->ops, 2);
        DB::table('lead_rides')->insert(['id' => Str::uuid(), 'lead_id' => $case->lead_id, 'from_date' => now()->addHour()]);
        DB::table('vouchers')->insert(['id' => Str::uuid(), 'lead_id' => $case->lead_id]);
        foreach ([[5, now()->subDays(2)], [4, now()->subDay()]] as [$status, $created]) {
            DB::table('lead_followups')->insert(['id' => Str::uuid(), 'lead_id' => $case->lead_id, 'status' => $status, 'total_amount' => 10000, 'received_amount' => 3000, 'created_at' => $created]);
        }
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertSame(1, $data['unconfirmed']);
        $this->assertSame(1, $data['partPaidCount']);
        $this->assertEquals(7000, $data['partPaidBalance']);
        DB::table('lead_followups')->insert(['id' => Str::uuid(), 'lead_id' => $case->lead_id, 'status' => 3, 'total_amount' => 10000, 'received_amount' => 10000, 'created_at' => now()]);
        $this->assertSame(0, app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops)['partPaidCount']);
    }

    public function test_expiry_uses_original_service_date_and_ignores_completed_or_other_users(): void
    {
        $tracking = ['reschedule' => ['state' => 'open', 'original' => ['from_date' => now()->subDays(85)->toDateString()]]];
        $this->caseFor($this->ops, 1, ['type' => 'reschedule', 'metadata' => $tracking]);
        $this->caseFor($this->other, 1, ['type' => 'reschedule', 'metadata' => $tracking]);
        $this->caseFor($this->ops, 1, ['type' => 'reschedule', 'metadata' => $tracking, 'status' => 'completed', 'completed_at' => now()]);
        $this->assertSame(1, app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops)['expiring']);
    }

    public function test_view_escapes_customer_data_and_marks_demo_sections(): void
    {
        $this->caseFor($this->ops, 9);
        $this->actingAs($this->ops);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $html = view('admin.pages.operations.home', $data)->render();
        $this->assertStringContainsString('Operations Dashboard', $html);
        $this->assertStringContainsString('&lt;script&gt;Customer&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>Customer</script>', $html);
        $this->assertStringNotContainsString('Sample', $html);
        $this->assertStringNotContainsString('8,500', $html);
        $this->assertStringNotContainsString('<aside class="oh-incentive">', $html);
        $this->assertStringNotContainsString('Preview: incentive eligible', $html);
        if ($dir = getenv('CRM_HOME_PREVIEW_DIR')) file_put_contents($dir.'/index.html', $html);
        OperationCase::query()->delete();
        $empty = view('admin.pages.operations.home', app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops))->render();
        $this->assertStringContainsString('No open cases', $empty);
        if ($dir = getenv('CRM_HOME_PREVIEW_DIR')) file_put_contents($dir.'/empty.html', $empty);
    }

    public function test_dashboard_role_gate_preserves_existing_permissions(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.operations.dashboard');
        $gate = collect($route->gatherMiddleware())->first(fn ($name) => str_starts_with($name, 'role:'));
        $this->assertNotNull($gate);
        foreach (array_merge(UserType::OPERATIONS_ROLES, UserType::ADMIN_ROLES, UserType::SALES_ROLES, UserType::ACCOUNTS_ROLES, [UserType::HR]) as $role) {
            $actor = $this->createUserWithRole('Home role '.$role, $role);
            $request = \Illuminate\Http\Request::create('/admin/operations/dashboard');
            $request->setUserResolver(fn () => $actor);
            $allowed = in_array($role, array_merge(UserType::OPERATIONS_ROLES, UserType::ADMIN_ROLES), true);
            try {
                $response = app(\App\Http\Middleware\RoleMiddleware::class)->handle($request, fn () => response('ok'), ...explode(',', substr($gate, 5)));
                $this->assertTrue($allowed, $role.' gained unauthorized access');
                $this->assertSame(200, $response->getStatusCode());
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                $this->assertFalse($allowed, $role.' lost dashboard access');
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }

    public function test_incentive_uses_only_own_current_active_target_and_existing_calculator(): void
    {
        $target = ['id' => (string) Str::uuid(), 'sales_executive_id' => $this->ops->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 100000, 'status' => 'active', 'incentive_enabled' => true, 'incentive_multiplier' => 0.01, 'incentive_basis' => 'company'];
        DB::table('targets')->insert($target);
        DB::table('targets')->insert(array_merge($target, ['id' => Str::uuid(), 'sales_executive_id' => $this->other->id]));
        $calculator = \Mockery::mock(\App\Services\TargetIncentiveService::class);
        $calculator->shouldReceive('forTarget')->once()->with(\Mockery::on(fn ($value) => $value->id === $target['id']))->andReturn(['incentive' => 1234.56]);
        app()->instance(\App\Services\TargetIncentiveService::class, $calculator);
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertSame('configured', $data['incentive']['status']);
        $this->assertSame(1234.56, $data['incentive']['amount']);
        $this->actingAs($this->ops);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $html = view('admin.pages.operations.home', $data)->render();
        $this->assertStringContainsString('1,234.56', $html);
        $this->assertStringNotContainsString('3 of 4 criteria', $html);
        if ($dir = getenv('CRM_HOME_PREVIEW_DIR')) file_put_contents($dir.'/incentive.html', $html);
        DB::table('targets')->insert(array_merge($target, ['id' => Str::uuid()]));
        $this->assertSame('duplicate', app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops)['incentive']['status']);
        $admin = $this->createUserWithRole('Incentive admin', UserType::SUPER_ADMIN);
        $this->assertSame('not_selected', app(\App\Services\Operations\OperationsHomeService::class)->forUser($admin)['incentive']['status']);
    }

    public function test_disabled_inactive_and_other_month_incentives_do_not_become_a_dashboard_estimate(): void
    {
        $base = ['sales_executive_id' => $this->ops->id, 'year' => now()->year, 'month' => now()->month, 'target_amount' => 100000, 'status' => 'active', 'incentive_enabled' => false];
        foreach ([[], ['month' => now()->month - 1, 'incentive_enabled' => true], ['status' => 'inactive', 'incentive_enabled' => true]] as $overrides) {
            DB::table('targets')->insert(array_merge($base, $overrides, ['id' => Str::uuid()]));
        }
        $calculator = \Mockery::mock(\App\Services\TargetIncentiveService::class);
        $calculator->shouldNotReceive('forTarget');
        app()->instance(\App\Services\TargetIncentiveService::class, $calculator);
        $data = app(\App\Services\Operations\OperationsHomeService::class)->forUser($this->ops);
        $this->assertSame('not_configured', $data['incentive']['status']);
        $this->assertNull($data['incentive']['amount']);
    }
}
