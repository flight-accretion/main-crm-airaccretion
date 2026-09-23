<?php

namespace Tests\Feature\Operations;

use App\Http\Controllers\IvrAgentController;
use App\Models\CallSummaryIntegration;
use App\Models\Client;
use App\Models\IvrAgent;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use App\Services\CallSummaryIntegrationService;
use App\Services\Operations\OperationsCallDashboardService;
use App\Services\Operations\OperationsCallLeadResolver;
use App\Services\Operations\OperationsLeadAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsCallFlowTest extends TestCase
{
    private UserType $salesType;
    private UserType $operationsType;
    private UserType $operationsManagerType;
    private UserType $superAdminType;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        $this->createUserTypes();
    }

    public function test_sales_owner_is_not_changed_when_operations_handler_is_assigned(): void
    {
        $salesUser = $this->user('Richa Sales', 'richa@example.test', $this->salesType);
        $operationsUser = $this->user('Deepak Ops', 'deepak@example.test', $this->operationsType);
        $manager = $this->user('Ops Manager', 'ops-manager@example.test', $this->operationsManagerType);
        $lead = $this->leadWithLatestStatus('9000000001', $salesUser, LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED);

        app(OperationsLeadAssignmentService::class)->assign($lead, $operationsUser, $manager);

        $this->assertSame($salesUser->id, $lead->fresh()->representative_user_id);
        $this->assertDatabaseHas('operations_lead_assignments', [
            'lead_id' => $lead->id,
            'operations_user_id' => $operationsUser->id,
            'is_active' => 1,
        ]);
    }

    public function test_operations_assignment_creates_note_in_existing_lead_history(): void
    {
        $salesUser = $this->user('Richa Sales', 'richa-note@example.test', $this->salesType);
        $operationsUser = $this->user('Deepak Ops', 'deepak-note@example.test', $this->operationsType);
        $manager = $this->user('Ops Manager', 'ops-manager-note@example.test', $this->operationsManagerType);
        $lead = $this->leadWithLatestStatus('9000000002', $salesUser, LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED);

        app(OperationsLeadAssignmentService::class)->assign($lead, $operationsUser, $manager);

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $manager->id,
            'status' => LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED,
        ]);

        $this->assertTrue(
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->where('followup_note', 'like', 'Operations handling assigned to Deepak Ops%')
                ->exists()
        );
    }

    public function test_operations_agent_can_attach_summary_to_sales_owned_eligible_lead(): void
    {
        $salesUser = $this->user('Richa Sales', 'richa-call@example.test', $this->salesType);
        $operationsUser = $this->user('Jitendra Ops', 'jitendra-call@example.test', $this->operationsType);
        $lead = $this->leadWithLatestStatus('9000000003', $salesUser, LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED);

        IvrAgent::create([
            'vi_agent_name' => 'Jitendra Ops',
            'vi_agent_number' => '7000000001',
            'mapped_user_id' => $operationsUser->id,
            'is_active' => true,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000003',
            'summary' => 'Customer called Operations to confirm reporting time.',
            'followup_date' => '2026-09-22 17:00:00',
            'call_start_at' => '2026-09-22 11:15:10',
            'call_end_at' => '2026-09-22 11:19:42',
            'agent_name' => 'Jitendra Ops',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => 5001,
        ]);

        $this->assertSame('followup_created', $integration->status);
        $this->assertSame('operations_phone', $integration->match_method);
        $this->assertSame($lead->id, $integration->lead_id);
        $this->assertSame($salesUser->id, $lead->fresh()->representative_user_id);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $operationsUser->id,
            'followup_recording_id' => 5001,
            'status' => LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
        ]);
    }

    public function test_sales_agent_mismatch_protection_remains_unchanged(): void
    {
        $salesOwner = $this->user('Richa Sales', 'richa-mismatch@example.test', $this->salesType);
        $otherSalesUser = $this->user('Other Sales', 'other-sales@example.test', $this->salesType);
        $lead = $this->leadWithLatestStatus('9000000004', $salesOwner, LeadFollowup::STATUS_ACTIVE);

        IvrAgent::create([
            'vi_agent_name' => 'Other Sales',
            'vi_agent_number' => '7000000002',
            'mapped_user_id' => $otherSalesUser->id,
            'is_active' => true,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000004',
            'summary' => 'Sales mismatch should not attach.',
            'followup_date' => '2026-09-22 17:00:00',
            'call_start_at' => '2026-09-22 11:15:10',
            'call_end_at' => '2026-09-22 11:19:42',
            'agent_name' => 'Other Sales',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => 5002,
        ]);

        $this->assertSame('ambiguous_match', $integration->status);
        $this->assertSame('active_lead_agent_mismatch', $integration->match_method);
        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $lead->id,
            'followup_note' => 'Sales mismatch should not attach.',
        ]);
    }

    public function test_operations_call_is_not_auto_attached_when_multiple_eligible_leads_share_phone(): void
    {
        $salesUser = $this->user('Richa Sales', 'richa-duplicate@example.test', $this->salesType);
        $firstLead = $this->leadWithLatestStatus('9000000005', $salesUser, LeadFollowup::STATUS_CONFIRMED);
        $secondLead = $this->leadWithLatestStatus('9000000005', $salesUser, LeadFollowup::STATUS_RESCHEDULED);

        $this->assertNotSame($firstLead->id, $secondLead->id);
        $this->assertNull(app(OperationsCallLeadResolver::class)->resolveByPhone('9000000005'));
    }

    public function test_dashboard_called_today_requires_verified_call_integration(): void
    {
        $salesUser = $this->user('Richa Sales', 'richa-dashboard@example.test', $this->salesType);
        $operationsUser = $this->user('Deepak Ops', 'deepak-dashboard@example.test', $this->operationsType);
        $manager = $this->user('Ops Manager', 'ops-manager-dashboard@example.test', $this->operationsManagerType);
        $lead = $this->leadWithLatestStatus('9000000006', $salesUser, LeadFollowup::STATUS_CONFIRMED);

        DB::table('operations_lead_assignments')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'operations_user_id' => $operationsUser->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => null,
            'is_active' => true,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Manual Operations note from today.',
            'followed_by' => $operationsUser->id,
            'status' => LeadFollowup::STATUS_CONFIRMED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(OperationsCallDashboardService::class)->rows($manager);
        $this->assertFalse($rows->firstWhere('lead.id', $lead->id)['called_today']);

        $verifiedFollowup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Verified Operations call.',
            'followed_by' => $operationsUser->id,
            'status' => LeadFollowup::STATUS_CONFIRMED,
            'followup_recording_id' => 5003,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CallSummaryIntegration::create([
            'id' => (string) Str::uuid(),
            'call_fingerprint' => 'verified-dashboard-call',
            'followup_recording_id' => 5003,
            'phone_number' => '9000000006',
            'normalized_phone' => '9000000006',
            'summary' => 'Verified Operations call.',
            'followup_date' => now()->addDay(),
            'call_start_at' => now()->subMinutes(10),
            'call_end_at' => now()->subMinutes(5),
            'agent_name' => 'Deepak Ops',
            'normalized_agent_name' => 'deepakops',
            'direction' => 'incoming',
            'lead_id' => $lead->id,
            'agent_user_id' => $operationsUser->id,
            'followup_id' => $verifiedFollowup->id,
            'match_method' => 'operations_phone',
            'status' => 'followup_created',
            'processed_at' => now(),
        ]);

        $rows = app(OperationsCallDashboardService::class)->rows($manager);
        $this->assertTrue($rows->firstWhere('lead.id', $lead->id)['called_today']);
    }

    public function test_ivr_agent_mapping_lists_sales_and_operations_users(): void
    {
        $superAdmin = $this->user('Super Admin', 'super-admin@example.test', $this->superAdminType);
        $salesUser = $this->user('Richa Sales', 'richa-ivr@example.test', $this->salesType);
        $operationsUser = $this->user('Jitendra Ops', 'jitendra-ivr@example.test', $this->operationsType);

        Auth::login($superAdmin);

        $view = app(IvrAgentController::class)->create();
        $userIds = $view->getData()['users']->pluck('id')->all();

        $this->assertContains($salesUser->id, $userIds);
        $this->assertContains($operationsUser->id, $userIds);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
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
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('representative_user_id')->nullable();
            $table->text('service_ids')->nullable();
            $table->text('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->unsignedBigInteger('followup_recording_id')->nullable();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->string('file')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->string('contact_outcome')->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
            $table->timestamps();

            $table->unique(['lead_id', 'followup_recording_id'], 'lead_followups_lead_recording_unique');
        });

        Schema::create('operations_lead_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('operations_user_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ivr_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vi_agent_name')->nullable();
            $table->string('vi_agent_number')->nullable();
            $table->uuid('mapped_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id')->nullable();
            $table->string('call_type_code')->nullable();
            $table->string('dni')->nullable();
            $table->string('cli')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('agent_name')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('call_end_at')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('call_summary_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('call_fingerprint', 64)->unique();
            $table->unsignedBigInteger('followup_recording_id')->nullable();
            $table->string('phone_number', 50);
            $table->string('normalized_phone', 20)->nullable();
            $table->text('summary');
            $table->timestamp('followup_date')->nullable();
            $table->timestamp('call_start_at');
            $table->timestamp('call_end_at');
            $table->string('agent_name', 150);
            $table->string('normalized_agent_name', 150)->nullable();
            $table->string('agent_phone', 50)->nullable();
            $table->string('normalized_agent_phone', 20)->nullable();
            $table->string('direction', 20);
            $table->decimal('sentiment_score', 5, 2)->nullable();
            $table->boolean('is_dnp')->default(false);
            $table->uuid('ivr_call_log_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('agent_user_id')->nullable();
            $table->uuid('followup_id')->nullable();
            $table->integer('match_score')->nullable();
            $table->string('match_method', 100)->nullable();
            $table->string('status', 50)->default('received');
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function createUserTypes(): void
    {
        $this->salesType = $this->userType(UserType::SALES_EXECUTIVE);
        $this->operationsType = $this->userType(UserType::OPERATIONS_EXECUTIVE);
        $this->operationsManagerType = $this->userType(UserType::OPERATIONS_MANAGER);
        $this->superAdminType = $this->userType(UserType::SUPER_ADMIN);
    }

    private function userType(string $type): UserType
    {
        return UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $type,
            'status' => 1,
        ]);
    }

    private function user(string $name, string $email, UserType $type): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function leadWithLatestStatus(string $phone, User $salesUser, int $status): Lead
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Customer ' . $phone,
            'contact_number' => $phone,
            'alternate_number' => null,
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $salesUser->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Existing latest status.',
            'followed_by' => $salesUser->id,
            'status' => $status,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        return $lead;
    }
}
