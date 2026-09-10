<?php

namespace Tests\Unit;

use App\Mail\BookingConfirmationMail;
use App\Models\BookingEmailTemplate;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use App\Services\BookingConfirmationEmailService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingConfirmationEmailServiceTest extends TestCase
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
        Carbon::setTestNow('2026-09-07 12:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_sends_booking_confirmation_email_with_registration_link_notes_and_agent_reply_to(): void
    {
        Mail::fake();

        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sourav Namdeo',
            'sourav@example.test',
            '9000000001'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Aahanaa',
            'email' => 'aahanaa@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'product' => 'Helicopter Joyride',
            'status' => 1,
            'booking_email_note' => 'Product note: Please reach 30 minutes before departure.',
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Far East 26 Joyride 30 Minutes',
            'description' => 'Joyride service',
            'service_amount' => 50000,
            'fees_percent' => 0,
            'terms_and_conditions' => '<p>Service terms</p>',
            'product_ids' => [$product->id],
            'status' => 1,
            'booking_email_note' => 'Service note: Carry original government ID.',
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
            'product_ids' => [$product->id],
            'service_ids' => [$service->id],
            'number_of_passengers' => 1,
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-09-26 10:00:00',
            'to_date' => '2026-09-26 10:30:00',
            'from_place' => 'Mumbai',
            'to_place' => 'Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'service_ids' => json_encode([$service->id]),
            'service_amount' => 50000,
            'discount_amount' => 5000,
            'total_amount' => 45000,
            'received_amount' => 10000,
            'status' => 4,
            'followed_by' => $agent->id,
        ]);

        BookingEmailTemplate::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Booking Confirmation | {{service_name}} on {{service_date}}',
            'body' => implode(PHP_EOL, [
                'Dear {{customer_name}},',
                'Service Name: {{service_name}}',
                'Date of Service: {{service_date}}',
                'Timing: {{timing}}',
                'Duration: {{duration}}',
                'Passengers: {{passengers}}',
                'Total Service Cost: {{total_amount}}',
                'Advance Payment Due Now: {{advance_amount}}',
                'Balance Amount: {{balance_amount}}',
                'Balance Due By: {{balance_due_by}}',
                '{{product_service_notes}}',
                'Registration: {{registration_link}}',
                'Regards, {{agent_name}} {{agent_email}} {{agent_phone}}',
            ]),
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->sendForLead($lead->fresh(), $agent, [
                'payment_mode' => 'payment_due',
                'advance_amount' => 10000,
            ]);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['registration_link']);

        Mail::assertSent(
            BookingConfirmationMail::class,
            function (BookingConfirmationMail $mail) use ($agent) {
                $this->assertSame(
                    'sourav@example.test',
                    $mail->replyToAddress
                );
                $this->assertStringContainsString(
                    'Far East 26 Joyride 30 Minutes',
                    $mail->subjectLine
                );
                $this->assertStringContainsString(
                    'Dear Aahanaa',
                    $mail->body
                );
                $this->assertStringContainsString(
                    'Saturday, 26th September 2026',
                    $mail->body
                );
                $this->assertStringContainsString(
                    '10:00 AM - 10:30 AM IST',
                    $mail->body
                );
                $this->assertStringContainsString('30 Minutes', $mail->body);
                $this->assertStringContainsString('₹45,000.00', $mail->body);
                $this->assertStringContainsString('₹10,000.00', $mail->body);
                $this->assertStringContainsString(
                    'Product note: Please reach 30 minutes before departure.',
                    $mail->body
                );
                $this->assertStringContainsString(
                    'Service note: Carry original government ID.',
                    $mail->body
                );
                $this->assertStringContainsString($agent->name, $mail->body);
                $this->assertStringNotContainsString('{{', $mail->body);

                return true;
            }
        );

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followup_note' => 'Booking confirmation email sent to aahanaa@example.test with passenger registration link.',
            'status' => 1,
            'followed_by' => $agent->id,
        ]);

        $this->assertDatabaseHas('lead_passengers', [
            'lead_id' => $lead->id,
        ]);
    }

    public function test_it_rejects_leads_without_valid_customer_email(): void
    {
        Mail::fake();

        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent',
            'agent@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'No Email Customer',
            'email' => null,
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->sendForLead($lead->fresh(), $agent);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Customer email is not available or invalid.',
            $result['message']
        );

        Mail::assertNothingSent();
        $this->assertSame(0, LeadFollowup::count());
    }

    public function test_preview_uses_from_to_datetime_range_for_timing_when_duration_is_available(): void
    {
        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent',
            'agent@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Duration Customer',
            'email' => 'duration@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Helicopter Charter',
            'description' => 'Charter service',
            'service_amount' => 75000,
            'fees_percent' => 0,
            'product_ids' => [],
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
            'service_ids' => [$service->id],
            'number_of_passengers' => 3,
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-09-03 00:00:00',
            'to_date' => '2026-09-03 05:00:00',
            'from_place' => 'Mumbai',
            'to_place' => 'Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BookingEmailTemplate::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Booking Confirmation | {{service_name}}',
            'body' => implode(PHP_EOL, [
                'Service Name: {{service_name}}',
                'Date of Service: {{service_date}}',
                'Duration: {{duration}}',
                'Timing: {{timing}}',
            ]),
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead->fresh(), $agent);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString(
            'Duration: 300 Minutes',
            $result['body_before_payment']
        );
        $this->assertStringContainsString(
            'Timing: 12:00 AM - 5:00 AM IST',
            $result['body_before_payment']
        );
    }

    private function createUser(
        string $role,
        string $name,
        string $email,
        ?string $phone = null
    ): User {
        $type = UserType::firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'status' => 1,
            ]
        );

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => $email,
            'contact_number' => $phone,
            'password' => 'secret',
            'user_type_id' => $type->id,
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
            $table->string('contact_number')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->uuid('vendor_id')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_airambulance')->default(false);
            $table->json('user_ids')->nullable();
            $table->text('booking_email_note')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->string('description')->nullable();
            $table->integer('service_amount')->default(0);
            $table->decimal('fees_percent', 5, 2)->default(0);
            $table->text('terms_and_conditions')->nullable();
            $table->json('product_ids')->nullable();
            $table->text('booking_email_note')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('crm_lead_code')->nullable();
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
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('registration_token')->nullable();
            $table->string('registration_slug')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_handler')->default(false);
            $table->boolean('is_additional_person')->default(false);
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->dateTime('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('service_amount', 12, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->text('body');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
