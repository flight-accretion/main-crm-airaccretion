<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\OperationsRescheduleController;
use App\Models\Lead;
use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\UserType;
use App\Services\Operations\OperationCaseService;
use App\Services\Operations\RescheduleTrackingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OperationsRescheduleTest extends KpiFeatureTestCase
{
    private $ops;
    private $ride;
    private $case;

    protected function setUp(): void
    {
        parent::setUp();
        config(['operations.reschedule_window_automation' => true, 'operations.booking_conflict_checks' => true]);
        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('lead_id');
            $table->dateTime('from_date')->nullable(); $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable(); $table->string('to_place')->nullable();
            $table->decimal('total_time')->nullable(); $table->boolean('is_tba')->default(false); $table->timestamps();
        });
        (require base_path('database/migrations/2026_10_17_190000_add_booking_resources_for_reschedule_conflicts.php'))->up();
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        (require base_path('database/migrations/2026_09_24_120000_add_operations_followup_fields.php'))->up();
        $this->ops = $this->createUserWithRole('Reschedule Operations', UserType::OPERATIONS_EXECUTIVE);
        $lead = Lead::findOrFail($this->createLeadFor($this->ops, 'Reschedule Customer'));
        $this->ride = LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'from_date' => '2026-06-25 10:00:00', 'to_date' => '2026-06-25 12:00:00', 'from_place' => 'Mumbai', 'to_place' => 'Pune', 'total_time' => 2]);
        $this->case = app(OperationCaseService::class)->open($lead, 'reschedule', ['ride_id' => $this->ride->id], $this->ops->id);
        config(['session.driver' => 'array']); auth()->setUser($this->ops);
    }

    private function request(array $data = [], string $method = 'GET'): Request
    {
        $request = Request::create('/admin/operations/reschedules', $method, $data);
        $request->setLaravelSession(app('session.store')); $request->setUserResolver(fn() => $this->ops);
        app()->instance('request', $request); return $request;
    }

    public function test_no_date_remains_open_and_preserves_original_and_audit(): void
    {
        $service = app(RescheduleTrackingService::class);
        $service->save($this->case, $this->ride, $this->ops, ['no_date' => 1, 'reason' => 'weather']);
        $case = $this->case->fresh(); $ride = $this->ride->fresh();
        $this->assertSame(null, $case->completed_at); $this->assertSame('in_progress', $case->status);
        $this->assertSame(null, $ride->from_date); $this->assertSame('2026-06-25 10:00:00', data_get($case->metadata, 'reschedule.original.from_date'));
        $this->assertSame('2026-09-23', data_get($case->metadata, 'reschedule.expires_on'));
        $this->assertSame('2026-06-25 10:00:00', data_get($case->activities()->latest()->first()->metadata, 'before.from_date'));
        $service->save($case, $ride, $this->ops, ['no_date' => 1, 'note' => 'Still waiting']);
        $this->assertSame('2026-09-23', data_get($case->fresh()->metadata, 'reschedule.expires_on'));
        $this->assertSame(3, $case->activities()->count());
    }

    public function test_fixed_date_closes_task_and_repeated_reschedule_keeps_first_date(): void
    {
        $service = app(RescheduleTrackingService::class);
        $service->save($this->case, $this->ride, $this->ops, ['no_date' => 0, 'from_date' => '2026-10-20 10:00', 'to_date' => '2026-10-20 12:00']);
        $this->assertSame('completed', $this->case->fresh()->status);
        $next = app(OperationCaseService::class)->open($this->ride->enquiry, 'reschedule', ['ride_id' => $this->ride->id], $this->ops->id);
        $service->save($next, $this->ride, $this->ops, ['no_date' => 1]);
        $this->assertSame('2026-06-25 10:00:00', data_get($next->fresh()->metadata, 'reschedule.original.from_date'));
        $this->assertSame('2026-09-23', data_get($next->fresh()->metadata, 'reschedule.expires_on'));
    }

    public function test_counts_filters_and_missing_original_dates_are_honest(): void
    {
        app(RescheduleTrackingService::class)->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        $controller = app(OperationsRescheduleController::class);
        $view = $controller->index($this->request(['segment' => 'open'])); $data = $view->getData();
        $this->assertSame(1, $data['rescheduleSummary']['open']); $this->assertSame(1, $data['rescheduleSummary']['expiring']);
        $this->assertSame(1, count($data['rescheduleRows'])); $this->assertSame(6, $data['rescheduleRows'][0]['days_left']);
        $filtered = $controller->index($this->request(['segment' => 'fixed']))->getData();
        $this->assertSame(0, count($filtered['rescheduleRows']));
        $this->assertSame(1, $filtered['rescheduleSummary']['open']);
        $this->assertSame(0, $controller->index($this->request(['search' => 'No such person']))->getData()['rescheduleSummary']['open']);
        $unknown = app(RescheduleTrackingService::class)->window(null);
        $this->assertSame(null, $unknown['days_left']);
        $this->assertSame(-1, app(RescheduleTrackingService::class)->window('2026-06-18')['days_left']);
    }

    public function test_fixed_requires_date_and_open_ignores_disabled_dates(): void
    {
        try {
            app(OperationsRescheduleController::class)->update($this->request(['no_date' => 0], 'POST'), $this->ride, app(OperationCaseService::class));
            $this->assertSame(true, false);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame(true, array_key_exists('from_date', $e->errors()));
        }
        app(OperationsRescheduleController::class)->update($this->request(['no_date' => 1, 'from_date' => 'bad-date', 'to_date' => 'bad-date'], 'POST'), $this->ride, app(OperationCaseService::class));
        $this->assertSame(null, $this->ride->fresh()->from_date);
        $this->assertSame(null, $this->case->fresh()->completed_at);
    }

    public function test_missing_ride_reference_never_edits_another_trip(): void
    {
        $this->case->update(['metadata' => ['ride_id' => (string) Str::uuid()]]);
        try {
            app(OperationsRescheduleController::class)->edit($this->ride);
            $this->assertSame(true, false);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $rows = app(OperationsRescheduleController::class)->index($this->request())->getData()['rescheduleRows'];
        $this->assertSame(1, count($rows)); $this->assertSame(null, $rows[0]['id']);
    }

    public function test_list_and_editor_render_for_mobile_and_desktop(): void
    {
        app(RescheduleTrackingService::class)->save($this->case, $this->ride, $this->ops, ['no_date' => 1, 'reason' => 'weather']);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $dir = getenv('CRM_RESCHEDULE_PREVIEW_DIR') ?: sys_get_temp_dir().'/reschedule-tests';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $compiled = $dir.'/compiled'; if (!is_dir($compiled)) mkdir($compiled);
        config(['view.compiled' => $compiled]); app()->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
        $controller = app(OperationsRescheduleController::class); $this->request();
        $list = $controller->index($this->request())->render(); $editor = $controller->edit($this->ride->fresh())->render();
        $this->assertSame(true, str_contains($list, 'Awaiting date')); $this->assertSame(true, str_contains($list, 'view-ride-btn'));
        $this->assertSame(true, str_contains($list, 'id="reschedule-cancel-dialog"'));
        $this->assertSame(true, str_contains($list, 'data-reschedule-cancel'));
        $this->assertSame(false, str_contains($list, "confirm('Cancel this ride?')"));
        $this->assertSame(true, str_contains($list, 'Last activity:'));
        $this->assertSame(true, str_contains($editor, 'Change history')); $this->assertSame(true, str_contains($editor, '2026-06-25'));
        file_put_contents($dir.'/list.html', $list); file_put_contents($dir.'/editor.html', $editor);
    }

    public function test_export_respects_filters_and_does_not_include_other_states(): void
    {
        app(RescheduleTrackingService::class)->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        $response = app(OperationsRescheduleController::class)->index($this->request(['download' => 'csv', 'segment' => 'open']));
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $this->assertSame(true, str_contains($csv, 'Reschedule Customer'));
        $this->assertSame(true, str_contains($csv, '2026-09-23'));
        $empty = app(OperationsRescheduleController::class)->index($this->request(['download' => 'csv', 'segment' => 'fixed']));
        ob_start(); $empty->sendContent(); $csv = ob_get_clean();
        $this->assertSame(false, str_contains($csv, 'Reschedule Customer'));
    }

    public function test_separate_trip_cases_cannot_be_mixed_and_closed_case_cannot_be_overwritten(): void
    {
        $otherRide = LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $this->ride->lead_id, 'from_date' => '2026-10-30 10:00:00']);
        $otherCase = app(OperationCaseService::class)->open($this->ride->enquiry, 'reschedule', ['ride_id' => $otherRide->id], $this->ops->id);
        $this->assertSame(false, $this->case->id === $otherCase->id);
        try {
            app(RescheduleTrackingService::class)->save($this->case, $otherRide, $this->ops, ['no_date' => 1]);
            $this->assertSame(true, false);
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['case'])); }
        $service = app(RescheduleTrackingService::class);
        $service->save($this->case, $this->ride, $this->ops, ['from_date' => '2026-10-20 10:00:00']);
        try {
            $service->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
            $this->assertSame(true, false);
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['case'])); }
        $this->assertSame('2026-10-20 10:00:00', $this->ride->fresh()->from_date->toDateTimeString());
    }

    public function test_scheduler_creates_one_reminder_and_one_expiry_assessment_without_money_changes(): void
    {
        $tracking = app(RescheduleTrackingService::class);
        $tracking->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        $windows = app(\App\Services\Operations\RescheduleWindowService::class);
        $this->assertSame(1, OperationCase::where('metadata->source', $windows::REMINDER_SOURCE)->count());
        $windows->process(); $windows->process();
        $this->assertSame(1, OperationCase::where('metadata->source', $windows::REMINDER_SOURCE)->count());
        \Carbon\Carbon::setTestNow('2026-09-23 23:59:00'); $windows->process();
        $this->assertSame(0, OperationCase::where('metadata->source', $windows::ASSESSMENT_SOURCE)->count());
        \Carbon\Carbon::setTestNow('2026-09-24 00:00:00');
        $counts = $windows->process(); $this->assertSame(1, $counts['assessments']); $windows->process();
        $this->assertSame(1, OperationCase::where('metadata->source', $windows::ASSESSMENT_SOURCE)->count());
        $reminder = OperationCase::where('metadata->source', $windows::REMINDER_SOURCE)->first();
        $this->assertSame('completed', $reminder->status);
        $this->assertSame(null, $this->case->fresh()->completed_at);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('payment_audit_trail')->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('lead_refunds')->count());
    }

    public function test_new_date_supersedes_reminders_and_scheduler_skips_closed_cases(): void
    {
        $tracking = app(RescheduleTrackingService::class);
        $tracking->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        $tracking->save($this->case, $this->ride, $this->ops, ['from_date' => '2026-10-30 10:00:00']);
        $windows = app(\App\Services\Operations\RescheduleWindowService::class); $windows->process();
        $reminder = OperationCase::where('metadata->source', $windows::REMINDER_SOURCE)->first();
        $this->assertSame(true, data_get($reminder->metadata, 'superseded'));
        $this->assertSame(0, OperationCase::where('metadata->source', $windows::REMINDER_SOURCE)->whereNull('completed_at')->count());
        \Carbon\Carbon::setTestNow('2027-01-01'); $windows->process();
        $this->assertSame(0, OperationCase::where('metadata->source', $windows::ASSESSMENT_SOURCE)->count());
    }

    public function test_assessments_require_accounts_decision_and_generic_completion_is_blocked(): void
    {
        \Carbon\Carbon::setTestNow('2026-09-24');
        app(RescheduleTrackingService::class)->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        $windows = app(\App\Services\Operations\RescheduleWindowService::class);
        $task = OperationCase::where('metadata->source', $windows::ASSESSMENT_SOURCE)->firstOrFail();
        try { app(OperationCaseService::class)->complete($task, $this->ops); $this->assertSame(true, false); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['case'])); }
        try { $windows->assess($task, $this->ops, 'no_refund', 'Not authorized'); $this->assertSame(true, false); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $windows->assess($task, $this->ops, 'refund_review_required', 'Please review');
        $this->assertSame(null, $task->fresh()->completed_at);
        $accounts = $this->createUserWithRole('Expiry Accounts', UserType::ACCOUNTS_MANAGER);
        $windows->assess($task, $accounts, 'refund_review_required', 'Refund processing required');
        $this->assertSame(null, $task->fresh()->completed_at);
        $windows->assess($task, $accounts, 'no_refund', 'Policy reviewed; no refund due');
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame($accounts->id, $task->fresh()->completed_by);
    }

    public function test_resource_conflicts_are_blocked_and_touching_slots_are_allowed(): void
    {
        $this->ride->update(['booking_resource_key' => 'vt-uml']);
        $this->assertSame('VT-UML', $this->ride->fresh()->booking_resource_key);
        $other = LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $this->createLeadFor($this->ops, 'Other booking'), 'from_date' => '2026-06-25 12:00:00', 'to_date' => '2026-06-25 13:00:00', 'booking_resource_key' => 'VT-UML']);
        try { $other->update(['from_date' => '2026-06-25 11:00:00']); $this->assertSame(true, false); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['booking_resource_key'])); }
        $this->assertSame('2026-06-25 12:00:00', $other->fresh()->from_date->toDateTimeString());
        $other->refresh()->update(['booking_resource_key' => 'VT-OTHER', 'from_date' => '2026-06-25 11:00:00']);
        $this->assertSame('VT-OTHER', $other->fresh()->booking_resource_key);
        try { $other->update(['to_date' => null]); $this->assertSame(true, false); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['to_date'])); }
    }

    public function test_conflict_endpoint_and_transaction_rollback_keep_the_open_case_safe(): void
    {
        $otherLead = $this->createLeadFor($this->ops, 'Conflicting booking');
        LeadRide::create(['id' => (string) Str::uuid(), 'lead_id' => $otherLead, 'from_date' => '2026-10-30 10:00:00', 'to_date' => '2026-10-30 12:00:00', 'booking_resource_key' => 'VT-UML']);
        $controller = app(OperationsRescheduleController::class);
        $data = ['no_date' => 0, 'from_date' => '2026-10-30 11:00:00', 'to_date' => '2026-10-30 13:00:00', 'booking_resource_key' => 'vt-uml'];
        $response = $controller->conflicts($this->request($data), $this->ride)->getData(true);
        $this->assertSame('conflict', $response['status']); $this->assertSame(1, count($response['conflicts']));
        try { $controller->update($this->request($data, 'POST'), $this->ride, app(OperationCaseService::class)); $this->assertSame(true, false); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame(true, isset($e->errors()['booking_resource_key'])); }
        $this->assertSame(null, $this->case->fresh()->completed_at);
        $this->assertSame('2026-06-25 10:00:00', $this->ride->fresh()->from_date->toDateTimeString());
        $this->assertSame('unverified', $controller->conflicts($this->request([]), $this->ride)->getData(true)['status']);
        $this->assertSame('incomplete', $controller->conflicts($this->request(['booking_resource_key' => 'VT-UML']), $this->ride)->getData(true)['status']);
    }

    public function test_expiry_assessment_page_renders_and_hr_is_denied_by_route(): void
    {
        \Carbon\Carbon::setTestNow('2026-09-24');
        app(RescheduleTrackingService::class)->save($this->case, $this->ride, $this->ops, ['no_date' => 1]);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $view = app(\App\Http\Controllers\RescheduleAssessmentController::class)->index($this->request());
        $html = $view->render(); $this->assertSame(true, str_contains($html, 'Recommend refund review'));
        $this->assertSame(false, str_contains($html, '>No refund due<'));
        if (getenv('CRM_RESCHEDULE_PREVIEW_DIR')) file_put_contents(getenv('CRM_RESCHEDULE_PREVIEW_DIR').'/assessments.html', $html);
        $hr = $this->createUserWithRole('Denied HR', UserType::HR); $request = $this->request(); $request->setUserResolver(fn() => $hr);
        try { (new \App\Http\Middleware\RoleMiddleware())->handle($request, fn() => response('ok'), 'OPERATIONS_ROLES', 'ACCOUNTS_ROLES', 'ADMIN_ROLES'); $this->assertSame(true, false); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }
}
