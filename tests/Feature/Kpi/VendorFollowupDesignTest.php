<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\OperationsVendorFollowupController;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\Lead;
use App\Models\UserType;
use App\Services\Operations\VendorFollowupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VendorFollowupDesignTest extends KpiFeatureTestCase
{
    private $ops;
    private $vendor;
    private $lead;
    private string $compiled;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('lead_rides', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->dateTime('from_date')->nullable(); $t->string('from_place')->nullable(); $t->string('to_place')->nullable(); });
        Schema::create('vendors', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('name'); $t->string('contact_number')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        (require base_path('database/migrations/2026_09_24_120000_add_operations_followup_fields.php'))->up();
        (require base_path('database/migrations/2026_10_03_160100_add_vendor_followup_fields_to_lead_followups_table.php'))->up();
        $this->ops = $this->createUserWithRole('Vendor Operations', UserType::OPERATIONS_EXECUTIVE);
        $this->lead = Lead::findOrFail($this->createLeadFor($this->ops, 'Vendor Client'));
        $this->vendor = \App\Models\Vendor::create(['id' => (string) Str::uuid(), 'name' => 'Vendor <script>name</script>', 'contact_number' => '9876543210', 'status' => 1]);
        $this->makeCase('2026-09-01', '2026-09-16');
        $this->makeCase('2026-09-15', '2026-09-17');
        $this->makeCase('2026-09-17', '2026-09-21');
        $this->makeCase('2026-09-10', null, 'confirmed', '2026-09-16');
        $this->makeCase('2026-09-09', null, 'cancelled', '2026-09-16');
        config(['session.driver' => 'array']);
        $this->compiled = sys_get_temp_dir().'/vendor-design-'.Str::uuid(); mkdir($this->compiled);
        config(['view.compiled' => $this->compiled]); app()->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views')); view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->compiled);
        parent::tearDown();
    }

    private function makeCase(string $opened, ?string $due, ?string $resolution = null, ?string $completed = null): OperationCase
    {
        $case = OperationCase::create(['lead_id' => $this->lead->id, 'type' => 'vendor_followup', 'assigned_to' => $this->ops->id,
            'opened_at' => $opened, 'next_followup_at' => $due, 'completed_at' => $completed, 'status' => $completed ? 'completed' : 'pending',
            'note' => 'Existing remark', 'metadata' => ['vendor_id' => $this->vendor->id, 'vendor_name_snapshot' => $this->vendor->name, 'purpose' => 'Confirm Ride Date & Time', 'resolution' => $resolution]]);
        OperationCaseActivity::create(['operation_case_id' => $case->id, 'lead_id' => $case->lead_id, 'user_id' => $this->ops->id, 'action' => 'vendor_followup_created', 'note' => 'Recorded activity']);
        return $case;
    }

    private function request(array $filters = [], $viewer = null): Request
    {
        $request = Request::create('/admin/operations/vendor-followups', 'GET', $filters);
        $viewer = $viewer ?: $this->ops; auth()->setUser($viewer); $request->setUserResolver(fn() => $viewer);
        $request->setLaravelSession(app('session.store')); app()->instance('request', $request); return $request;
    }

    public function test_counts_are_unique_and_completed_this_week_excludes_cancelled(): void
    {
        $data = app(OperationsVendorFollowupController::class)->index($this->request(['bucket' => 'all']))->getData();
        $this->assertSame(5, $data['counts']['all']); $this->assertSame(3, $data['counts']['open']);
        $this->assertSame(1, $data['counts']['completed']); $this->assertSame(1, $data['counts']['cancelled']);
        $this->assertSame(1, $data['counts']['completed_week']); $this->assertSame(1, $data['counts']['overdue']);
        $this->assertSame(1, $data['counts']['today']); $this->assertSame(1, $data['counts']['upcoming']);
    }

    public function test_list_renders_real_remarks_contacts_actions_and_read_only_roles(): void
    {
        $view = app(OperationsVendorFollowupController::class)->index($this->request()); $html = $view->render();
        foreach (['Remarks', '9876543210', 'Existing remark', 'Due today', 'Chase now'] as $label) $this->assertSame(true, str_contains($html, $label));
        $this->assertSame(false, str_contains($html, '<script>name</script>'));
        $this->assertSame(false, str_contains($html, 'Vendor stalling'));
        $hr = $this->createUserWithRole('Vendor HR', UserType::HR);
        $readOnly = app(OperationsVendorFollowupController::class)->index($this->request([], $hr))->render();
        $this->assertSame(false, str_contains($readOnly, 'Chase now')); $this->assertSame(true, str_contains($readOnly, '>View<'));
        if (getenv('CRM_VENDOR_PREVIEW_DIR')) file_put_contents(getenv('CRM_VENDOR_PREVIEW_DIR').'/list.html', $html);
    }

    public function test_search_vendor_and_age_filters_keep_counts_and_export_in_sync(): void
    {
        $controller = app(OperationsVendorFollowupController::class);
        $data = $controller->index($this->request(['bucket' => 'age_2_7', 'search' => 'vendor client', 'vendor_id' => $this->vendor->id]))->getData();
        $this->assertSame(1, $data['cases']->total()); $this->assertSame(5, $data['counts']['all']);
        $none = $controller->index($this->request(['search' => 'no match']))->getData(); $this->assertSame(0, $none['cases']->total());
        $export = $controller->export($this->request(['bucket' => 'today'])); ob_start(); $export->sendContent(); $csv = ob_get_clean();
        $this->assertSame(1, substr_count($csv, 'Vendor Client'));
    }

    public function test_create_preserves_existing_fields_and_shows_context_and_preview(): void
    {
        $this->request();
        $html = app(OperationsVendorFollowupController::class)->create($this->request(), $this->lead)->render();
        foreach (['vendor_id', 'assigned_to', 'purpose', 'tentative_ride_at', 'followup_date', 'remarks'] as $field) $this->assertSame(true, str_contains($html, 'name="'.$field.'"'));
        $this->assertSame(false, str_contains($html, 'name="priority"')); $this->assertSame(false, str_contains($html, 'name="next_action"'));
        $this->assertSame(true, str_contains($html, 'Vendor Client')); $this->assertSame(true, str_contains($html, 'data-vf-preview'));
        if (getenv('CRM_VENDOR_PREVIEW_DIR')) file_put_contents(getenv('CRM_VENDOR_PREVIEW_DIR').'/create.html', $html);
        $hr = $this->createUserWithRole('Create HR', UserType::HR);
        try { app(OperationsVendorFollowupController::class)->create($this->request([], $hr), $this->lead); $this->assertSame(true, false); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_operations_user_can_save_a_vendor_followup_and_write_compatible_lead_history(): void
    {
        $newVendor = \App\Models\Vendor::create([
            'id' => (string) Str::uuid(),
            'name' => 'New Vendor',
            'contact_number' => '9123456789',
            'status' => 1,
        ]);
        $request = Request::create('/admin/operations/vendor-followups/lead', 'POST', [
            'vendor_id' => $newVendor->id,
            'purpose' => 'Confirm availability',
            'followup_date' => now()->toDateString(),
            'remarks' => 'Call vendor before departure.',
        ]);
        auth()->setUser($this->ops);
        $request->setUserResolver(fn () => $this->ops);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        config(['logging.default' => 'null']);

        $response = app(OperationsVendorFollowupController::class)->store(
            $request,
            $this->lead,
            app(VendorFollowupService::class)
        );

        $case = OperationCase::query()
            ->where('type', OperationCase::TYPE_VENDOR_FOLLOWUP)
            ->where('metadata->vendor_id', $newVendor->id)
            ->firstOrFail();
        $history = \App\Models\LeadFollowup::query()->where('operation_case_id', $case->id)->firstOrFail();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('operations_vendor', $history->source);
        $this->assertSame('vendor_followup_note', $history->followup_type);
        $this->assertSame($this->ops->id, $case->assigned_to);
    }
}
