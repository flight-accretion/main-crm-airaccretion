<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Services\LeadAiLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadAiLifecycleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]
        );

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_only_approved_positive_payment_marks_lead_booked_or_closed(): void
    {
        $cases = [
            ['paid_amount' => null, 'payment_status' => null, 'expected' => false],
            ['paid_amount' => 100, 'payment_status' => 0, 'expected' => false],
            ['paid_amount' => 100, 'payment_status' => 2, 'expected' => false],
            ['paid_amount' => 0, 'payment_status' => 1, 'expected' => false],
            ['paid_amount' => 1, 'payment_status' => 1, 'expected' => true],
        ];

        foreach ($cases as $case) {
            $lead = $this->leadWithPayment(
                $case['paid_amount'],
                $case['payment_status']
            );

            $this->assertSame(
                $case['expected'],
                app(LeadAiLifecycleService::class)
                    ->isBookedOrClosed($lead),
                json_encode($case)
            );
        }
    }

    private function leadWithPayment(
        ?float $paidAmount,
        ?int $paymentStatus
    ): Lead {
        $lead = Lead::create([
            'id' => (string) Str::uuid(),
        ]);

        $followup = LeadFollowup::withoutEvents(function () use ($lead) {
            $followup = new LeadFollowup([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'followup_note' => 'Payment status check.',
                'status' => 1,
            ]);

            $followup->created_at = Carbon::parse('2026-09-08 10:00:00');
            $followup->updated_at = Carbon::parse('2026-09-08 10:00:00');
            $followup->save();

            return $followup;
        });

        if ($paidAmount !== null) {
            PaymentAuditTrail::create([
                'id' => (string) Str::uuid(),
                'lead_followup_id' => $followup->id,
                'paid_amount' => $paidAmount,
                'payment_status' => $paymentStatus,
            ]);
        }

        return $lead;
    }

    private function createSchema(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->string('contact_outcome', 30)->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->unsignedInteger('followup_recording_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->integer('payment_status')->nullable();
            $table->timestamps();
        });
    }
}
