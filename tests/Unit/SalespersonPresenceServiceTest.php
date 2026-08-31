<?php

namespace Tests\Unit;

use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\SalespersonPresenceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalespersonPresenceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 8, 31, 11, 30, 0));

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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_is_present_only_when_yes_was_clicked_today(): void
    {
        $salesperson = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);

        SalespersonAvailability::create([
            'user_id' => $salesperson->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now()->copy()->subDay(),
        ]);

        $presence = app(SalespersonPresenceService::class);

        $this->assertFalse($presence->isPresentToday($salesperson));

        SalespersonAvailability::query()
            ->where('user_id', $salesperson->id)
            ->update(['last_response_at' => now()]);

        $this->assertTrue($presence->isPresentToday($salesperson->fresh()));
    }

    public function test_super_admin_sees_all_sales_presence_and_sales_user_sees_only_self(): void
    {
        $superAdmin = $this->createUser('Super Admin User', UserType::SUPER_ADMIN);
        $presentSalesperson = $this->createUser('Akshita Borkar', UserType::SALES_EXECUTIVE);
        $absentSalesperson = $this->createUser('Pallavi Singh', UserType::SALES_MANAGER);

        SalespersonAvailability::create([
            'user_id' => $presentSalesperson->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now(),
        ]);

        SalespersonAvailability::create([
            'user_id' => $absentSalesperson->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now()->copy()->subDay(),
        ]);

        $presence = app(SalespersonPresenceService::class);

        $adminRows = $presence->rowsForDashboard($superAdmin);

        $this->assertSame(
            ['Akshita Borkar', 'Pallavi Singh'],
            $adminRows->pluck('name')->values()->all()
        );
        $this->assertSame('Yes', $adminRows->firstWhere('name', 'Akshita Borkar')['status_label']);
        $this->assertSame('No', $adminRows->firstWhere('name', 'Pallavi Singh')['status_label']);

        $ownRows = $presence->rowsForDashboard($presentSalesperson);

        $this->assertSame(['Akshita Borkar'], $ownRows->pluck('name')->values()->all());
    }

    private function createUser(string $name, string $type): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $type,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
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
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('salesperson_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('state')->default('unasked');
            $table->boolean('is_available')->default(false);
            $table->boolean('is_opted_in')->default(false);
            $table->timestamp('last_popup_at')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->timestamps();
        });
    }
}
