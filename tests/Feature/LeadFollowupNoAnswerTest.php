<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadFollowupNoAnswerTest extends TestCase
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
    }

    public function test_followup_persists_structured_customer_not_picked_up_flag(): void
    {
        $lead = Lead::create([
            'id' => (string) Str::uuid(),
        ]);

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followup_note' => 'Customer did not pick up / respond.',
            'contact_outcome' => LeadFollowup::CONTACT_OUTCOME_NO_ANSWER,
            'customer_not_picked_up' => true,
            'status' => 1,
        ]);

        $this->assertTrue($followup->fresh()->customer_not_picked_up);
    }
}
