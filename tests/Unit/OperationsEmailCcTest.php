<?php

namespace Tests\Unit;

use App\Mail\BookingConfirmationMail;
use App\Mail\RefundMail;
use App\Mail\VoucherMail;
use App\Services\BookingConfirmationEmailService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationsEmailCcTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        Schema::create('notification_masters', function (Blueprint $table) {
            $table->id();
            $table->string('email_id')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('contact_country_code')->nullable();
            $table->boolean('status');
            $table->timestamps();
        });
        DB::table('notification_masters')->insert([
            ['email_id' => 'active@example.test', 'status' => 1],
            ['email_id' => ' ACTIVE@example.test ', 'status' => 1],
            ['email_id' => 'inactive@example.test', 'status' => 0],
            ['email_id' => 'invalid-address', 'status' => 1],
            ['email_id' => 'customer@example.test', 'status' => 1],
        ]);
    }

    public function test_voucher_copies_only_active_valid_unique_notification_recipients(): void
    {
        $mail = new VoucherMail('emails.voucher', 'Voucher', []);
        $mail->to('customer@example.test')->cc('agent@example.test');
        $mail->build();
        $mail->build();
        $this->assertTrue($mail->hasCc('active@example.test'));
        $this->assertTrue($mail->hasCc('agent@example.test'));
        $this->assertFalse($mail->hasCc('inactive@example.test'));
        $this->assertFalse($mail->hasCc('customer@example.test'));
        $this->assertCount(2, $mail->cc);
    }

    public function test_existing_to_cc_and_bcc_recipients_are_not_duplicated(): void
    {
        foreach (['to', 'cc', 'bcc'] as $type) {
            $mail = new VoucherMail('emails.voucher', 'Voucher', []);
            $mail->to('customer@example.test')->{$type}('active@example.test');
            $mail->build();
            $this->assertCount($type === 'cc' ? 1 : 0, $mail->cc);
        }
    }

    public function test_existing_refund_payment_booking_registration_and_reminders_do_not_gain_automatic_cc(): void
    {
        foreach ([
            new RefundMail('emails.refund-customer', 'Refund', []),
            new RefundMail('emails.refund-vendor', 'Vendor payment', []),
            new BookingConfirmationMail('Booking', 'Details'),
            new VoucherMail('emails.registration_link', 'Registration', []),
            new VoucherMail('emails.ride_reminder', 'Reminder', []),
            new VoucherMail('emails.booking_reminder_fallback', 'Reminder', []),
        ] as $mail) {
            $mail->cc('sales@example.test')->build();
            $this->assertTrue($mail->hasCc('sales@example.test'));
            $this->assertCount(1, $mail->cc);
        }
    }

    public function test_booking_cc_includes_sender_and_preserves_sales_and_manager_without_customer_duplicates(): void
    {
        $method = new \ReflectionMethod(BookingConfirmationEmailService::class, 'bookingConfirmationCcEmails');
        $method->setAccessible(true);
        $service = app(BookingConfirmationEmailService::class);
        $variables = ['agent_email' => 'rep@example.test', 'sales_email' => 'REP@example.test', 'manager_email' => 'manager@example.test'];
        $this->assertSame(['rep@example.test', 'manager@example.test', 'sender@example.test'],
            $method->invoke($service, $variables, 'customer@example.test', ' SENDER@example.test '));
        $this->assertSame(['rep@example.test', 'manager@example.test'],
            $method->invoke($service, $variables, 'customer@example.test', 'customer@example.test'));
    }
}
