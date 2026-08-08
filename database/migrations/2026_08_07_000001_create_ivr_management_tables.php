<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIvrManagementTables extends Migration
{
    public function up()
    {
        Schema::create('ivr_call_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->string('assignment_mode', 20)->default('balanced');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::create('ivr_call_type_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ivr_call_type_id');
            $table->uuid('user_id');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ivr_call_type_id', 'user_id'], 'ivr_call_type_user_unique');
            $table->index('ivr_call_type_id');
            $table->index('user_id');
        });

        Schema::create('ivr_dtmf_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ivr_call_type_id')->nullable();
            $table->string('dtmf_value', 100);
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->json('match_values')->nullable();
            $table->string('assignment_mode', 20)->default('balanced');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('ivr_call_type_id');
            $table->index('dtmf_value');
            $table->index('is_default');
            $table->index('is_active');
        });

        Schema::create('ivr_dtmf_rule_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ivr_dtmf_rule_id');
            $table->uuid('user_id');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ivr_dtmf_rule_id', 'user_id'], 'ivr_dtmf_rule_user_unique');
            $table->index('ivr_dtmf_rule_id');
            $table->index('user_id');
        });

        Schema::create('ivr_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vi_agent_name', 150)->unique();
            $table->uuid('mapped_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remarks')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('mapped_user_id');
            $table->index('is_active');
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id', 150)->unique();
            $table->uuid('ivr_call_type_id')->nullable();
            $table->string('call_type_code', 50)->nullable();
            $table->string('dni', 50)->nullable();
            $table->string('cli', 50)->nullable();
            $table->string('normalized_phone', 20)->nullable();
            $table->string('raw_dtmf', 150)->nullable();
            $table->string('agent_name', 150)->nullable();
            $table->string('dial_status', 100)->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('call_end_at')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->integer('og_duration_sec')->nullable();
            $table->text('voice_url')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status', 50)->default('received');
            $table->text('processing_message')->nullable();
            $table->timestamp('initial_followup_created_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('ivr_call_type_id');
            $table->index('normalized_phone');
            $table->index('lead_id');
            $table->index('processing_status');
            $table->index('call_start_at');
        });

        Schema::create('ivr_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status', 30)->default('running');
            $table->integer('records_fetched')->default(0);
            $table->integer('records_created')->default(0);
            $table->integer('duplicate_calls')->default(0);
            $table->integer('repeat_leads')->default(0);
            $table->integer('errors')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ivr_sync_logs');
        Schema::dropIfExists('ivr_call_logs');
        Schema::dropIfExists('ivr_agents');
        Schema::dropIfExists('ivr_dtmf_rule_users');
        Schema::dropIfExists('ivr_dtmf_rules');
        Schema::dropIfExists('ivr_call_type_users');
        Schema::dropIfExists('ivr_call_types');
    }
}
