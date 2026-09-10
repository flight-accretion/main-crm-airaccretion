<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateKpiOutreachTables extends Migration
{
    public function up()
    {
        Schema::create('kpi_outreach_pool', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('normalized_phone', 20)->unique();
            $table->uuid('canonical_client_id')->nullable();
            $table->string('display_name', 150)->nullable();
            $table->timestamp('cooling_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('cooling_until');
        });

        Schema::create('kpi_outreach_cursors', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_pool_id')->default(0);
            $table->timestamps();
        });

        DB::table('kpi_outreach_cursors')->insert([
            'id' => 1,
            'last_pool_id' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('kpi_outreach_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('batch_type', 20)->default('extra');
            $table->unsignedSmallInteger('requested_count')->default(50);
            $table->unsignedSmallInteger('allocated_count')->default(0);
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'batch_type', 'completed_at']);
        });

        Schema::create('kpi_outreach_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('pool_id');
            $table->uuid('user_id');
            $table->uuid('batch_id')->nullable();
            $table->string('allocation_type', 20)->default('standard');
            $table->string('normalized_phone', 20);
            $table->string('active_phone_key', 20)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('completion_type', 20)->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('remark_expires_at')->nullable();
            $table->uuid('call_summary_integration_id')->nullable();
            $table->uuid('ivr_call_log_id')->nullable();
            $table->uuid('created_lead_id')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'allocation_type']);
            $table->index(['user_id', 'completed_at']);
            $table->index(['normalized_phone', 'completed_at']);
            $table->index('batch_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kpi_outreach_assignments');
        Schema::dropIfExists('kpi_outreach_batches');
        Schema::dropIfExists('kpi_outreach_cursors');
        Schema::dropIfExists('kpi_outreach_pool');
    }
}
