<?php

namespace Tests\Feature;

use App\Models\RideReminderLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RideReminderLegacyConstraintTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.reminder_constraint_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('reminder_constraint_test');
        DB::setDefaultConnection('reminder_constraint_test');
    }

    protected function tearDown(): void
    {
        DB::disconnect('reminder_constraint_test');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('reminder_constraint_test');
        parent::tearDown();
    }

    public function test_repair_accepts_current_writer_and_preserves_history_and_uniqueness(): void
    {
        Schema::create('ride_reminder_logs', function ($table) {
            $table->string('id')->primary();
            $table->string('ride_id');
            $table->integer('reminder_type');
            $table->string('template_name');
            $table->integer('hours_before');
            $table->string('channel');
            $table->string('status');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['ride_id', 'hours_before', 'channel'], 'ride_reminder_logs_unique_delivery');
        });
        $history = ['id' => 'history', 'ride_id' => 'old-ride', 'reminder_type' => 24, 'template_name' => 'booking', 'hours_before' => 24, 'channel' => 'booking', 'status' => 'sent', 'sent_at' => '2026-10-13 10:00:00'];
        DB::table('ride_reminder_logs')->insert($history);
        $pending = ['id' => 'pending', 'ride_id' => 'new-ride', 'hours_before' => 24, 'channel' => 'booking', 'status' => 'pending', 'sent_at' => null];
        try {
            RideReminderLog::create($pending);
            $this->fail('Legacy NOT NULL constraints should reject the current writer before repair');
        } catch (QueryException $expected) {
            $this->assertStringContainsString('NOT NULL', $expected->getMessage());
        }
        $migration = require base_path('database/migrations/2026_10_14_130000_relax_legacy_ride_reminder_log_constraints.php');
        $migration->up();
        $migration->up();
        RideReminderLog::create($pending);
        $saved = (array) DB::table('ride_reminder_logs')->where('id', 'history')->first();
        foreach ($history as $field => $value) {
            $this->assertSame($value, $saved[$field]);
        }
        $migration->down();
        $this->assertSame(2, DB::table('ride_reminder_logs')->count());
        $this->expectException(QueryException::class);
        RideReminderLog::create(array_merge($pending, ['id' => 'duplicate']));
    }

    public function test_missing_or_current_schema_is_safe(): void
    {
        $migration = require base_path('database/migrations/2026_10_14_130000_relax_legacy_ride_reminder_log_constraints.php');
        $migration->up();
        $this->assertFalse(Schema::hasTable('ride_reminder_logs'));
        Schema::create('ride_reminder_logs', function ($table) {
            $table->string('id')->primary();
            $table->timestamp('sent_at')->nullable();
        });
        $migration->up();
        DB::table('ride_reminder_logs')->insert(['id' => 'current', 'sent_at' => null]);
        $this->assertSame(1, DB::table('ride_reminder_logs')->count());
    }
}
