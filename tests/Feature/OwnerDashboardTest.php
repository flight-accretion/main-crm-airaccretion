<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use App\Services\Accounts\AccountsHomeService;
use App\Services\TargetResolverService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDashboardTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'owner_testing');
        config()->set('database.connections.owner_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('owner_testing');
        DB::reconnect('owner_testing');
        DB::setDefaultConnection('owner_testing');

        $this->createSchema();
        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        DB::disconnect('owner_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('owner_testing');

        parent::tearDown();
    }

    /* ------------------------------------------------------------------ */
    /* Access control                                                      */
    /* ------------------------------------------------------------------ */

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.owner.dashboard'))->assertRedirect(route('login'));
    }

    public function test_owner_login_redirects_to_owner_dashboard(): void
    {
        $owner = $this->makeUser('Owner', UserType::OWNER);
        $owner->password = Hash::make('owner-password');
        $owner->save();

        $this->post(route('login.attempt'), [
            'email' => $owner->email,
            'password' => 'owner-password',
        ])->assertRedirect(route('admin.owner.dashboard'));
    }

    public function test_sales_executive_gets_403(): void
    {
        $this->actingAs($this->makeUser('Sales Exec', UserType::SALES_EXECUTIVE))
            ->get(route('admin.owner.dashboard'))
            ->assertForbidden();
    }

    public function test_owner_mutating_request_is_forbidden_by_readonly_guard(): void
    {
        // The refunds group intentionally includes OWNER (view); the guard must still block POSTs.
        $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->post(route('admin.refunds.store'))
            ->assertForbidden();
    }

    public function test_owner_can_open_a_sidebar_page_that_is_otherwise_limited_to_accounts(): void
    {
        $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->get(route('admin.account.dashboard'))
            ->assertOk()
            ->assertSee('Accounts Dashboard');
    }

    public function test_owner_can_monitor_live_lead_chat_without_being_given_write_access(): void
    {
        $owner = $this->makeUser('Owner', UserType::OWNER);
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();
        $conversationId = (string) Str::uuid();

        DB::table('clients')->insert(['id' => $clientId, 'name' => 'WhatsApp Lead', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('leads')->insert(['id' => $leadId, 'client_id' => $clientId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lead_chat_conversations')->insert(['id' => $conversationId, 'lead_id' => $leadId, 'last_message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lead_chat_messages')->insert(['id' => (string) Str::uuid(), 'conversation_id' => $conversationId, 'lead_id' => $leadId, 'body' => 'Vendor confirmation pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lead_chat_tasks')->insert(['id' => (string) Str::uuid(), 'conversation_id' => $conversationId, 'lead_id' => $leadId, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($owner)
            ->get(route('admin.lead-chat.overview', ['search' => 'WhatsApp Lead', 'state' => 'needs_attention']))
            ->assertOk()
            ->assertSee('Read-only oversight')
            ->assertSee('WhatsApp Lead')
            ->assertSee('Vendor confirmation pending')
            ->assertSee('1 open task');
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard render                                                    */
    /* ------------------------------------------------------------------ */

    public function test_owner_dashboard_renders(): void
    {
        $this->stubHeavyCollaborators();

        $lead = (string) Str::uuid();
        DB::table('leads')->insert(['id' => $lead, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lead_followups')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead,
            'status' => 3,
            'received_amount' => 5000,
            'total_amount' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('operation_cases')->insert(['id' => (string) Str::uuid(), 'lead_id' => $lead, 'type' => 'review', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->get(route('admin.owner.dashboard'))
            ->assertOk()
            ->assertSee('Owner Dashboard')
            ->assertSee('Owner Rahul ji')
            ->assertSee('Live executive snapshot')
            ->assertSee('Net Profit / Loss')
            ->assertSee('Email lead fetch')
            ->assertSee('Lead AI scoring')
            ->assertSee('Exceptional Dashboard')
            ->assertSee(route('admin.account.exceptional'))
            ->assertSee("document.addEventListener('DOMContentLoaded'", false)
            ->assertSee('ow-sales-chart');
    }

    public function test_dashboard_uses_real_source_dates_and_non_overlapping_source_buckets(): void
    {
        $this->stubHeavyCollaborators();
        $month = now()->startOfMonth();
        $leads = [];

        foreach (['email', 'whatsapp', 'ivr', 'both', 'other'] as $source) {
            $leads[$source] = (string) Str::uuid();
            DB::table('leads')->insert([
                'id' => $leads[$source],
                'created_at' => $month->copy()->addDay(),
                'updated_at' => $month->copy()->addDay(),
            ]);
        }

        foreach (['email', 'both'] as $source) {
            DB::table('email_lead_logs')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $leads[$source],
                'received_at' => $month->copy()->addDay(),
                'created_at' => $month->copy()->addDays(2),
                'updated_at' => $month->copy()->addDays(2),
            ]);
        }

        foreach (['ivr', 'both'] as $source) {
            DB::table('ivr_call_logs')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $leads[$source],
                'call_start_at' => $month->copy()->addDay(),
                'created_at' => $month->copy()->addDays(2),
                'updated_at' => $month->copy()->addDays(2),
            ]);
        }

        DB::table('whatsapp_lead_integrations')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leads['whatsapp'],
            'created_at' => $month->copy()->addDay(),
            'updated_at' => $month->copy()->addDay(),
        ]);

        $response = $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->get(route('admin.owner.dashboard'));
        $sources = $response->viewData('sources');
        $buckets = collect($sources['buckets'])->keyBy('label');

        $this->assertSame(5, $sources['total']);
        $this->assertSame(2, $buckets['Email']['count']);
        $this->assertSame(1, $buckets['WhatsApp']['count']);
        $this->assertSame(1, $buckets['IVR calls']['count']);
        $this->assertSame(1, $buckets['Manual / Other']['count']);
        $this->assertArrayNotHasKey('Website', $buckets->all());
    }

    public function test_dashboard_pipeline_uses_latest_followup_status_per_monthly_lead(): void
    {
        $this->stubHeavyCollaborators();
        $month = now()->startOfMonth();
        $statuses = [
            [LeadFollowup::STATUS_ACTIVE, LeadFollowup::STATUS_CANCELLED],
            [LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED],
            [LeadFollowup::STATUS_CONFIRMED],
            [LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED],
        ];

        foreach ($statuses as $leadStatuses) {
            $leadId = (string) Str::uuid();
            DB::table('leads')->insert([
                'id' => $leadId,
                'created_at' => $month->copy()->addDay(),
                'updated_at' => $month->copy()->addDay(),
            ]);

            foreach ($leadStatuses as $index => $status) {
                DB::table('lead_followups')->insert([
                    'id' => (string) Str::uuid(),
                    'lead_id' => $leadId,
                    'status' => $status,
                    'created_at' => $month->copy()->addDays($index + 1),
                    'updated_at' => $month->copy()->addDays($index + 1),
                ]);
            }
        }

        $response = $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->get(route('admin.owner.dashboard'));
        $funnel = $response->viewData('funnel');

        $this->assertSame(4, $funnel['new']);
        $this->assertSame(1, $funnel['active']);
        $this->assertSame(1, $funnel['confirmed']);
        $this->assertSame(1, $funnel['paid']);
    }

    public function test_operations_rides_exclude_cancelled_leads(): void
    {
        $this->stubHeavyCollaborators();

        foreach ([
            [now()->copy()->addHours(2), LeadFollowup::STATUS_CONFIRMED],
            [now()->copy()->addHours(3), LeadFollowup::STATUS_CANCELLED],
            [now()->copy()->addHours(24), LeadFollowup::STATUS_CONFIRMED],
        ] as [$rideDate, $status]) {
            $leadId = (string) Str::uuid();
            DB::table('leads')->insert([
                'id' => $leadId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('lead_followups')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $leadId,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('lead_rides')->insert([
                'id' => (string) Str::uuid(),
                'lead_id' => $leadId,
                'from_date' => $rideDate,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->makeUser('Owner', UserType::OWNER))
            ->get(route('admin.owner.dashboard'));
        $operations = $response->viewData('operations');

        $this->assertSame(1, $operations['rides_today']);
        $this->assertSame(2, $operations['rides_48h']);
    }

    public function test_invalid_month_is_rejected_and_dashboard_is_not_cached(): void
    {
        $this->stubHeavyCollaborators();
        $owner = $this->makeUser('Owner', UserType::OWNER);

        $this->actingAs($owner)
            ->get(route('admin.owner.dashboard', ['month' => '2026-13']))
            ->assertSessionHasErrors('month');

        $this->actingAs($owner)
            ->get(route('admin.owner.dashboard', ['month' => '2026-09']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('month', function (Carbon $month) {
                return $month->format('Y-m') === '2026-09';
            });
    }

    /* ------------------------------------------------------------------ */
    /* Seeder idempotency (destructive seeder must keep Owner)             */
    /* ------------------------------------------------------------------ */

    public function test_user_type_seeder_keeps_owner_role_across_runs(): void
    {
        $originalEmail = getenv('OWNER_EMAIL');
        $originalPassword = getenv('OWNER_PASSWORD');
        putenv('OWNER_EMAIL=rahul-owner@example.test');
        putenv('OWNER_PASSWORD=owner-test-password');

        try {
            $this->seed(\Database\Seeders\UserTypeSeeder::class);
            $this->seed(\Database\Seeders\UserTypeSeeder::class);

            $ownerType = DB::table('user_types')->where('user_type', UserType::OWNER)->first();
            $this->assertNotNull($ownerType, 'Owner role must survive the seeder');

            $superAdmin = DB::table('user_types')->where('user_type', UserType::SUPER_ADMIN)->first();
            $this->assertSame($superAdmin->id, $ownerType->parent_id, 'Owner sits directly under Super Admin');

            $owner = DB::table('users')->where('user_type_id', $ownerType->id)->first();
            $this->assertNotNull($owner);
            $this->assertSame('rahul-owner@example.test', $owner->email);
            $this->assertTrue(Hash::check('owner-test-password', $owner->password));
        } finally {
            $this->restoreProcessEnvironment('OWNER_EMAIL', $originalEmail);
            $this->restoreProcessEnvironment('OWNER_PASSWORD', $originalPassword);
        }
    }

    public function test_seeder_updates_existing_owner_login_and_password(): void
    {
        $owner = $this->makeUser('Existing Owner', UserType::OWNER);
        $customRoleId = (string) Str::uuid();
        DB::table('user_types')->insert([
            'id' => $customRoleId,
            'user_type' => 'Custom legacy role',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $owner->password = Hash::make('old-password');
        $owner->save();
        $originalEmail = getenv('OWNER_EMAIL');
        $originalPassword = getenv('OWNER_PASSWORD');
        putenv('OWNER_EMAIL=rahul-owner@example.test');
        putenv('OWNER_PASSWORD=new-owner-password');

        try {
            $this->seed(\Database\Seeders\OwnerAccountSeeder::class);

            $updatedOwner = DB::table('users')->where('id', $owner->id)->first();
            $this->assertSame('rahul-owner@example.test', $updatedOwner->email);
            $this->assertTrue(Hash::check('new-owner-password', $updatedOwner->password));
            $this->assertSame(1, DB::table('users')->where('user_type_id', $owner->user_type_id)->count());
            $this->assertTrue(DB::table('user_types')->where('id', $customRoleId)->exists());
        } finally {
            $this->restoreProcessEnvironment('OWNER_EMAIL', $originalEmail);
            $this->restoreProcessEnvironment('OWNER_PASSWORD', $originalPassword);
        }
    }

    public function test_seeder_refuses_to_take_over_an_email_owned_by_another_user(): void
    {
        $owner = $this->makeUser('Existing Owner', UserType::OWNER);
        $other = $this->makeUser('Different User', UserType::SALES_EXECUTIVE);
        $originalEmail = getenv('OWNER_EMAIL');
        $originalPassword = getenv('OWNER_PASSWORD');
        putenv('OWNER_EMAIL=' . $other->email);
        putenv('OWNER_PASSWORD=new-owner-password');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('OWNER_EMAIL is already assigned to a different user.');
            $this->seed(\Database\Seeders\UserTypeSeeder::class);
        } finally {
            $this->restoreProcessEnvironment('OWNER_EMAIL', $originalEmail);
            $this->restoreProcessEnvironment('OWNER_PASSWORD', $originalPassword);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function makeUser(string $name, string $role): User
    {
        $typeId = DB::table('user_types')->where('user_type', $role)->value('id');

        $user = new User([
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'secret',
            'user_type_id' => $typeId,
            'status' => 1,
        ]);
        $user->id = (string) Str::uuid();
        $user->save();

        return $user;
    }

    private function restoreProcessEnvironment(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            return;
        }

        putenv($key . '=' . $value);
    }

    private function stubHeavyCollaborators(): void
    {
        $accounts = $this->createMock(AccountsHomeService::class);
        $accounts->method('forMonth')->willReturn([
            'month' => now()->startOfMonth(),
            'pending' => [
                'payments' => 1, 'payment_amount' => 1000.0, 'payment_oldest' => now()->subDay(),
                'customer_refunds' => 0, 'customer_amount' => 0.0, 'customer_oldest' => null,
                'vendor_overdue' => 0, 'vendor_overdue_amount' => 0.0,
                'vendor_refunds' => 0, 'vendor_refund_amount' => 0.0,
            ],
            'invoices' => ['generated' => 0, 'finalized' => 0],
            'approval' => ['count' => 0, 'percent' => null, 'average_minutes' => null],
            'money' => ['collected' => 1000.0, 'vendor_paid' => 0.0, 'vendor_payments' => 0, 'customer_refunded' => 0.0, 'vendor_received' => 0.0, 'exposure' => 0.0, 'net' => 1000.0],
            'previous' => ['collected' => 500.0, 'vendor_paid' => 0.0, 'customer_refunded' => 0.0, 'vendor_received' => 0.0, 'net' => 500.0],
            'chart' => [], 'chartStart' => now()->subDays(13), 'chartEnd' => now(),
        ]);
        $this->app->instance(AccountsHomeService::class, $accounts);

        $targets = $this->createMock(TargetResolverService::class);
        $targets->method('amountForUsers')->willReturn(0.0);
        $this->app->instance(TargetResolverService::class, $targets);

        $progress = $this->getMockBuilder(DashboardController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['calculateAchievedAmountForRepIds'])
            ->getMock();
        $progress->method('calculateAchievedAmountForRepIds')->willReturn(0.0);
        $this->app->instance(DashboardController::class, $progress);
    }

    private function seedRoles(): void
    {
        $roles = [
            'Super Admin' => null,
            'Admin' => 'Super Admin',
            'Owner' => 'Super Admin',
            'Senior Sales Manager' => 'Admin',
            'Sales Manager' => 'Senior Sales Manager',
            'Sales Executive' => 'Sales Manager',
            'Hr' => 'Admin',
        ];

        $ids = [];
        foreach ($roles as $role => $parent) {
            $id = (string) Str::uuid();
            $ids[$role] = $id;
            DB::table('user_types')->insert([
                'id' => $id,
                'user_type' => $role,
                'parent_id' => $parent ? $ids[$parent] : null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_type');
            $t->text('description')->nullable();
            $t->string('parent_id')->nullable();
            $t->integer('status')->default(1);
            $t->timestamps();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->string('address')->nullable();
            $t->string('contact_number')->nullable();
            $t->string('user_type_id')->nullable();
            $t->integer('status')->default(1);
            $t->timestamp('joining_date')->nullable();
            $t->timestamp('last_login')->nullable();
            $t->timestamps();
        });

        Schema::create('clients', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->timestamps();
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('client_id')->nullable();
            $t->string('representative_user_id')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->integer('status')->default(0);
            $t->decimal('received_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_followup_id')->nullable();
            $t->integer('payment_status')->default(0);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->timestamp('paid_date')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->timestamp('from_date')->nullable();
            $t->timestamps();
        });

        Schema::create('operation_cases', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->string('type')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_id')->nullable();
            $t->timestamp('attendance_date')->nullable();
            $t->timestamp('in_time')->nullable();
            $t->string('paycode')->nullable();
            $t->timestamps();
        });

        Schema::create('kpi_metric_snapshots', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->decimal('achievement_percent', 8, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('sales_daily_updates', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('user_id')->nullable();
            $t->timestamp('update_date')->nullable();
            $t->timestamps();
        });

        Schema::create('email_lead_logs', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->timestamp('call_start_at')->nullable();
            $t->timestamps();
        });

        foreach (['whatsapp_lead_integrations', 'skyrack_lead_syncs'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->string('id')->primary();
                $t->string('lead_id')->nullable();
                $t->timestamps();
            });
        }

        Schema::create('lead_chat_conversations', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_chat_messages', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('conversation_id')->nullable();
            $t->string('lead_id')->nullable();
            $t->text('body')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_chat_tasks', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('conversation_id')->nullable();
            $t->string('lead_id')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('sales_executive_assignments', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('manager_id')->nullable();
            $t->string('sales_executive_id')->nullable();
            $t->integer('status')->default(1);
            $t->timestamps();
        });

        Schema::create('whatsapp_ai_reply_batches', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->timestamps();
        });

        Schema::create('call_summary_integrations', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('lead_ai_scores', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->string('status')->nullable();
            $t->timestamps();
        });

        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->text('payload')->nullable();
            $t->text('exception')->nullable();
            $t->timestamp('failed_at')->nullable();
        });

        Schema::create('lead_refunds', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_followup_id')->nullable();
            $t->decimal('refund_amount', 15, 2)->default(0);
            $t->integer('status')->default(0);
            $t->timestamps();
        });

        Schema::create('lead_vendor_payments', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->decimal('vendor_payment_balance', 15, 2)->default(0);
            $t->decimal('vendor_refund_due', 15, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_vendor_payment_id')->nullable();
            $t->integer('status')->default(0);
            $t->decimal('paid_amount', 15, 2)->default(0);
            $t->timestamp('paid_date')->nullable();
            $t->timestamps();
        });

        Schema::create('vendor_refunds', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_vendor_payment_id')->nullable();
            $t->string('status')->nullable();
            $t->decimal('refund_amount', 15, 2)->default(0);
            $t->timestamp('refund_date')->nullable();
            $t->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('lead_id')->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('voucher_id')->nullable();
            $t->integer('status')->default(0);
            $t->timestamps();
        });
    }
}
