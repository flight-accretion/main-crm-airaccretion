<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttendanceCalendarsAndImportHash extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_week_off_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->date('effective_from');
            $table->json('weekdays');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'effective_from'], 'attendance_weekoff_effective_unique');
        });
        Schema::create('attendance_calendar_months', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('mode', 20)->default('additional');
            $table->json('dates');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
        Schema::create('attendance_week_off_swaps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->date('worked_date');
            $table->date('off_date');
            $table->uuid('approved_by');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'worked_date']);
            $table->unique(['user_id', 'off_date']);
        });
        Schema::create('attendance_calendar_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action', 50);
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('actor_id');
            $table->json('details');
            $table->timestamps();
        });
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->string('file_hash', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_imports', function (Blueprint $table) {
            $table->dropColumn('file_hash');
        });
        Schema::dropIfExists('attendance_calendar_audits');
        Schema::dropIfExists('attendance_week_off_swaps');
        Schema::dropIfExists('attendance_calendar_months');
        Schema::dropIfExists('attendance_week_off_schedules');
    }
}
