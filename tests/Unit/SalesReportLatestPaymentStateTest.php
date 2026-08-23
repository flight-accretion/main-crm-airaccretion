<?php

namespace Tests\Unit;

use App\Exports\SalesReportExport;
use App\Http\Controllers\ReportController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

class SalesReportLatestPaymentStateTest extends TestCase
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

    public function test_sales_report_screen_uses_latest_payment_amounts_after_discount_is_removed(): void
    {
        $admin = $this->createUser(UserType::SUPER_ADMIN, 'Admin User');
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Samarpit Sharma');
        $this->createDiscountRemovedScenario($salesperson);

        $this->actingAs($admin);

        $response = app(ReportController::class)
            ->salesReport(Request::create('/admin/report/sales', 'GET', [
                'month' => 8,
                'year' => 2026,
                'representative_user_id' => $salesperson->id,
            ]));

        $this->assertInstanceOf(View::class, $response);

        $row = $response->getData()['salesData']->first();

        $this->assertNotNull($row);
        $this->assertSame(14000.0, (float) $row->received_amount);
        $this->assertSame(14000.0, (float) $row->total_amount);
        $this->assertSame(0.0, (float) $row->pending_amount);
        $this->assertSame('Full Payment Received', $row->ride_status);
    }

    public function test_sales_report_export_uses_latest_payment_amounts_after_discount_is_removed(): void
    {
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Samarpit Sharma');
        $this->createDiscountRemovedScenario($salesperson);

        $row = (new SalesReportExport([
            'month' => 8,
            'year' => 2026,
            'representative_user_id' => $salesperson->id,
        ]))->collection()->first();

        $this->assertNotNull($row);
        $this->assertSame(14000.0, (float) $row->received_amount);
        $this->assertSame(14000.0, (float) $row->total_amount);
        $this->assertSame(0.0, (float) $row->pending_amount);
        $this->assertSame('Full Payment Received', $row->ride_status);
    }

    private function createDiscountRemovedScenario(User $salesperson): void
    {
        $client = Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Mahendra soni',
            'email' => 'ssoni3035@gmail.com',
            'contact_number' => '+91-7597777755',
            'status' => 1,
        ]);

        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $salesperson->id,
            'service_ids' => [],
            'product_ids' => [],
            'number_of_passengers' => 4,
            'created_at' => '2026-08-23 12:00:00',
            'updated_at' => '2026-08-23 12:00:00',
        ]);

        LeadRide::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-08-25 09:00:00',
            'to_date' => '2026-08-25 10:00:00',
            'from_place' => 'Gangtok',
            'to_place' => 'Bagdogra',
        ]);

        $discountedApprovedFollowup = LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 8,
            'followup_note' => 'Payment Approved (from history)',
            'service_amount' => 14000,
            'discount_amount' => 10500,
            'total_amount' => 3500,
            'received_amount' => 3500,
            'service_ids' => [],
            'extra_service_ids' => [],
            'service_details' => [
                [
                    'type' => 'service',
                    'name' => 'Gangtok To Bagdogra By Helicopter - 4 people',
                    'original_amount' => 14000,
                    'discount_amount' => 10500,
                    'final_amount' => 3500,
                ],
            ],
            'created_at' => '2026-08-23 13:31:29',
            'updated_at' => '2026-08-23 13:31:29',
        ]);

        PaymentAuditTrail::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $discountedApprovedFollowup->id,
            'paid_amount' => 3500,
            'paid_date' => '2026-08-23 13:31:29',
            'payment_method' => 'Online Payment',
            'payment_status' => 1,
        ]);

        $fullPaymentFollowup = LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 3,
            'followup_note' => 'Full payment received after removing discount',
            'service_amount' => 14000,
            'discount_amount' => 0,
            'total_amount' => 14000,
            'received_amount' => 14000,
            'service_ids' => [],
            'extra_service_ids' => [],
            'service_details' => [
                [
                    'type' => 'service',
                    'name' => 'Gangtok To Bagdogra By Helicopter - 4 people',
                    'original_amount' => 14000,
                    'discount_amount' => 0,
                    'final_amount' => 14000,
                ],
            ],
            'created_at' => '2026-08-23 13:33:57',
            'updated_at' => '2026-08-23 13:33:57',
        ]);

        PaymentAuditTrail::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $fullPaymentFollowup->id,
            'paid_amount' => 10500,
            'paid_date' => '2026-08-23 13:33:57',
            'payment_method' => 'Online Payment',
            'payment_status' => 1,
        ]);
    }

    private function createUser(string $userTypeName, string $name): User
    {
        $userType = UserType::forceCreate([
            'id' => (string) Str::uuid(),
            'user_type' => $userTypeName,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->uuid('parent_id')->nullable();
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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->nullable();
            $table->string('city')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->uuid('country_id')->nullable();
            $table->uuid('city_id')->nullable();
            $table->text('address')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->string('total_time')->nullable();
            $table->boolean('is_tba')->default(false);
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->integer('followup_recording_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->timestamp('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('narration')->nullable();
            $table->integer('payment_status')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->decimal('service_amount', 15, 2)->default(0);
            $table->decimal('fees_percent', 8, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('extra_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('extra_service');
            $table->decimal('extra_service_amount', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('sales_executive_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('manager_id')->nullable();
            $table->uuid('sales_executive_id')->nullable();
            $table->timestamp('assigned_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}
