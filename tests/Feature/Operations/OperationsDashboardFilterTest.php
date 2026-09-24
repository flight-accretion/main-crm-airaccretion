<?php

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsDashboardFilterTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'operations_filter_testing');
        config()->set('database.connections.operations_filter_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('operations_filter_testing');
        DB::reconnect('operations_filter_testing');
        DB::setDefaultConnection('operations_filter_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 23, 10, 0, 0));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('operations_filter_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('operations_filter_testing');

        parent::tearDown();
    }

    public function test_queue_filters_by_date_status_assignee_and_search(): void
    {
        $viewer = $this->user('Ops Viewer', UserType::OPERATIONS_EXECUTIVE);
        $assignee = $this->user('Matching Ops', UserType::OPERATIONS_EXECUTIVE);
        $otherAssignee = $this->user('Other Ops', UserType::OPERATIONS_EXECUTIVE);

        $matchingLeadId = $this->lead('Match Customer', $viewer);
        $otherLeadId = $this->lead('Other Customer', $viewer);

        $this->case(
            $matchingLeadId,
            'review',
            'pending',
            $assignee->id,
            '2026-09-22 11:00:00',
            null,
            'Matching summary'
        );
        $this->case(
            $otherLeadId,
            'review',
            'in_progress',
            $otherAssignee->id,
            '2026-09-22 12:00:00',
            null,
            'Other summary'
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.queue', [
                'type' => 'review',
                'from_date' => '2026-09-22',
                'to_date' => '2026-09-22',
                'status' => 'pending',
                'operations_user_id' => $assignee->id,
                'search' => 'Match',
            ]));

        $response->assertOk();
        $response->assertSee('Match Customer');
        $response->assertDontSee('Other Customer');
        $response->assertSee('operations-queue-table');
        $response->assertSee('name="status"', false);
        $response->assertSee('name="operations_user_id"', false);
    }

    public function test_history_filters_completed_cases_by_date_assignee_and_search(): void
    {
        $viewer = $this->user('History Viewer', UserType::OPERATIONS_EXECUTIVE);
        $assignee = $this->user('History Ops', UserType::OPERATIONS_EXECUTIVE);
        $otherAssignee = $this->user('Other History Ops', UserType::OPERATIONS_EXECUTIVE);

        $matchingLeadId = $this->lead('History Match Customer', $viewer);
        $otherLeadId = $this->lead('History Other Customer', $viewer);

        $this->case(
            $matchingLeadId,
            'refund',
            'completed',
            $assignee->id,
            '2026-09-20 11:00:00',
            '2026-09-22 15:00:00',
            'History matching note'
        );
        $this->case(
            $otherLeadId,
            'refund',
            'completed',
            $otherAssignee->id,
            '2026-09-20 12:00:00',
            '2026-09-21 15:00:00',
            'History other note'
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.history', [
                'from_date' => '2026-09-22',
                'to_date' => '2026-09-22',
                'type' => 'refund',
                'operations_user_id' => $assignee->id,
                'search' => 'History Match',
            ]));

        $response->assertOk();
        $response->assertSee('History Match Customer');
        $response->assertDontSee('History Other Customer');
        $response->assertSee('operations-history-table');
        $response->assertSee('name="type"', false);
        $response->assertSee('name="operations_user_id"', false);
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
            $table->timestamps();
        });

        Schema::create('operation_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function user(string $name, string $role): User
    {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'status' => 1,
            ]
        );

        return User::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function lead(string $customerName, User $salesUser): string
    {
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => $customerName,
            'contact_number' => '9876543210',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'id' => $leadId,
            'client_id' => $clientId,
            'representative_user_id' => $salesUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $leadId;
    }

    private function case(
        string $leadId,
        string $type,
        string $status,
        string $assigneeId,
        string $openedAt,
        ?string $completedAt,
        string $note
    ): void {
        DB::table('operation_cases')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'type' => $type,
            'status' => $status,
            'assigned_to' => $assigneeId,
            'created_by' => null,
            'completed_by' => $completedAt ? $assigneeId : null,
            'opened_at' => $openedAt,
            'completed_at' => $completedAt,
            'note' => $note,
            'metadata' => null,
            'created_at' => $openedAt,
            'updated_at' => $completedAt ?: $openedAt,
        ]);
    }
}
