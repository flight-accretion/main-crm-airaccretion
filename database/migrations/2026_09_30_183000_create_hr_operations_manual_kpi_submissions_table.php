<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrOperationsManualKpiSubmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('hr_operations_manual_kpi_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('metric_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('value', 16, 4);
            $table->text('note')->nullable();
            $table->string('status', 30)->default('draft');
            $table->uuid('submitted_by');
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('review_note')->nullable();
            $table->uuid('manual_value_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'year', 'month']);
            $table->index(['user_id', 'metric_id', 'year', 'month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_operations_manual_kpi_submissions');
    }
}
