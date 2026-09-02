<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadTransfer;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadTransferService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\LeadAllocationQueue;
use App\Models\LeadAuditTrail;
use App\Models\LeadAllocationLog;

class LeadTransferServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 31, 13, 30, 0));

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

    public function test_stale_accept_cancels_pending_request_before_returning_error(): void
    {
        $oldOwner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $currentOwner = $this->createUser('Sourav Namdeo', UserType::SALES_EXECUTIVE);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);
        $lead = $this->createLead($currentOwner);

        $transfer = LeadTransfer::create([
            'lead_id' => $lead->id,
            'from_user_id' => $oldOwner->id,
            'to_user_id' => $requester->id,
            'requested_by' => $requester->id,
            'status' => 'pending',
            'reason' => 'Lead access requested.',
        ]);

        try {
            app(LeadTransferService::class)->accept($transfer, $superAdmin);
            $this->fail('Expected stale transfer approval to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Lead ownership has already changed. This request is no longer valid.',
                $exception->errors()['transfer'][0]
            );
        }

        $transfer->refresh();

        $this->assertSame('cancelled', $transfer->status);
        $this->assertSame($superAdmin->id, $transfer->responded_by);
        $this->assertNotNull($transfer->responded_at);
        $this->assertSame(
            'Transfer automatically cancelled because lead ownership changed before approval.',
            $transfer->response_note
        );
        $this->assertSame($currentOwner->id, $lead->fresh()->representative_user_id);
    }

    public function test_stale_reject_cancels_pending_request_before_returning_error(): void
    {
        $oldOwner = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);
        $currentOwner = $this->createUser('Sourav Namdeo', UserType::SALES_EXECUTIVE);
        $requester = $this->createUser('Samarpit Sharma', UserType::SALES_EXECUTIVE);
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);
        $lead = $this->createLead($currentOwner);

        $transfer = LeadTransfer::create([
            'lead_id' => $lead->id,
            'from_user_id' => $oldOwner->id,
            'to_user_id' => $requester->id,
            'requested_by' => $requester->id,
            'status' => 'pending',
            'reason' => 'Lead access requested.',
        ]);

        try {
            app(LeadTransferService::class)->reject($transfer, $superAdmin, 'Not approved.');
            $this->fail('Expected stale transfer rejection to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Lead ownership has already changed. This request is no longer valid.',
                $exception->errors()['transfer'][0]
            );
        }

        $transfer->refresh();

        $this->assertSame('cancelled', $transfer->status);
        $this->assertSame($superAdmin->id, $transfer->responded_by);
        $this->assertNotNull($transfer->responded_at);
        $this->assertSame(
            'Transfer automatically cancelled because lead ownership changed before rejection.',
            $transfer->response_note
        );
        $this->assertSame($currentOwner->id, $lead->fresh()->representative_user_id);
    }

    private function createUser(string $name, string $role): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $role,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createLead(?User $owner = null): Lead
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Transfer Test Customer',
            'contact_number' => '919748162048',
            'status' => 1,
        ]);

        return Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' =>
             $owner ? $owner->id : null,
            'description' => 'Transfer test lead',
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
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('from_user_id');
            $table->uuid('to_user_id');
            $table->uuid('requested_by');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamps();
        });

        Schema::create(
    'lead_audit_trail',
    function (Blueprint $table) {

        $table->uuid('id')->primary();
        $table->uuid('lead_id');
        $table->string('field_name');
        $table->text('old_value')->nullable();
        $table->text('new_value')->nullable();
        $table->uuid('changed_by')->nullable();
        $table->timestamp('created_at')->nullable();
    }
);

Schema::create(
    'lead_allocation_queue',
    function (Blueprint $table) {

        $table->uuid('id')->primary();
        $table->uuid('lead_id');

        $table
            ->uuid('assigned_to')
            ->nullable();

        $table
            ->string('status')
            ->default('queued');

        $table
            ->text('reason')
            ->nullable();

        $table
            ->integer('attempt_count')
            ->default(0);

        $table
            ->timestamp('queued_at')
            ->nullable();

        $table
            ->timestamp('processed_at')
            ->nullable();

        $table->timestamps();
    }
);

