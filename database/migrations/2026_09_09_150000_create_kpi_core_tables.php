<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKpiCoreTables extends Migration
{
    public function up()
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('department', 50);
            $table->unsignedSmallInteger('working_days_per_month')->default(22);
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['department', 'active']);
        });

        Schema::create('kpi_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('code', 100);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->decimal('weightage', 6, 2)->default(0);
            $table->string('measurement_type', 30)->default('automatic');
            $table->string('source_key', 100)->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->string('direction', 30)->default('higher_better');
            $table->json('score_rules')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'code']);
            $table->index(['template_id', 'active', 'sort_order']);
        });

        Schema::create('kpi_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('template_id');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->uuid('assigned_by')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active', 'effective_from']);
            $table->index(['template_id', 'active']);
        });

        Schema::create('kpi_working_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('work_date')->unique();
            $table->boolean('is_working_day')->default(true);
            $table->string('note', 255)->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_user_non_working_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->date('work_date');
            $table->string('reason_type', 50);
            $table->string('note', 255)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
        });

        Schema::create('kpi_manual_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('metric_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('value', 16, 4)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('note')->nullable();
            $table->uuid('entered_by');
            $table->timestamps();

            $table->unique(['user_id', 'metric_id', 'year', 'month']);
        });

        Schema::create('kpi_monthly_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('template_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('weighted_raw_score', 6, 3);
            $table->unsignedTinyInteger('overall_score');
            $table->timestamp('finalized_at')->nullable();
            $table->uuid('finalized_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month']);
        });

        Schema::create('kpi_metric_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('snapshot_id');
            $table->uuid('metric_id');
            $table->decimal('actual_value', 16, 4)->nullable();
            $table->decimal('target_value', 16, 4)->nullable();
            $table->decimal('achievement_percent', 10, 4)->nullable();
            $table->unsignedTinyInteger('score');
            $table->decimal('weightage', 6, 2);
            $table->decimal('weighted_score', 10, 4);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'metric_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('kpi_metric_snapshots');
        Schema::dropIfExists('kpi_monthly_snapshots');
        Schema::dropIfExists('kpi_manual_values');
        Schema::dropIfExists('kpi_user_non_working_days');
        Schema::dropIfExists('kpi_working_days');
        Schema::dropIfExists('kpi_user_assignments');
        Schema::dropIfExists('kpi_metrics');
        Schema::dropIfExists('kpi_templates');
    }
}
