<?php

namespace Tests\Feature\Kpi;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class KpiFeatureTestCase extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'kpi_feature_testing');
        config()->set('database.connections.kpi_feature_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('kpi_feature_testing');
        DB::reconnect('kpi_feature_testing');
        DB::setDefaultConnection('kpi_feature_testing');

        Carbon::setTestNow(
            Carbon::create(2026, 9, 17, 10, 0, 0)
        );

        $this->createKpiSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        DB::disconnect('kpi_feature_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('kpi_feature_testing');

        parent::tearDown();
    }

    protected function createUserWithRole(
        string $name,
        string $role
    ): User {
        $type = UserType::query()
            ->where('user_type', $role)
            ->first();

        if (!$type) {
            $type = UserType::create([
                'id' => (string) Str::uuid(),
                'user_type' => $role,
                'status' => 1,
            ]);
        }

        return User::create([
            'name' => $name,
            'email' => Str::slug($name)
                . '-'
                . Str::lower(Str::random(6))
                . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    protected function createLeadFor(
        User $user,
        string $customerName = 'KPI Customer',
        ?string $createdAt = null
    ): string {
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => $customerName,
            'contact_number' => '98765' . random_int(10000, 99999),
            'created_at' => $createdAt ?: now(),
            'updated_at' => $createdAt ?: now(),
        ]);

        DB::table('leads')->insert([
            'id' => $leadId,
            'client_id' => $clientId,
            'representative_user_id' => $user->id,
            'created_at' => $createdAt ?: now(),
            'updated_at' => $createdAt ?: now(),
        ]);

        return $leadId;
    }

    protected function insertKpiEvent(
        User $user,
        string $leadId,
        string $eventType,
        string $occurredAt,
        string $keySuffix
    ): void {
        DB::table('kpi_activity_events')->insert([
            'id' => (string) Str::uuid(),
            'event_key' => 'test:' . $keySuffix,
            'department' => 'sales',
            'user_id' => $user->id,
            'entity_type' => 'lead',
            'entity_id' => $leadId,
            'event_type' => $eventType,
            'source_record_id' => (string) Str::uuid(),
            'source' => 'human_ui',
            'old_value' => null,
            'new_value' => 'value',
            'metadata' => null,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    protected function assignSalesExecutive(
        User $manager,
        User $executive
    ): void {
        DB::table('sales_executive_assignments')->insert([
            'id' => (string) Str::uuid(),
            'manager_id' => $manager->id,
            'sales_executive_id' => $executive->id,
            'assigned_date' => now()->toDateString(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assignKpiTeamMember(
        string $department,
        User $manager,
        User $member
    ): void {
        DB::table('kpi_team_memberships')->insert([
            'id' => (string) Str::uuid(),
            'department' => $department,
            'manager_user_id' => $manager->id,
            'member_user_id' => $member->id,
            'active' => true,
            'created_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createKpiSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('description')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('paycode', 100)->nullable();
            $table->date('attendance_date');
            $table->string('day_name', 20)->nullable();
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->string('raw_in', 50)->nullable();
            $table->string('raw_out', 50)->nullable();
            $table->string('raw_status', 50)->nullable();
            $table->uuid('source_import_id')->nullable();
            $table->timestamps();

            $table->unique([
                'user_id',
                'attendance_date',
            ]);
        });

        Schema::create('attendance_shift_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create(
            'attendance_user_shift_assignments',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('shift_policy_id');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'sales_executive_assignments',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('manager_id');
                $table->uuid('sales_executive_id');
                $table->date('assigned_date')->nullable();
                $table->text('notes')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            }
        );

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->timestamp('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('file')->nullable();
            $table->text('narration')->nullable();
            $table->integer('payment_status')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('original_amount', 15, 2)->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->string('refund_type')->nullable();
            $table->dateTime('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        Schema::create('targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_executive_id');
            $table->uuid('assigned_by')->nullable();
            $table->integer('year');
            $table->tinyInteger('month');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('achieved_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('whatsapp_lead_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->string('source_type')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_activity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_key', 190)->unique();
            $table->string('department', 50)->index();
            $table->uuid('user_id')->index();
            $table->string('entity_type', 50)->index();
            $table->string('entity_id', 64)->index();
            $table->string('event_type', 80)->index();
            $table->string('source_record_id', 64)->nullable()->index();
            $table->string('source', 50)->default('human_ui');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        Schema::create('kpi_team_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department', 50)->index();
            $table->uuid('manager_user_id')->index();
            $table->uuid('member_user_id')->index();
            $table->boolean('active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique([
                'department',
                'manager_user_id',
                'member_user_id',
            ]);
        });

        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('department', 50);
            $table->unsignedSmallInteger('working_days_per_month')->default(22);
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('code', 100);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->decimal('weightage', 6, 2)->default(0);
            $table->string('measurement_type', 30)->default('automatic');
            $table->string('source_key', 100)->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->string('direction', 30)->default('higher_better');
            $table->json('score_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('template_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->uuid('assigned_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_working_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('work_date')->unique();
            $table->boolean('is_working_day')->default(true);
            $table->string('note', 255)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_user_non_working_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->date('work_date');
            $table->string('reason_type', 50);
            $table->string('note', 255)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_manual_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('metric_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('value', 16, 4)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('note')->nullable();
            $table->uuid('entered_by');
            $table->timestamps();

            $table->unique(['user_id', 'metric_id', 'year', 'month']);
        });

        Schema::create('kpi_outreach_pool', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 20)->unique();
            $table->timestamps();
        });

        Schema::create('kpi_outreach_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('batch_type', 20)->default('extra');
            $table->unsignedSmallInteger('requested_count')->default(50);
            $table->unsignedSmallInteger('allocated_count')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_outreach_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('pool_id')->nullable();
            $table->uuid('user_id');
            $table->uuid('batch_id')->nullable();
            $table->string('allocation_type', 20)->default('standard');
            $table->string('normalized_phone', 20);
            $table->string('active_phone_key', 20)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }
}
