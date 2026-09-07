<?php

namespace Tests\Unit;

use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerRefundProofOptionalTest extends TestCase
{
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
    }

    public function test_refund_page_accounts_user_can_save_customer_refund_without_proof(): void
    {
        $accountsUser = $this->createUser(
            UserType::ACCOUNTS_MANAGER
        );
        $followup = $this->createCancelledFollowup();

        $this
            ->actingAs($accountsUser)
            ->postJson(
                route('admin.refunds.store'),
                [
                    'followup_id' => $followup->id,
                    'original_amount' => '5000.00',
                    'refund_amount' => '2500.00',
                    'refund_type' => 'UPI',
                    'refund_date' => '2026-09-07',
                    'refund_reason' => 'Customer refund without proof.',
                ]
            )
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('lead_refunds', [
            'lead_followup_id' => $followup->id,
            'refund_amount' => 2500,
            'refund_type' => 'UPI',
            'refund_proof' => null,
        ]);
    }

    public function test_ride_status_accounts_user_can_save_customer_refund_without_proof(): void
    {
        $accountsUser = $this->createUser(
            UserType::ACCOUNTS_MANAGER
        );
        $followup = $this->createCancelledFollowup();

        $this
            ->actingAs($accountsUser)
            ->postJson(
                route(
                    'admin.rides.ride-status.save-refund',
                    Str::uuid()
                ),
                [
                    'followup_id' => $followup->id,
                    'original_amount' => '5000.00',
                    'refund_amount' => '2500.00',
                    'refund_type' => 'UPI',
                    'refund_date' => '2026-09-07',
                    'refund_reason' => 'Customer refund without proof.',
                ]
            )
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('lead_refunds', [
            'lead_followup_id' => $followup->id,
            'refund_amount' => 2500,
            'refund_type' => 'UPI',
            'refund_proof' => null,
        ]);
    }

    private function createUser(string $role): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $role,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $role . ' User',
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createCancelledFollowup(): LeadFollowup
    {
        return LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => (string) Str::uuid(),
            'status' => 2,
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

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->string('refund_type')->nullable();
            $table->dateTime('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('refund_invoice_id')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }
}
