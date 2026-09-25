<?php

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsDashboardFilterTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'operations_filter_testing');
        config()->set('database.connections.operations_filter_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('operations_filter_testing');
        DB::reconnect('operations_filter_testing');
        DB::setDefaultConnection('operations_filter_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 10, 0, 0));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('operations_filter_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('operations_filter_testing');

        parent::tearDown();
    }

    public function test_queue_filters_by_date_status_assignee_and_search(): void
    {
        $viewer = $this->user('Ops Viewer', UserType::OPERATIONS_EXECUTIVE);
        $assignee = $this->user('Matching Ops', UserType::OPERATIONS_EXECUTIVE);
        $otherAssignee = $this->user('Other Ops', UserType::OPERATIONS_EXECUTIVE);

        $matchingLeadId = $this->lead('Match Customer', $viewer);
        $otherLeadId = $this->lead('Other Customer', $viewer);

        $this->case(
            $matchingLeadId,
            'review',
            'pending',
            $assignee->id,
            '2026-09-22 11:00:00',
            null,
            'Matching summary'
        );
        $this->case(
            $otherLeadId,
            'review',
            'in_progress',
            $otherAssignee->id,
            '2026-09-22 12:00:00',
            null,
            'Other summary'
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', [
                'type' => 'review',
                'from_date' => '2026-09-22',
                'to_date' => '2026-09-22',
                'status' => 'pending',
                'operations_user_id' => $assignee->id,
                'search' => 'Match',
            ]));

        $response->assertOk();
        $response->assertSee('Match Customer');
        $response->assertDontSee('Other Customer');
        $response->assertSee('operations-queue-table');
        $response->assertSee('name="status"', false);
        $response->assertSee('name="operations_user_id"', false);
    }

    public function test_queue_uses_leads_table_columns_and_actions(): void
    {
        $viewer = $this->user('Ops Table Viewer', UserType::OPERATIONS_EXECUTIVE);
        $sales = $this->user('Sales Rep Anita', UserType::SALES_EXECUTIVE);

        $leadId = $this->lead('Table Customer', $sales);
        $this->enrichLead($leadId, 'Helicopter Charter', 1);

        $caseId = $this->case($leadId, 'review', 'pending', $viewer->id, '2026-09-22 11:00:00', null, 'Note');
        DB::table('operation_cases')->where('id', $caseId)->update([
            'next_followup_at' => '2026-09-30 15:30:00',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', 'review'));

        $response->assertOk();

        foreach (['S.No', 'Client Name', 'Phone', 'Next Follow Up', 'Assigned:', 'Service Date:', 'Service:', 'Status', 'Action'] as $heading) {
            $response->assertSee('>' . $heading . '</th>', false);
        }

        $response->assertSee('Table Customer');
        $response->assertSee('9876543210');
        $response->assertSee('30-09-2026 15:30');
        $response->assertSee('Sales Rep Anita');
        $response->assertSee('From: 01-10-2026', false);
        $response->assertSee('To: 03-10-2026', false);
        $response->assertSee('Helicopter Charter');
        $response->assertSee('Active');

        // Action items: existing Add Follow-up, View Lead like the Leads page, and Complete popup.
        $response->assertSee(route('admin.operations.followups.create', $caseId), false);
        $response->assertSee(route('admin.leads.view', $leadId), false);
        $response->assertSee('complete-case-btn', false);
        $response->assertSee('complete-case-modal', false);
        $response->assertSee(route('admin.operations.case.complete', $caseId), false);
        $response->assertDontSee('<input name="note"', false);
        $response->assertDontSee('>Start</button>', false);
        $response->assertSee('name="lead_status"', false);
        $response->assertSee('name="representative_user_id"', false);
    }

    public function test_queue_applies_leads_style_filters(): void
    {
        $viewer = $this->user('Ops Filter Viewer', UserType::OPERATIONS_EXECUTIVE);
        $sales = $this->user('Sales Filter Rep', UserType::SALES_EXECUTIVE);
        $otherSales = $this->user('Sales Other Rep', UserType::SALES_EXECUTIVE);

        $matchLead = $this->lead('Filter Match', $sales);
        $otherLead = $this->lead('Filter Other', $otherSales);

        $serviceId = $this->enrichLead($matchLead, 'Match Service', 1);
        $this->enrichLead($otherLead, 'Other Service', 3, '2026-11-01');

        DB::table('clients')->where('name', 'Filter Other')->update(['contact_number' => '1112223333']);

        $this->case($matchLead, 'review', 'pending', $viewer->id, '2026-09-22 11:00:00', null, 'a');
        $this->case($otherLead, 'review', 'pending', $viewer->id, '2026-09-22 12:00:00', null, 'b');

        $filters = [
            'name' => 'Filter Match',
            'phone' => '98765',
            'representative_user_id' => $sales->id,
            'lead_status' => '1',
            'service_ids' => $serviceId,
            'to_service_date' => '2026-10-31',
        ];

        foreach ($filters as $key => $value) {
            $response = $this
                ->actingAs($viewer)
                ->get(route('admin.operations.queue', ['review', $key => $value]));

            $response->assertOk();
            $response->assertSee('Filter Match');
            $response->assertDontSee('Filter Other');
        }

        // From service date only: the lead travelling in November matches, October does not.
        $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', ['review', 'from_service_date' => '2026-10-20']))
            ->assertOk()
            ->assertSee('Filter Other')
            ->assertDontSee('Filter Match');

        // Lead status N/A = lead without any follow-up.
        $noFollowup = $this->lead('No Followup Lead', $sales);
        $this->case($noFollowup, 'review', 'pending', $viewer->id, '2026-09-22 13:00:00', null, 'c');

        $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', ['review', 'lead_status' => 'na']))
            ->assertOk()
            ->assertSee('No Followup Lead')
            ->assertDontSee('Filter Match');
    }

    public function test_complete_from_popup_closes_case_with_optional_note(): void
    {
        $viewer = $this->user('Ops Complete Viewer', UserType::OPERATIONS_EXECUTIVE);
        $leadId = $this->lead('Complete Customer', $viewer);
        $caseId = $this->case($leadId, 'review', 'pending', $viewer->id, '2026-09-22 11:00:00', null, 'Open');
        $withoutNoteId = $this->case($leadId, 'refund', 'pending', $viewer->id, '2026-09-22 11:00:00', null, 'Open');

        $this
            ->actingAs($viewer)
            ->post(route('admin.operations.case.complete', $caseId), ['note' => 'Reviewed with customer'])
            ->assertRedirect();

        $this
            ->actingAs($viewer)
            ->post(route('admin.operations.case.complete', $withoutNoteId))
            ->assertRedirect();

        $this->assertDatabaseHas('operation_cases', [
            'id' => $caseId,
            'status' => 'completed',
            'note' => 'Reviewed with customer',
        ]);
        $this->assertDatabaseHas('operation_cases', [
            'id' => $withoutNoteId,
            'status' => 'completed',
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', 'review'))
            ->assertOk()
            ->assertDontSee('Complete Customer');
    }

    public function test_history_uses_leads_table_without_complete_action(): void
    {
        $viewer = $this->user('Ops History Table', UserType::OPERATIONS_EXECUTIVE);
        $leadId = $this->lead('History Table Customer', $viewer);
        $caseId = $this->case($leadId, 'review', 'completed', $viewer->id, '2026-09-20 11:00:00', '2026-09-22 15:00:00', 'Done');

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.history'));

        $response->assertOk();
        $response->assertSee('History Table Customer');
        $response->assertSee('>Client Name</th>', false);
        $response->assertSee('>Completed</th>', false);
        $response->assertSee('22-09-2026 15:00');
        $response->assertSee(route('admin.leads.view', $leadId), false);
        $response->assertDontSee(route('admin.operations.case.complete', $caseId), false);
        $response->assertDontSee('complete-case-modal', false);
    }

    public function test_history_filters_completed_cases_by_date_assignee_and_search(): void
    {
        $viewer = $this->user('History Viewer', UserType::OPERATIONS_EXECUTIVE);
        $assignee = $this->user('History Ops', UserType::OPERATIONS_EXECUTIVE);
        $otherAssignee = $this->user('Other History Ops', UserType::OPERATIONS_EXECUTIVE);

        $matchingLeadId = $this->lead('History Match Customer', $viewer);
        $otherLeadId = $this->lead('History Other Customer', $viewer);

        $this->case(
            $matchingLeadId,
            'refund',
            'completed',
            $assignee->id,
            '2026-09-20 11:00:00',
            '2026-09-22 15:00:00',
            'History matching note'
        );
        $this->case(
            $otherLeadId,
            'refund',
            'completed',
            $otherAssignee->id,
            '2026-09-20 12:00:00',
            '2026-09-21 15:00:00',
            'History other note'
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.history', [
                'from_date' => '2026-09-22',
                'to_date' => '2026-09-22',
                'type' => 'refund',
                'operations_user_id' => $assignee->id,
                'search' => 'History Match',
            ]));

        $response->assertOk();
        $response->assertSee('History Match Customer');
        $response->assertDontSee('History Other Customer');
        $response->assertSee('operations-history-table');
        $response->assertSee('name="type"', false);
        $response->assertSee('name="operations_user_id"', false);
    }

    public function test_overview_quick_date_filter_scopes_dashboard_counts_and_activity(): void
    {
        $viewer = $this->user('Overview Viewer', UserType::OPERATIONS_EXECUTIVE);
        $assignee = $this->user('Overview Ops', UserType::OPERATIONS_EXECUTIVE);

        $todayLeadId = $this->lead('Today Customer', $viewer);
        $yesterdayLeadId = $this->lead('Yesterday Customer', $viewer);

        $todayCaseId = $this->case(
            $todayLeadId,
            'customer_call',
            'pending',
            $assignee->id,
            '2026-09-23 09:00:00',
            null,
            'Today overview case'
        );

        $yesterdayCaseId = $this->case(
            $yesterdayLeadId,
            'customer_call',
            'pending',
            $assignee->id,
            '2026-09-22 09:00:00',
            null,
            'Yesterday overview case'
        );

        $this->activity(
            $todayCaseId,
            $todayLeadId,
            $assignee->id,
            'followup_saved',
            '2026-09-23 09:30:00'
        );

        $this->activity(
            $yesterdayCaseId,
            $yesterdayLeadId,
            $assignee->id,
            'start',
            '2026-09-22 09:30:00'
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.index', [
                'date_filter' => 'today',
            ]));

        $response->assertOk();
        $response->assertSee('Overview');
        $response->assertSee('name="date_filter"', false);
        $response->assertSee('value="today"', false);
        $response->assertSee('value="yesterday"', false);
        $response->assertSee('value="monthly"', false);
        $response->assertSee('value="custom"', false);
        $response->assertSee('data-overview-count-type="customer_call"', false);
        $response->assertSee('data-overview-count-value="1"', false);
        $response->assertSee('Followup Saved');
        $response->assertDontSee('Yesterday Customer');
        $response->assertDontSee('22 Sep 2026 09:30 AM');
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->text('service_ids')->nullable();
            $table->text('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('operation_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_case_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function user(string $name, string $role): User
    {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'status' => 1,
            ]
        );

        return User::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function lead(string $customerName, User $salesUser): string
    {
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => $customerName,
            'contact_number' => '9876543210',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'id' => $leadId,
            'client_id' => $clientId,
            'representative_user_id' => $salesUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $leadId;
    }

    /**
     * Gives a lead a service, a ride segment (default 2026-10-01 to 2026-10-03)
     * and a latest follow-up status. Returns the service id.
     */
    private function enrichLead(
        string $leadId,
        string $serviceName,
        int $followupStatus,
        string $rideFrom = '2026-10-01'
    ): string {
        $serviceId = (string) Str::uuid();
        $from = Carbon::parse($rideFrom);

        DB::table('services')->insert([
            'id' => $serviceId,
            'service' => $serviceName,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->where('id', $leadId)->update([
            'service_ids' => json_encode([$serviceId]),
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'from_date' => $from->copy()->setTime(9, 0),
            'to_date' => $from->copy()->addDays(2)->setTime(18, 0),
            'from_place' => 'Mumbai',
            'to_place' => 'Pune',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_followups')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'status' => $followupStatus,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $serviceId;
    }

    private function case(
        string $leadId,
        string $type,
        string $status,
        string $assigneeId,
        string $openedAt,
        ?string $completedAt,
        string $note
    ): string {
        $id = (string) Str::uuid();

        DB::table('operation_cases')->insert([
            'id' => $id,
            'lead_id' => $leadId,
            'type' => $type,
            'status' => $status,
            'assigned_to' => $assigneeId,
            'created_by' => null,
            'completed_by' => $completedAt ? $assigneeId : null,
            'opened_at' => $openedAt,
            'completed_at' => $completedAt,
            'note' => $note,
            'metadata' => null,
            'created_at' => $openedAt,
            'updated_at' => $completedAt ?: $openedAt,
        ]);

        return $id;
    }

    private function activity(
        string $caseId,
        string $leadId,
        string $userId,
        string $action,
        string $createdAt
    ): void {
        DB::table('operation_case_activities')->insert([
            'id' => (string) Str::uuid(),
            'operation_case_id' => $caseId,
            'lead_id' => $leadId,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => null,
            'to_status' => 'pending',
            'note' => null,
            'metadata' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
