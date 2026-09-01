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

    private function createLead(User $owner): Lead
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
            'representative_user_id' => $owner->id,
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
    }
}
