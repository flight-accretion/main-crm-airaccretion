<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceImportTables extends Migration
{
    public function up()
    {
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // HR-selected attendance upload range.
            $table->date('from_date');
            $table->date('to_date');

            // Actual period detected inside uploaded sheet.
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();

            $table->string('original_filename', 255);
            $table->string('stored_path', 500);
            $table->string('file_type', 20)->nullable();

            // previewed / completed
            $table->string('status', 30)->default('previewed');

            $table->unsignedInteger('total_employees')->default(0);
            $table->unsignedInteger('total_records')->default(0);

            $table->unsignedInteger('created_records')->default(0);
            $table->unsignedInteger('updated_records')->default(0);

            $table->uuid('uploaded_by');
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'from_date', 'to_date']);
            $table->index('uploaded_by');
        });

        Schema::create('attendance_user_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            /*
             * Paycode from biometric attendance file.
             *
             * This is the stable employee key.
             */
            $table->string('paycode', 100)->unique();

            $table->uuid('user_id');

            /*
             * Employee name as last seen in the file.
             * Useful for audit only.
             */
            $table->string('employee_name', 255)->nullable();

            $table->uuid('updated_by')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->string('paycode', 100)->nullable();

            /*
             * Critical rule:
             * one final attendance row per employee/date.
             */
            $table->date('attendance_date');

            $table->string('day_name', 20)->nullable();

            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();

            /*
             * Preserve original values from attendance software.
             */
            $table->string('raw_in', 50)->nullable();
            $table->string('raw_out', 50)->nullable();
            $table->string('raw_status', 50)->nullable();

            /*
             * Latest import responsible for current final row.
             */
            $table->uuid('source_import_id');

            /*
             * Snapshot of the office-time policy used when
             * this row was evaluated. Historical KPI should
             * not change just because the employee's current
             * policy changes later.
             */
            $table->uuid('resolved_shift_policy_id')->nullable();
            $table->string('resolved_shift_policy_name', 150)->nullable();
            $table->time('resolved_shift_start_time')->nullable();
            $table->time('resolved_shift_end_time')->nullable();
            $table->unsignedSmallInteger('resolved_shift_grace_minutes')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'attendance_date'],
                'attendance_user_date_unique'
            );

            $table->index([
                'attendance_date',
                'user_id',
            ]);

            $table->index('paycode');
            $table->index('source_import_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_user_mappings');
        Schema::dropIfExists('attendance_imports');
    }
}