Schema::create(
    'lead_allocation_logs',
    function (Blueprint $table) {

        $table->uuid('id')->primary();

        $table
            ->uuid('lead_id')
            ->nullable();

        $table
            ->uuid('salesperson_id')
            ->nullable();

        $table
            ->string('action');

        $table
            ->string('result');

        $table
            ->text('details')
            ->nullable();

        $table->timestamps();
    }
);
    }


    public function test_super_admin_can_directly_reassign_a_lead(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $requester = $this->createUser(
        'Pallavi Singh',
        UserType::SALES_MANAGER
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $pendingTransfer = LeadTransfer::create([
        'lead_id' => $lead->id,
        'from_user_id' => $oldOwner->id,
        'to_user_id' => $requester->id,
        'requested_by' => $requester->id,
        'status' => 'pending',
        'reason' => 'Lead access requested.',
    ]);

    $result = app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $superAdmin
    );

    $this->assertTrue($result['changed']);
    $this->assertFalse($result['was_queued']);

    $this->assertSame(
        $newOwner->id,
        $lead->fresh()->representative_user_id
    );

    $pendingTransfer->refresh();

    $this->assertSame(
        'cancelled',
        $pendingTransfer->status
    );

    $this->assertSame(
        $superAdmin->id,
        $pendingTransfer->responded_by
    );

    $audit = LeadAuditTrail::where(
        'lead_id',
        $lead->id
    )->first();

    $this->assertNotNull($audit);

    $this->assertSame(
        $oldOwner->id,
        $audit->old_value
    );

    $this->assertSame(
        $newOwner->id,
        $audit->new_value
    );

    $this->assertSame(
        $superAdmin->id,
        $audit->changed_by
    );
}

public function test_super_admin_direct_assignment_closes_active_queue(): void
{
    $newOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead(null);

    $queue = LeadAllocationQueue::create([
        'lead_id' => $lead->id,
        'status' => 'queued',
        'reason' => 'whatsapp_new_lead',
        'attempt_count' => 3,
        'queued_at' => now(),
    ]);

    $result = app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $superAdmin
    );

    $this->assertTrue($result['changed']);
    $this->assertTrue($result['was_queued']);

    $this->assertSame(
        'whatsapp_new_lead',
        $result['queue_reason']
    );

    $queue->refresh();

    $this->assertSame(
        'assigned',
        $queue->status
    );

    $this->assertSame(
        $newOwner->id,
        $queue->assigned_to
    );

    $this->assertNotNull(
        $queue->processed_at
    );

    $this->assertSame(
        $newOwner->id,
        $lead->fresh()->representative_user_id
    );

    $allocationLog =
    LeadAllocationLog::where(
        'lead_id',
        $lead->id
    )
    ->where(
        'salesperson_id',
        $newOwner->id
    )
    ->first();

$this->assertNotNull(
    $allocationLog
);

$this->assertSame(
    'assigned',
    $allocationLog->action
);

$this->assertSame(
    'success',
    $allocationLog->result
);

$this->assertSame(
    'Assigned manually by Super Admin from lead queue',
    $allocationLog->details
);
}

public function test_non_super_admin_cannot_directly_assign_leads(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $newOwner = $this->createUser(
        'Samarpit Sharma',
        UserType::SALES_EXECUTIVE
    );

    $salesManager = $this->createUser(
        'Sales Manager',
        UserType::SALES_MANAGER
    );

    $lead = $this->createLead($oldOwner);

    $this->expectException(
        ValidationException::class
    );

    app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $newOwner,
        $salesManager
    );
}

public function test_direct_assignment_rejects_non_sales_destination(): void
{
    $oldOwner = $this->createUser(
        'Sourav Namdeo',
        UserType::SALES_EXECUTIVE
    );

    $adminDestination = $this->createUser(
        'Admin User',
        UserType::ADMIN
    );

    $superAdmin = $this->createUser(
        'Super Admin User',
        UserType::SUPER_ADMIN
    );

    $lead = $this->createLead($oldOwner);

    $this->expectException(
        ValidationException::class
    );

    app(
        LeadTransferService::class
    )->directAssign(
        $lead,
        $adminDestination,
        $superAdmin
    );
}
}
