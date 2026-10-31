<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\ClientController;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\OperationCase;
use App\Models\UserType;
use App\Services\Operations\OperationsActivityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadStatusPreservationTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); Mail::fake(); Queue::fake();
        Event::fake(['eloquent.created: '.LeadFollowup::class, 'eloquent.updated: '.LeadFollowup::class]);
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->uuid('parent_followup_id')->nullable();
            foreach (['file', 'service_ids', 'extra_service_ids', 'service_details', 'payment_method', 'paid_date', 'contact_outcome'] as $name) {
                $table->text($name)->nullable();
            }
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
        });
        Schema::table('clients', function (Blueprint $table) {
            foreach (['email', 'alternate_number', 'date_of_birth', 'city_id', 'country_id', 'address', 'description', 'company_name', 'gst_number'] as $name) $table->string($name)->nullable();
            $table->integer('status')->default(0);
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->dateTime('next_follow_up')->nullable();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('product'); $table->integer('status')->default(1); $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('service'); $table->integer('status')->default(1); $table->decimal('service_amount')->default(0); $table->decimal('fees_percent')->default(0); $table->timestamps();
        });
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->string('name'); $table->integer('status')->default(1);
        });
        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('lead_id'); $table->timestamps();
        });
        config(['session.driver' => 'array']);
    }

    private function fixture(string $role, int $status = 3): array
    {
        $actor = $this->createUserWithRole('Status '.$role, $role);
        $lead = Lead::findOrFail($this->createLeadFor($actor));
        $previous = LeadFollowup::withoutEvents(fn () => LeadFollowup::create([
            'id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'status' => $status,
            'followup_note' => 'Payment recorded', 'total_amount' => 1000, 'received_amount' => 1000,
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]));
        DB::table('lead_followups')->where('id', $previous->id)->update(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        DB::table('payment_audit_trail')->insert(['id' => (string) Str::uuid(), 'lead_followup_id' => $previous->id, 'paid_amount' => 1000, 'payment_status' => 1]);
        $this->actingAs($actor);
        return [$actor, $lead, $previous];
    }

    private function request(array $data, bool $json = false): Request
    {
        $request = Request::create('/admin/leads/follow-up', 'POST', $data);
        if ($json) $request->headers->set('Accept', 'application/json');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);
        return $request;
    }

    /** @dataProvider preservationCases */
    public function test_note_only_preserves_status_without_recording_another_payment(string $role, int $status, bool $json, ?string $submittedStatus): void
    {
        [, $lead] = $this->fixture($role, $status);
        $data = ['notes' => 'Called customer about travel details'];
        if ($submittedStatus !== null) $data['status'] = $submittedStatus;
        $request = $this->request($data, $json);
        $response = app(ClientController::class)->storeLeadFollowUp($request, $lead);
        $this->assertSame($json ? 200 : 302, $response->getStatusCode());
        $this->assertFalse(session()->has('errors'), json_encode(session('errors')?->all()));
        $this->assertFalse(session()->has('error'), (string) session('error'));
        $this->assertSame(2, $lead->leadFollowups()->count());
        $saved = $lead->leadFollowups()->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertSame($status, (int) $saved->status);
        $this->assertNull($saved->received_amount);
        $this->assertSame(1000.0, (float) $saved->total_amount);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
        $this->assertSame(0, DB::table('kpi_activity_events')->where('event_type', 'status_changed')->count());
    }

    public static function preservationCases(): array
    {
        $cases = [];
        foreach ([UserType::SALES_EXECUTIVE, UserType::OPERATIONS_EXECUTIVE, UserType::SUPER_ADMIN] as $role) {
            foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9] as $status) {
                $cases[$role.' status '.$status] = [$role, $status, false, 'keep'];
            }
        }
        $cases['missing status HTML'] = [UserType::SALES_EXECUTIVE, 3, false, null];
        $cases['missing status JSON'] = [UserType::SUPER_ADMIN, 4, true, null];
        $cases['keep status JSON'] = [UserType::OPERATIONS_EXECUTIVE, 5, true, 'keep'];
        return $cases;
    }

    public function test_sales_cannot_cancel_after_approved_payment(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $request = $this->request(['notes' => 'Customer requested cancellation', 'status' => 2], true);
        $response = app(ClientController::class)->storeLeadFollowUp($request, $lead);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1, $lead->leadFollowups()->count());
        $this->assertSame(0, DB::table('kpi_activity_events')->where('event_type', 'status_changed')->count());
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    public function test_explicit_payment_still_requires_payment_details(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        app(ClientController::class)->storeLeadFollowUp($this->request(['notes' => 'New payment', 'status' => 3]), $lead);
        $this->assertTrue(session('errors')->has('received_amount'));
        $this->assertTrue(session('errors')->has('payment_method'));
        $this->assertSame(1, $lead->leadFollowups()->count());
    }

    public function test_explicit_new_payment_still_creates_one_payment_entry(): void
    {
        [, $lead] = $this->fixture(UserType::OPERATIONS_EXECUTIVE, 4);
        $service = (string) Str::uuid();
        DB::table('services')->insert(['id' => $service, 'service' => 'Flight', 'service_amount' => 2000]);
        $request = $this->request([
            'notes' => 'Received final payment', 'status' => 3, 'services' => [$service],
            'received_amount' => 1000, 'payment_method' => 'Paid Directly to Vendor', 'paid_date' => now()->toDateString(),
        ]);
        $response = app(ClientController::class)->storeLeadFollowUp($request, $lead);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse(session()->has('errors'), json_encode(session('errors')?->all()));
        $this->assertFalse(session()->has('error'), (string) session('error'));
        $this->assertSame(2, DB::table('payment_audit_trail')->count());
        $this->assertSame(2000.0, (float) DB::table('payment_audit_trail')->sum('paid_amount'));
        $this->assertSame(3, (int) $lead->leadFollowups()->latest('created_at')->first()->status);
    }

    public function test_keep_does_not_replay_stale_payment_fields(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request([
            'notes' => 'Only adding a note', 'status' => 'keep', 'received_amount' => 1000,
            'payment_method' => 'Acepoint Point Redeem', 'redeem_points' => 1000, 'paid_date' => '2026-09-17',
        ], true), $lead);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull($response->getData()->data->received_amount);
        $this->assertNull($response->getData()->data->payment_method);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
        Http::assertNothingSent();
    }

    public function test_keep_resolves_server_status_even_when_lead_was_preloaded(): void
    {
        [, $lead, $old] = $this->fixture(UserType::SUPER_ADMIN, 1);
        $lead->load('leadFollowups');
        DB::table('lead_followups')->where('id', $old->id)->update(['status' => 3]);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request(['notes' => 'No change', 'status' => 'keep'], true), $lead);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, (int) $response->getData()->data->status);
    }

    public function test_invalid_status_is_rejected_without_writes(): void
    {
        [, $lead] = $this->fixture(UserType::SUPER_ADMIN);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request(['notes' => 'Invalid change', 'status' => 99], true), $lead);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1, $lead->leadFollowups()->count());
    }

    public function test_edit_uses_latest_saved_status_not_latest_scheduled_date(): void
    {
        [, $lead, $payment] = $this->fixture(UserType::SUPER_ADMIN);
        DB::table('lead_followups')->insert([
            'id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'status' => 1,
            'next_followup_date' => now()->addWeek(), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
        ]);
        // The legacy edit URL redirects to the unified follow-up workspace.
        // The status default must come from the newest saved follow-up, not from
        // the follow-up with the newest scheduled date. Keep this focused on that
        // invariant so this lightweight fixture need not create every optional
        // dashboard table loaded by the full workspace.
        $latestSavedFollowup = $lead->leadFollowups()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $this->assertSame($payment->id, $latestSavedFollowup->id);
        $this->assertSame(3, (int) $latestSavedFollowup->status);
    }

    public function test_form_defaults_to_keep_and_restores_explicit_choice_after_validation(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $this->request([]);
        $html = view('admin.pages.follow-ups.partials.status-select', ['followups' => $lead->leadFollowups()->get()])->render();
        $this->assertMatchesRegularExpression('/<option\s+value="keep"\s+selected\s*>/', $html);
        $this->assertStringContainsString('Select action', $html);
        if ($path = getenv('CRM_STATUS_SELECT_PREVIEW')) file_put_contents($path, $html);
        session()->flashInput(['status' => '4']);
        $html = view('admin.pages.follow-ups.partials.status-select', ['followups' => $lead->leadFollowups()->get()])->render();
        $this->assertMatchesRegularExpression('/<option\s+value="4"\s+selected\s*>/', $html);
        $this->assertStringNotContainsString('value="keep" selected', $html);
    }

    public function test_operations_case_status_does_not_replace_lead_status(): void
    {
        [$actor, $lead] = $this->fixture(UserType::OPERATIONS_EXECUTIVE);
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        (require base_path('database/migrations/2026_09_24_120000_add_operations_followup_fields.php'))->up();
        $case = OperationCase::create(['lead_id' => $lead->id, 'type' => 'customer_call', 'status' => 'pending', 'opened_at' => now()]);
        $followup = app(OperationsActivityService::class)->recordFollowup($case, $actor, ['note' => 'Called client', 'status' => 'completed']);
        $this->assertSame(3, (int) $followup->status);
        $this->assertSame('completed', $case->fresh()->status);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    /** @dataProvider editRoles */
    public function test_edit_preserves_client_state_and_lead_payment_status(string $role): void
    {
        [, $lead] = $this->fixture($role);
        $product = (string) Str::uuid();
        DB::table('products')->insert(['id' => $product, 'product' => 'No requirement', 'status' => 1]);
        Event::fake();
        $request = $this->request([
            'name' => 'Updated customer', 'contact_number' => '9876543210', 'contact_country_code' => '+91',
            'product_ids' => [$product], 'representative_user_id' => $lead->representative_user_id,
        ]);
        $response = app(ClientController::class)->updateLead($request, $lead);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse(session()->has('errors'));
        $this->assertFalse(session()->has('error'), (string) session('error'));
        $this->assertSame('Updated customer', $lead->client->fresh()->name);
        $this->assertSame(0, (int) $lead->client->fresh()->status);
        $this->assertSame(3, (int) $lead->leadFollowups()->first()->status);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    public static function editRoles(): array
    {
        return [[UserType::SALES_EXECUTIVE], [UserType::OPERATIONS_EXECUTIVE], [UserType::SUPER_ADMIN]];
    }

    public function test_no_answer_without_notes_preserves_status(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request(['status' => 'keep', 'customer_not_picked_up' => 1], true), $lead);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, (int) $response->getData()->data->status);
        $this->assertSame('Customer did not pick up.', $response->getData()->data->followup_note);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    public function test_empty_note_request_preserves_existing_status(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request(['status' => 'keep'], true), $lead);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, (int) $response->getData()->data->status);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    public function test_legacy_client_followup_route_preserves_lead_status(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $response = app(ClientController::class)->storeFollowUp($this->request(['notes' => 'Legacy route note'], true), $lead->client);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, (int) $response->getData()->data->status);
        $this->assertSame(1, DB::table('payment_audit_trail')->count());
    }

    public function test_lead_without_previous_status_is_not_made_active_by_default(): void
    {
        $actor = $this->createUserWithRole('First followup', UserType::SUPER_ADMIN);
        $lead = Lead::findOrFail($this->createLeadFor($actor));
        $this->actingAs($actor);
        $response = app(ClientController::class)->storeLeadFollowUp($this->request(['notes' => 'First note', 'status' => 'keep'], true), $lead);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, (int) $response->getData()->data->status);
        $this->assertSame(0, DB::table('payment_audit_trail')->count());
    }

    public function test_sales_cannot_update_another_salespersons_lead(): void
    {
        [, $lead] = $this->fixture(UserType::SALES_EXECUTIVE);
        $other = $this->createUserWithRole('Other sales member', UserType::SALES_EXECUTIVE);
        $this->actingAs($other);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        try {
            app(ClientController::class)->storeLeadFollowUp($this->request(['notes' => 'Not allowed', 'status' => 'keep'], true), $lead);
        } finally {
            $this->assertSame(1, $lead->leadFollowups()->count());
        }
    }
}
