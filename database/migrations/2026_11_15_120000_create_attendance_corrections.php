<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_records', fn (Blueprint $table) => $table->unsignedBigInteger('correction_revision')->default(0));
        Schema::table('attendance_imports', fn (Blueprint $table) => $table->json('correction_snapshot')->nullable());
        Schema::create('attendance_payroll_periods', function (Blueprint $table) {
            $table->date('month')->primary();
            $table->boolean('closed')->default(false);
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attendance_record_id')->index();
            $table->uuid('user_id');
            $table->date('attendance_date');
            $table->json('before_values');
            $table->json('after_values');
            $table->string('base_version', 64);
            $table->string('applied_version', 64)->nullable();
            $table->text('reason');
            $table->string('source', 30);
            $table->uuid('source_import_id')->nullable();
            $table->uuid('reverses_id')->nullable()->unique();
            $table->uuid('requested_by');
            $table->string('status', 20);
            $table->uuid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_correction_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('correction_id')->nullable()->index();
            $table->string('action', 30);
            $table->uuid('actor_id');
            $table->json('details');
            $table->timestamp('created_at');
        });
        Schema::create('attendance_payroll_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('correction_id')->unique();
            $table->uuid('user_id');
            $table->date('attendance_date');
            $table->date('month');
            $table->string('status', 20)->default('pending');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_payroll_reviews');
        Schema::dropIfExists('attendance_correction_events');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_payroll_periods');
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn('correction_revision'));
        Schema::table('attendance_imports', fn (Blueprint $table) => $table->dropColumn('correction_snapshot'));
    }
};
