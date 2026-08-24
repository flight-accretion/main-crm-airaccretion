<?php

namespace Tests\Unit;

use App\Models\IvrAgent;
use App\Models\IvrCallType;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\User;
use App\Models\UserType;
use App\Services\IvrImportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class IvrAgentNumberMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 24, 21, 0, 0));

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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_successful_ivr_call_assigns_lead_by_b_party_number_not_agent_name(): void
    {
        $salesperson = $this->createSalesUser('Samarpit Sharma', '9109152175');

        IvrAgent::forceCreate([
            'id' => (string) Str::uuid(),
            'vi_agent_name' => 'Samarpit Sharma',
            'vi_agent_number' => '9109152175',
            'mapped_user_id' => $salesperson->id,
            'is_active' => true,
        ]);

        $callType = IvrCallType::create([
            'id' => (string) Str::uuid(),
            'code' => 'DEFAULT',
            'name' => 'Default IVR',
            'is_active' => true,
        ]);

        $result = app(IvrImportService::class)->import($callType, [[
            'CALLID' => '397568307',
            'DNI' => '9575340786',
            'CLI' => '9341526238',
            'DTMF' => '3',
            'B PARTY NO' => '9109152175',
            'AGENTNAME' => 'Wrong Agent Name',
            'DIALSTATUS' => 'Success',
            'CALLSTARTTIME' => '24/08/2026 15:45:21',
            'CALLENDTIME' => '24/08/2026 15:46:36',
            'DURATIONSEC' => '75',
        ]]);

        $lead = Lead::query()->firstOrFail();

        $this->assertSame(1, $result['created']);
        $this->assertSame($salesperson->id, $lead->representative_user_id);
        $this->assertDatabaseHas('ivr_call_logs', [
            'provider_call_id' => '397568307',
            'agent_name' => 'Wrong Agent Name',
            'agent_number' => '9109152175',
            'lead_id' => $lead->id,
        ]);
        $this->assertDatabaseMissing('lead_allocation_queue', [
            'lead_id' => $lead->id,
        ]);
    }

    public function test_b_party_number_prevents_old_name_mapping_from_assigning_wrong_agent(): void
    {
        $wrongMappedUser = $this->createSalesUser('Wrong Mapped User', '9819515554');

        IvrAgent::forceCreate([
            'id' => (string) Str::uuid(),
            'vi_agent_name' => 'Matching Old Name',
            'vi_agent_number' => '9819515554',
            'mapped_user_id' => $wrongMappedUser->id,
            'is_active' => true,
        ]);

        $callType = IvrCallType::create([
            'id' => (string) Str::uuid(),
            'code' => 'DEFAULT',
            'name' => 'Default IVR',
            'is_active' => true,
        ]);

        app(IvrImportService::class)->import($callType, [[
            'CALLID' => '397568308',
            'DNI' => '9575340786',
            'CLI' => '9341526239',
            'DTMF' => '3',
            'B PARTY NO' => '9109152175',
            'AGENTNAME' => 'Matching Old Name',
            'DIALSTATUS' => 'Success',
            'CALLSTARTTIME' => '24/08/2026 15:45:21',
            'CALLENDTIME' => '24/08/2026 15:46:36',
            'DURATIONSEC' => '75',
        ]]);

        $lead = Lead::query()->firstOrFail();

        $this->assertNull($lead->representative_user_id);
        $this->assertDatabaseHas('lead_allocation_queue', [
            'lead_id' => $lead->id,
            'status' => 'queued',
        ]);
    }

    private function createSalesUser(string $name, string $contactNumber): User
    {
        $userType = UserType::query()->create([
            'id' => (string) Str::uuid(),
            'user_type' => UserType::SALES_EXECUTIVE,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'contact_number' => $contactNumber,
            'status' => 1,
        ]);
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
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_allocation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_start_time')->default('10:30');
            $table->string('office_end_time')->default('19:30');
            $table->integer('popup_interval_minutes')->default(120);
            $table->integer('minimum_leads_before_popup')->default(1);
            $table->boolean('auto_allocation_enabled')->default(true);
            $table->string('allocation_method')->default('balanced');
            $table->timestamps();
        });

        LeadAllocationSetting::create([
            'office_start_time' => '10:30',
            'office_end_time' => '19:30',
            'popup_interval_minutes' => 120,
            'minimum_leads_before_popup' => 1,
            'auto_allocation_enabled' => true,
            'allocation_method' => 'balanced',
        ]);

        Schema::create('lead_allocation_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('assigned_to')->nullable();
            $table->string('status')->default('queued');
            $table->string('reason')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique('lead_id');
        });

        Schema::create('lead_allocation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('salesperson_id')->nullable();
            $table->string('action');
            $table->string('result')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('assignment_mode')->default('balanced');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ivr_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vi_agent_name')->nullable();
            $table->string('vi_agent_number')->nullable();
            $table->uuid('mapped_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id')->unique();
            $table->uuid('ivr_call_type_id')->nullable();
            $table->string('call_type_code')->nullable();
            $table->string('dni')->nullable();
            $table->string('cli')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('raw_dtmf')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_number')->nullable();
            $table->string('dial_status')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('call_end_at')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->integer('og_duration_sec')->nullable();
            $table->text('voice_url')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->default('received');
            $table->text('processing_message')->nullable();
            $table->timestamp('initial_followup_created_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }
}
