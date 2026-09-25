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
use Illuminate\Support\Facades\Http;
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
                $body = str_replace(["\r\n", "\r"], "\n", $mail->body);

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
                $this->assertStringContainsString('Time: 10:00 AM', $body);
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

    public function test_send_also_sends_booking_confirmation_whatsapp_message(): void
    {
        Mail::fake();

        config()->set(
            'whatcrm.send_message_url',
            'https://web.airaccretion.com/api/v1/send-message'
        );
        config()->set('whatcrm.send_message_token', 'booking-token');
        config()->set('whatcrm.default_country_code', '91');
        config()->set(
            'services.booking_whatsapp.company_number',
            '+91 95753 40786'
        );

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.BOOKING-CONFIRM-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Booking Agent',
            'booking.agent@example.test',
            '9000000002'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'WhatsApp Customer',
            'email' => 'whatsapp-customer@example.test',
            'contact_number' => '9876543210',
            'alternate_number' => '+91-9123456780',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Helicopter Joyride 30 Minutes',
            'description' => 'Joyride service',
            'service_amount' => 50000,
            'fees_percent' => 0,
            'product_ids' => [],
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
            'service_ids' => [$service->id],
            'number_of_passengers' => 2,
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

        BookingEmailTemplate::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Booking Confirmation | {{service_name}}',
            'body' => implode(PHP_EOL, [
                'Dear {{customer_name}},',
                'Service Name: {{service_name}}',
                'Date of Service: {{service_date}}',
                'Time: {{time}}',
                'Registration: {{registration_link}}',
            ]),
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->sendForLead($lead->fresh(), $agent, [
                'payment_mode' => 'payment_due',
                'advance_amount' => 10000,
            ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['whatsapp_sent']);

        Http::assertSent(function ($request) use ($result) {
            $payload = $request->data();
            $body = data_get($payload, 'messageObject.text.body');

            return str_contains(
                    $request->url(),
                    'https://web.airaccretion.com/api/v1/send-message?token=booking-token'
                )
                && data_get($payload, 'messageObject.to') === '919123456780'
                && data_get($payload, 'messageObject.type') === 'text'
                && str_contains($body, 'Your booking confirmation has been sent')
                && str_contains($body, '+91 95753 40786')
                && str_contains($body, $result['short_link'] ?: $result['registration_link']);
        });

        $this->assertDatabaseHas('whatsapp_contacts', [
            'name' => 'WhatsApp Customer',
            'normalized_phone' => '9123456780',
        ]);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'lead_id' => $lead->id,
            'assigned_user_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid.BOOKING-CONFIRM-1',
            'direction' => 'outgoing',
            'sender_user_id' => $agent->id,
            'message_type' => 'text',
        ]);
    }

    public function test_preview_renders_booking_bank_details_from_config(): void
    {
        config()->set('services.booking_bank.account_name', 'Accretion Aviation Pvt Ltd');
        config()->set('services.booking_bank.bank_name', 'HDFC Bank');
        config()->set('services.booking_bank.account_number', '50200012345678');
        config()->set('services.booking_bank.ifsc', 'HDFC0001234');
        config()->set('services.booking_bank.branch', 'Indore');

        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent',
            'agent@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bank Detail Customer',
            'email' => 'bank-detail@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
            'number_of_passengers' => 1,
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead->fresh(), $agent);

        $body = $result['body_before_payment']
            . "\n"
            . $result['body_after_payment'];

        $this->assertTrue($result['success']);
        $this->assertStringContainsString(
            'Account Name: Accretion Aviation Pvt Ltd',
            $body
        );
        $this->assertStringContainsString('Bank Name: HDFC Bank', $body);
        $this->assertStringContainsString(
            'Account Number: 50200012345678',
            $body
        );
        $this->assertStringContainsString('IFSC Code: HDFC0001234', $body);
        $this->assertStringContainsString('Branch: Indore', $body);
    }

    public function test_preview_uses_total_time_fallback_duration_and_customer_ride_time(): void
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
            'from_date' => '2026-09-03 10:15:00',
            'to_date' => '2026-09-03 10:15:00',
            'from_place' => 'Mumbai',
            'to_place' => 'Mumbai',
            'total_time' => '2.50',
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

        $body = str_replace(["\r\n", "\r"], "\n", $result['body_before_payment']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Duration: 2 Hours 30 Min', $body);
        $this->assertStringContainsString('Time: 10:15 AM', $body);
    }

    public function test_preview_uses_first_ride_customer_time_for_multi_ride_booking(): void
    {
        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent',
            'agent@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Multi Ride Customer',
            'email' => 'multi-ride@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Helicopter Charter 7 Hours',
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

        foreach (
            [
                ['2026-09-14 15:00:00', '2026-09-14 17:00:00', 'Indore', 'Mumbai'],
                ['2026-09-15 02:00:00', '2026-09-15 05:00:00', 'Mumbai', 'Goa'],
                ['2026-09-16 01:00:00', '2026-09-16 03:00:00', 'Goa', 'Indore'],
            ]
            as $ride
        ) {
            DB::table('lead_rides')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $ride[0],
                'to_date' => $ride[1],
                'from_place' => $ride[2],
                'to_place' => $ride[3],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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

        $body = str_replace(["\r\n", "\r"], "\n", $result['body_before_payment']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Duration: 7 Hours', $body);
        $this->assertStringContainsString('Time: 03:00 PM', $body);
        $this->assertStringNotContainsString('2. 15 Sep 2026', $body);
    }

    public function test_preview_shows_tba_for_multi_ride_segment_without_confirmed_time(): void
    {
        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent',
            'agent@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'TBA Customer',
            'email' => 'tba@example.test',
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
            'number_of_passengers' => 2,
        ]);

        foreach (
            [
                [
                    'from_date' => '2026-09-14 00:00:00',
                    'to_date' => '2026-09-14 00:00:00',
                    'from_place' => 'Indore',
                    'to_place' => 'Mumbai',
                    'is_tba' => true,
                ],
                [
                    'from_date' => '2026-09-15 00:00:00',
                    'to_date' => '2026-09-15 00:00:00',
                    'from_place' => 'Mumbai',
                    'to_place' => 'Goa',
                    'is_tba' => true,
                ],
                [
                    'from_date' => '2026-09-16 00:00:00',
                    'to_date' => '2026-09-16 00:00:00',
                    'from_place' => 'Goa',
                    'to_place' => 'Indore',
                    'is_tba' => true,
                ],
            ]
            as $ride
        ) {
            DB::table('lead_rides')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $ride['from_date'],
                'to_date' => $ride['to_date'],
                'from_place' => $ride['from_place'],
                'to_place' => $ride['to_place'],
                'is_tba' => $ride['is_tba'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        BookingEmailTemplate::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Booking Confirmation | {{service_name}}',
            'body' => 'Timing: {{timing}}',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead->fresh(), $agent);

        $this->assertTrue($result['success']);
        $body = str_replace(["\r\n", "\r"], "\n", $result['body_before_payment']);

        $this->assertStringContainsString(
            'Time: TBA',
            $body
        );
        $this->assertStringNotContainsString("2. TBA", $body);
    }

    public function test_preview_defaults_to_tba_when_ride_has_only_the_default_noon_time(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 12:00:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent);

        $body = str_replace(["\r\n", "\r"], "\n", $result['body_before_payment']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Time: TBA', $body);
        $this->assertStringContainsString('Duration: TBA', $body);

        $this->assertTrue($result['time_is_tba']);
        $this->assertSame('12:00 PM', $result['voucher_time']);
        $this->assertSame('12:00', $result['email_time']);
        $this->assertStringContainsString('default 12:00', $result['time_note']);
        $this->assertTrue($result['duration_is_tba']);
        $this->assertSame('', $result['duration']);
        $this->assertSame('none', $result['duration_source']);
    }

    public function test_preview_defaults_to_tba_when_voucher_has_no_time(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 00:00:00',
            'to_date' => '2026-10-04 00:00:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent);

        $this->assertStringContainsString('Time: TBA', $result['body_before_payment']);
        $this->assertTrue($result['time_is_tba']);
        $this->assertSame('', $result['voucher_time']);
        $this->assertSame('', $result['email_time']);
    }

    public function test_preview_uses_voucher_time_and_ride_date_duration_when_real(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 17:00:00',
            'to_date' => '2026-10-04 18:30:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent);

        $this->assertStringContainsString('Time: 05:00 PM', $result['body_before_payment']);
        $this->assertStringContainsString('Duration: 1 Hour 30 Min', $result['body_before_payment']);
        $this->assertFalse($result['time_is_tba']);
        $this->assertSame('17:00', $result['email_time']);
        $this->assertSame('1 Hour 30 Min', $result['duration']);
        $this->assertSame('ride dates', $result['duration_source']);
        $this->assertFalse($result['duration_is_tba']);
    }

    public function test_multi_day_ride_dates_are_suggested_but_stay_tba_until_sender_confirms(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-07-22 12:00:00',
            'to_date' => '2026-09-22 12:00:00',
        ]);

        $service = app(BookingConfirmationEmailService::class);

        $default = $service->previewForLead($lead, $agent);

        // A 63 day travel window must not be sent as the service duration.
        $this->assertStringContainsString('Duration: TBA', $default['body_before_payment']);
        $this->assertTrue($default['duration_is_tba']);
        $this->assertSame('63 Days', $default['duration']);
        $this->assertSame('ride dates', $default['duration_source']);
        $this->assertNotSame('', $default['duration_note']);

        $confirmed = $service->previewForLead($lead, $agent, [
            'email_duration_tba' => false,
            'email_duration' => '63 Days',
        ]);

        $this->assertStringContainsString('Duration: 63 Days', $confirmed['body_before_payment']);
        $this->assertFalse($confirmed['duration_is_tba']);
    }

    public function test_sender_can_untick_tba_type_a_time_and_type_a_duration(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 12:00:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent, [
                'email_time_tba' => false,
                'email_time' => '15:30',
                'email_duration_tba' => false,
                'email_duration' => '45 Minutes',
            ]);

        $body = $result['body_before_payment'];

        $this->assertStringContainsString('Time: 03:30 PM', $body);
        $this->assertStringContainsString('Duration: 45 Minutes', $body);
        $this->assertFalse($result['time_is_tba']);
        $this->assertSame('15:30', $result['email_time']);
        $this->assertSame('45 Minutes', $result['duration']);
        $this->assertSame('entered by you', $result['duration_source']);
    }

    public function test_sender_can_tick_tba_over_a_real_time_and_duration(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 17:00:00',
            'to_date' => '2026-10-04 18:30:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent, [
                'email_time_tba' => true,
                'email_duration_tba' => true,
            ]);

        $this->assertStringContainsString('Time: TBA', $result['body_before_payment']);
        $this->assertStringContainsString('Duration: TBA', $result['body_before_payment']);
        $this->assertTrue($result['time_is_tba']);
        $this->assertTrue($result['duration_is_tba']);
    }

    public function test_unticked_tba_without_any_time_never_prints_a_blank(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 00:00:00',
            'to_date' => '2026-10-04 00:00:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->previewForLead($lead, $agent, [
                'email_time_tba' => false,
                'email_duration_tba' => false,
            ]);

        $this->assertStringContainsString('Time: TBA', $result['body_before_payment']);
        $this->assertStringContainsString('Duration: TBA', $result['body_before_payment']);
    }

    public function test_service_name_duration_is_read_correctly(): void
    {
        foreach (
            [
                'Private Plane Ride In Mumbai 30 Minutes' => '30 Minutes',
                'Private Plane Ride In Mumbai 60 minutes' => '1 Hour',
                'Helicopter Ride In Mumbai - 15 minutes each ride' => '15 Minutes',
                'Boat Cruise 1.5 hours' => '1 Hour 30 Min',
                'Vaishnodevi Yatra By Helicopter 2 Night and 3 days' => '2 Nights 3 Days',
            ]
            as $serviceName => $expected
        ) {
            [$lead, $agent] = $this->leadWithRide([
                'from_date' => '2026-10-04 12:00:00',
                'to_date' => '2026-10-04 12:00:00',
            ], $serviceName);

            $result = app(BookingConfirmationEmailService::class)
                ->previewForLead($lead, $agent);

            $this->assertStringContainsString(
                'Duration: ' . $expected,
                $result['body_before_payment'],
                $serviceName
            );
            $this->assertSame('service name', $result['duration_source'], $serviceName);
        }
    }

    public function test_final_send_uses_the_senders_time_and_duration_choices(): void
    {
        Mail::fake();

        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 12:00:00',
        ]);

        $result = app(BookingConfirmationEmailService::class)
            ->sendForLead($lead, $agent, [
                'payment_mode' => 'payment_due',
                'total_amount' => 10000,
                'advance_amount' => 5000,
                'email_time_tba' => false,
                'email_time' => '09:15',
                'email_duration_tba' => false,
                'email_duration' => '2 Hours',
            ]);

        $this->assertTrue($result['success']);

        Mail::assertSent(
            BookingConfirmationMail::class,
            function (BookingConfirmationMail $mail) {
                $body = str_replace(["\r\n", "\r"], "\n", $mail->body);

                $this->assertStringContainsString('Time: 09:15 AM', $body);
                $this->assertStringContainsString('Duration: 2 Hours', $body);

                return true;
            }
        );
    }

    public function test_send_endpoint_blocks_unticked_tba_without_time_or_duration(): void
    {
        Mail::fake();

        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 00:00:00',
            'to_date' => '2026-10-04 00:00:00',
        ]);

        $base = [
            'payment_mode' => 'payment_due',
            'total_amount' => 10000,
            'advance_amount' => 5000,
        ];

        $url = route('admin.leads.booking-confirmation-email.send', $lead);

        $this->actingAs($agent)
            ->postJson($url, $base + [
                'email_time_tba' => false,
                'email_time' => '',
                'email_duration_tba' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_time']);

        $this->actingAs($agent)
            ->postJson($url, $base + [
                'email_time_tba' => true,
                'email_duration_tba' => false,
                'email_duration' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email_duration']);

        Mail::assertNothingSent();

        $this->actingAs($agent)
            ->postJson($url, $base + [
                'email_time_tba' => true,
                'email_duration_tba' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(
            BookingConfirmationMail::class,
            function (BookingConfirmationMail $mail) {
                $this->assertStringContainsString('Time: TBA', $mail->body);
                $this->assertStringContainsString('Duration: TBA', $mail->body);

                return true;
            }
        );
    }

    public function test_preview_endpoint_passes_the_senders_choices_through(): void
    {
        [$lead, $agent] = $this->leadWithRide([
            'from_date' => '2026-10-04 12:00:00',
            'to_date' => '2026-10-04 12:00:00',
        ]);

        $url = route('admin.leads.booking-confirmation-email.send', $lead);

        $this->actingAs($agent)
            ->postJson($url, ['preview_only' => true])
            ->assertOk()
            ->assertJsonPath('time_is_tba', true)
            ->assertJsonPath('voucher_time', '12:00 PM');

        $response = $this->actingAs($agent)
            ->postJson($url, [
                'preview_only' => true,
                'email_time_tba' => false,
                'email_time' => '10:45',
                'email_duration_tba' => false,
                'email_duration' => '30 Minutes',
            ])
            ->assertOk()
            ->assertJsonPath('time_is_tba', false);

        $this->assertStringContainsString('Time: 10:45 AM', $response->json('body_before_payment'));
        $this->assertStringContainsString('Duration: 30 Minutes', $response->json('body_before_payment'));
    }

    /**
     * Lead with one ride and a booking template that prints time and duration.
     *
     * @return array{0: Lead, 1: User}
     */
    private function leadWithRide(
        array $ride,
        string $serviceName = 'Helicopter Charter'
    ): array {
        $agent = $this->createUser(
            UserType::SALES_EXECUTIVE,
            'Sales Agent ' . Str::random(4),
            'agent' . Str::random(6) . '@example.test'
        );

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ride Customer',
            'email' => 'ride@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => $serviceName,
            'description' => 'Service',
            'service_amount' => 10000,
            'fees_percent' => 0,
            'product_ids' => [],
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $agent->id,
            'service_ids' => [$service->id],
            'number_of_passengers' => 2,
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => $ride['from_date'],
            'to_date' => $ride['to_date'],
            'from_place' => 'Mumbai',
            'to_place' => 'Mumbai',
            'is_tba' => $ride['is_tba'] ?? false,
            'total_time' => $ride['total_time'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BookingEmailTemplate::query()->delete();

        BookingEmailTemplate::create([
            'id' => (string) Str::uuid(),
            'subject' => 'Booking Confirmation | {{service_name}}',
            'body' => implode(PHP_EOL, [
                'Timing: {{timing}}',
                'Duration: {{duration}}',
            ]),
        ]);

        return [$lead->fresh(), $agent];
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
            $table->boolean('is_tba')->default(false);
            $table->decimal('total_time', 5, 2)->nullable();
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

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('normalized_phone', 30)->unique();
            $table->string('raw_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('whatcrm_chat_id')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('lead_followup_id')->nullable();
            $table->uuid('ai_reply_batch_id')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction', 20);
            $table->string('sender_type', 30);
            $table->uuid('sender_user_id')->nullable();
            $table->string('message_type', 30)->default('text');
            $table->text('body')->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->timestamp('message_at')->nullable();
            $table->timestamp('crm_read_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }
}
