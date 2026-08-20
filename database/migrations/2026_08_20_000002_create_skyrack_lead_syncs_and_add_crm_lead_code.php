<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkyrackLeadSyncsAndAddCrmLeadCode extends Migration
{
    public function up()
    {
        if (
            Schema::hasTable('leads')
            &&
            !Schema::hasColumn('leads', 'crm_lead_code')
        ) {
            Schema::table('leads', function (Blueprint $table) {
                $table
                    ->string('crm_lead_code', 50)
                    ->nullable()
                    ->after('id');

                $table->unique(
                    'crm_lead_code',
                    'leads_crm_lead_code_unique'
                );
            });
        }

        if (!Schema::hasTable('skyrack_lead_syncs')) {
            Schema::create('skyrack_lead_syncs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('lead_id')->unique();
                $table->string('status', 30)->default('pending');
                $table->string('reason', 50)->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->string('last_payload_hash', 64)->nullable();
                $table->text('last_error')->nullable();
                $table->json('last_payload')->nullable();
                $table->json('last_response')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('next_attempt_at');
                $table->index('synced_at');
            });
        }

        if (!Schema::hasTable('skyrack_lead_sync_states')) {
            Schema::create('skyrack_lead_sync_states', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('skyrack_lead_sync_states');
        Schema::dropIfExists('skyrack_lead_syncs');

        if (
            Schema::hasTable('leads')
            &&
            Schema::hasColumn('leads', 'crm_lead_code')
        ) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropUnique('leads_crm_lead_code_unique');
                $table->dropColumn('crm_lead_code');
            });
        }
    }
}
