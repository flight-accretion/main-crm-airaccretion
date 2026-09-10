<?php

namespace Tests\Feature;

use App\Models\KpiOutreachAssignment;
use App\Models\KpiOutreachCursor;
use App\Models\KpiOutreachPool;
use App\Models\User;
use App\Services\Kpi\KpiOutreachAllocator;
use App\Services\Kpi\KpiOutreachService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpiOutreachFlowTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        Carbon::setTestNow(Carbon::create(2026, 9, 10, 11, 0, 0));
        $this->resetTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->restoreDefaultDatabase();

        parent::tearDown();
    }

    public function test_allocator_keeps_one_pending_owner_per_customer_phone(): void
    {
        $first = $this->user('First Sales');
        $second = $this->user('Second Sales');

        $this->pool('6500009001');

        $allocator = app(KpiOutreachAllocator::class);

        $this->assertSame(1, $allocator->allocate($first, 1));
        $this->assertSame(0, $allocator->allocate($second, 1));

        $this->assertSame(
            $first->id,
            KpiOutreachAssignment::query()
                ->where('normalized_phone', '6500009001')
                ->where('status', 'pending')
                ->value('user_id')
        );
    }

    public function test_create_lead_completion_replenishes_standard_queue(): void
    {
        $user = $this->user('Retail Sales');

        foreach (range(1, 51) as $index) {
            $this->pool('98765432' . str_pad((string) $index, 2, '0', STR_PAD_LEFT));
        }

        $service = app(KpiOutreachService::class);

        $service->ensureStandardQueue($user);

        $assignment = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('assigned_at')
            ->firstOrFail();

        $lead = new \App\Models\Lead([
            'id' => (string) Str::uuid(),
        ]);

        $service->completeWithLead($assignment, $user, $lead);

        $this->assertSame(
            50,
            KpiOutreachAssignment::query()
                ->where('user_id', $user->id)
                ->where('allocation_type', 'standard')
                ->where('status', 'pending')
                ->count()
        );

        $assignment->refresh();

        $this->assertSame('completed', $assignment->status);
        $this->assertSame('lead', $assignment->completion_type);
        $this->assertNull($assignment->active_phone_key);
    }

    private function user(string $name): User
    {
        $user = new User([
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'status' => 1,
        ]);

        $user->id = (string) Str::uuid();

        return $user;
    }

    private function pool(string $phone): void
    {
        KpiOutreachPool::create([
            'normalized_phone' => $phone,
            'display_name' => 'Customer ' . substr($phone, -2),
            'last_seen_at' => now(),
        ]);
    }

    private function resetTables(): void
    {
        Schema::dropIfExists('kpi_outreach_assignments');
        Schema::dropIfExists('kpi_outreach_batches');
        Schema::dropIfExists('kpi_outreach_cursors');
        Schema::dropIfExists('kpi_outreach_pool');

        $this->ensureActiveLeadServiceTables();

        Schema::create('kpi_outreach_pool', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 20)->unique();
            $table->uuid('canonical_client_id')->nullable();
            $table->string('display_name', 150)->nullable();
            $table->timestamp('cooling_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_outreach_cursors', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_pool_id')->default(0);
            $table->timestamps();
        });

        KpiOutreachCursor::create([
            'id' => 1,
            'last_pool_id' => 0,
        ]);

        Schema::create('kpi_outreach_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('batch_type', 20)->default('extra');
            $table->unsignedSmallInteger('requested_count')->default(50);
            $table->unsignedSmallInteger('allocated_count')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_outreach_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('pool_id');
            $table->uuid('user_id');
            $table->uuid('batch_id')->nullable();
            $table->string('allocation_type', 20)->default('standard');
            $table->string('normalized_phone', 20);
            $table->string('active_phone_key', 20)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('completion_type', 20)->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('remark_expires_at')->nullable();
            $table->uuid('call_summary_integration_id')->nullable();
            $table->uuid('ivr_call_log_id')->nullable();
            $table->uuid('created_lead_id')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    private function ensureActiveLeadServiceTables(): void
    {
        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('contact_number')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('client_id')->nullable();
                $table->uuid('representative_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('lead_followups')) {
            Schema::create('lead_followups', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('lead_id');
                $table->integer('status')->nullable();
                $table->timestamps();
            });
        }

    }

    private function useIsolatedDatabase(): void
    {
        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.connections.kpi_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('kpi_testing');
        DB::setDefaultConnection('kpi_testing');
    }

    private function restoreDefaultDatabase(): void
    {
        DB::disconnect('kpi_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('kpi_testing');
    }
}
