<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\RideController;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UpcomingRidesDesignTest extends KpiFeatureTestCase
{
    private $actor;
    private string $leadId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('lead_id'); $table->dateTime('from_date'); $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable(); $table->string('to_place')->nullable(); $table->timestamps();
        });
        Schema::create('vouchers', function (Blueprint $table) {$table->uuid('id')->primary(); $table->uuid('lead_id'); $table->timestamps();});
        Schema::create('lead_payments', function (Blueprint $table) {$table->uuid('id')->primary(); $table->uuid('voucher_id'); $table->timestamps();});
        Schema::create('lead_payment_details', function (Blueprint $table) {$table->uuid('id')->primary(); $table->uuid('lead_payment_id'); $table->uuid('service_id')->nullable(); $table->boolean('is_extra_service')->default(false);});
        Schema::create('lead_vendor_payments', function (Blueprint $table) {$table->uuid('id')->primary(); $table->uuid('voucher_id'); $table->uuid('lead_id'); $table->uuid('vendor_id')->nullable(); $table->timestamps();});
        Schema::create('lead_vendor_payment_details', function (Blueprint $table) {$table->uuid('id')->primary(); $table->uuid('lead_vendor_payment_id'); $table->uuid('service_id')->nullable(); $table->boolean('is_extra_service')->default(false);});
        Schema::create('products', function (Blueprint $table) {$table->uuid('id')->primary(); $table->string('product'); $table->integer('status')->default(1);});
        Schema::create('services', function (Blueprint $table) {$table->uuid('id')->primary(); $table->string('service'); $table->boolean('is_extra_service')->default(false);});
        Schema::create('extra_services', function (Blueprint $table) {$table->uuid('id')->primary(); $table->string('extra_service'); $table->boolean('is_extra_service')->default(true);});
        Schema::table('lead_followups', function (Blueprint $table) {$table->text('service_ids')->nullable(); $table->text('extra_service_ids')->nullable();});
        $this->actor = $this->createUserWithRole('Upcoming Sales', UserType::SALES_EXECUTIVE);
        $this->leadId = $this->createLeadFor($this->actor, 'Upcoming Customer');
        $followup = (string) Str::uuid();
        DB::table('lead_followups')->insert(['id' => $followup, 'lead_id' => $this->leadId, 'status' => 3, 'total_amount' => 2000, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_audit_trail')->insert(['id' => (string) Str::uuid(), 'lead_followup_id' => $followup, 'payment_status' => 1, 'paid_amount' => 1000]);
        DB::table('vouchers')->insert(['id' => (string) Str::uuid(), 'lead_id' => $this->leadId]);
        DB::table('lead_rides')->insert(['id' => (string) Str::uuid(), 'lead_id' => $this->leadId, 'from_date' => now()->addDay()->setTime(12, 0), 'to_date' => now()->addDay()->setTime(13, 0), 'from_place' => 'Mumbai', 'to_place' => 'Pune']);
        $this->actingAs($this->actor);
        config(['session.driver' => 'array']);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    private function request(array $filters = []): Request
    {
        $request = Request::create('/admin/rides/upcoming-ride', 'GET', $filters);
        $request->setLaravelSession(app('session.store')); $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);
        return $request;
    }

    public function test_real_controller_preserves_payment_balance_and_renders_both_views(): void
    {
        $view = app(RideController::class)->upcomingRides($this->request());
        $data = $view->getData();
        $this->assertSame(1, $data['schedule']['summary']['total']);
        $this->assertSame(1, $data['schedule']['summary']['payment_pending']);
        $this->assertSame(1000.0, (float) $data['schedule']['rows'][0]['pending_amount_value']);
        $this->assertSame('Full Payment Received', $data['schedule']['rows'][0]['status_name']);
        $this->assertSame(now()->addDays(13)->toDateString(), $data['currentFilters']['to_date']);
        $html = $view->render();
        foreach (['ur-list-tab', 'ur-calendar-tab', 'ur-calendar', 'ur-day-panel', 'ur-details-dialog', 'Upcoming Customer', 'Partially paid'] as $text) $this->assertStringContainsString($text, $html);
        if ($dir = getenv('CRM_UPCOMING_PREVIEW_DIR')) file_put_contents($dir.'/index.html', $html);
    }

    public function test_unassigned_filter_and_date_filter_work_for_both_views(): void
    {
        $data = app(RideController::class)->upcomingRides($this->request(['view' => 'calendar', 'representative_user_id' => 'unassigned']))->getData();
        $this->assertSame(0, $data['schedule']['summary']['total']);
        DB::table('leads')->where('id', $this->leadId)->update(['representative_user_id' => null]);
        $data = app(RideController::class)->upcomingRides($this->request(['view' => 'calendar', 'representative_user_id' => 'unassigned']))->getData();
        $this->assertSame(1, $data['schedule']['summary']['total']);
        $data = app(RideController::class)->upcomingRides($this->request(['from_date' => now()->addDays(3)->toDateString(), 'to_date' => now()->addDays(4)->toDateString()]))->getData();
        $this->assertSame(0, $data['schedule']['summary']['total']);
    }

    public function test_csv_exports_filtered_rows_without_changing_status_or_payment_records(): void
    {
        $response = app(RideController::class)->upcomingRides($this->request(['download' => 'csv']));
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $this->assertStringContainsString('Upcoming Customer', $csv);
        $this->assertStringContainsString('Full Payment Received', $csv);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
        $this->assertSame(3, (int) DB::table('lead_followups')->value('status'));
    }

    public function test_to_date_alone_remains_supported(): void
    {
        $data = app(RideController::class)->upcomingRides($this->request(['to_date' => now()->addDays(3)->toDateString()]))->getData();
        $this->assertSame(1, $data['schedule']['summary']['total']);
    }

    public function test_export_does_not_allow_customer_text_to_become_a_spreadsheet_formula(): void
    {
        DB::table('clients')->update(['name' => '=1+1']);
        $response = app(RideController::class)->upcomingRides($this->request(['download' => 'csv']));
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $this->assertStringContainsString("'=1+1", $csv);
    }

    public function test_invalid_dates_fail_validation_without_writes(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(RideController::class)->upcomingRides($this->request(['from_date' => 'not-a-date']));
        } finally {
            $this->assertSame(1, DB::table('lead_followups')->count());
            $this->assertSame(1, DB::table('payment_audit_trail')->count());
        }
    }
}
