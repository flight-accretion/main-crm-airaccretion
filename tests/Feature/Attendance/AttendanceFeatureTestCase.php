<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AttendanceFeatureTestCase extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'attendance_feature_testing');
        config()->set('database.connections.attendance_feature_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('attendance_feature_testing');
        DB::reconnect('attendance_feature_testing');
        DB::setDefaultConnection('attendance_feature_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 19, 10, 0, 0));

        $this->createAttendanceSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('attendance_feature_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('attendance_feature_testing');

        parent::tearDown();
    }

    protected function createUserWithRole(
        string $name,
        string $role = UserType::HR
    ): User {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'description' => null,
                'status' => 1,
            ]
        );

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

    private function createAttendanceSchema(): void
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
            $table->timestamps();
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
    }
}
