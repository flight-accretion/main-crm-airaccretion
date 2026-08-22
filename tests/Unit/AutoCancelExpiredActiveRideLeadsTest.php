<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoCancelExpiredActiveRideLeadsTest extends TestCase
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
        config()->set('services.skyrack.enabled', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Carbon::setTestNow(Carbon::parse('2026-08-21 09:00:00', 'Asia/Kolkata'));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_cancels_only_active_leads_fifteen_days_after_last_ride_date(): void
    {
        $representative = User::create([
            'name' => 'Sales Executive',
            'email' => 'sales@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $dueLead = $this->createLeadWithRideAndFollowup($representative, [
            'ride_dates' => ['2026-08-06 10:00:00'],
            'status' => 1,
            'total_amount' => 125000,
            'received_amount' => 25000,
            'service_ids' => ['11111111-1111-4111-8111-111111111111'],
            'extra_service_ids' => ['22222222-2222-4222-8222-222222222222'],
            'service_amount' => 130000,
            'discount_amount' => 5000,
            'service_details' => ['fare' => 125000],
        ]);

        $notDueLead = $this->createLeadWithRideAndFollowup($representative, [
            'ride_dates' => ['2026-08-07 10:00:00'],
            'status' => 1,
        ]);

        $completedLead = $this->createLeadWithRideAndFollowup($representative, [
            'ride_dates' => ['2026-08-01 10:00:00'],
            'status' => 5,
        ]);

        $multiSegmentLead = $this->createLeadWithRideAndFollowup($representative, [
            'ride_dates' => ['2026-08-01 10:00:00', '2026-08-10 10:00:00'],
            'status' => 1,
        ]);

        $this->assertArrayHasKey(
            'lead:auto-cancel-expired-active-rides',
            Artisan::all()
        );

        $this->assertSame(
            0,
            Artisan::call('lead:auto-cancel-expired-active-rides')
        );

        $cancelledFollowup = $this->latestFollowup($dueLead);

        $this->assertSame(2, (int) $cancelledFollowup->status);
        $this->assertSame($representative->id, $cancelledFollowup->followed_by);
        $this->assertSame(2, LeadFollowup::where('lead_id', $dueLead->id)->count());
        $this->assertStringContainsString(
            'automatically cancelled',
            strtolower($cancelledFollowup->followup_note)
        );
        $this->assertSame(125000.0, (float) $cancelledFollowup->total_amount);
        $this->assertSame(25000.0, (float) $cancelledFollowup->received_amount);
        $this->assertSame(['11111111-1111-4111-8111-111111111111'], $cancelledFollowup->service_ids);
        $this->assertSame(['22222222-2222-4222-8222-222222222222'], $cancelledFollowup->extra_service_ids);
        $this->assertSame(130000.0, (float) $cancelledFollowup->service_amount);
        $this->assertSame(5000.0, (float) $cancelledFollowup->discount_amount);
        $this->assertSame(['fare' => 125000], $cancelledFollowup->service_details);

        $this->assertSame(1, (int) $this->latestFollowup($notDueLead)->status);
        $this->assertSame(1, LeadFollowup::where('lead_id', $notDueLead->id)->count());

        $this->assertSame(5, (int) $this->latestFollowup($completedLead)->status);
        $this->assertSame(1, LeadFollowup::where('lead_id', $completedLead->id)->count());

        $this->assertSame(1, (int) $this->latestFollowup($multiSegmentLead)->status);
        $this->assertSame(1, LeadFollowup::where('lead_id', $multiSegmentLead->id)->count());
    }

    private function createLeadWithRideAndFollowup(User $representative, array $overrides): Lead
    {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Charter Customer ' . Str::random(5),
            'contact_number' => '9999999999',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $representative->id,
        ]);

        foreach ($overrides['ride_dates'] as $rideDate) {
            LeadRide::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $rideDate,
                'to_date' => Carbon::parse($rideDate)->addHour(),
                'from_place' => 'Delhi',
                'to_place' => 'Mumbai',
            ]);
        }

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-08-01 09:00:00',
            'followup_note' => 'Existing active follow-up',
            'followed_by' => $representative->id,
            'status' => $overrides['status'],
            'total_amount' => $overrides['total_amount'] ?? null,
            'received_amount' => $overrides['received_amount'] ?? null,
            'service_ids' => $overrides['service_ids'] ?? null,
            'extra_service_ids' => $overrides['extra_service_ids'] ?? null,
            'service_amount' => $overrides['service_amount'] ?? null,
            'discount_amount' => $overrides['discount_amount'] ?? null,
            'service_details' => $overrides['service_details'] ?? null,
        ]);

        $followup->forceFill([
            'created_at' => '2026-08-01 08:00:00',
            'updated_at' => '2026-08-01 08:00:00',
        ])->saveQuietly();

        return $lead;
    }

    private function latestFollowup(Lead $lead): LeadFollowup
    {
        return LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->firstOrFail();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
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

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->string('file')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by');
            $table->text('service_ids')->nullable();
            $table->text('extra_service_ids')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->timestamps();
        });
    }
}
