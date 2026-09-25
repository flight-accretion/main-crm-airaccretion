<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('operation_cases')
            && !Schema::hasColumn('operation_cases', 'next_followup_at')
        ) {
            Schema::table('operation_cases', function (Blueprint $table) {
                $table->timestamp('next_followup_at')->nullable()->after('completed_at');
                $table->index(['type', 'status', 'next_followup_at'], 'operation_cases_type_status_next_idx');
            });
        }

        if (
            Schema::hasTable('lead_followups')
            && !Schema::hasColumn('lead_followups', 'operation_case_id')
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->uuid('operation_case_id')->nullable()->after('parent_followup_id');
                $table->index(['operation_case_id', 'created_at'], 'lead_followups_operation_case_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('lead_followups')
            && Schema::hasColumn('lead_followups', 'operation_case_id')
        ) {
            Schema::table('lead_followups', function (Blueprint $table) {
                $table->dropIndex('lead_followups_operation_case_created_idx');
                $table->dropColumn('operation_case_id');
            });
        }

        if (
            Schema::hasTable('operation_cases')
            && Schema::hasColumn('operation_cases', 'next_followup_at')
        ) {
            Schema::table('operation_cases', function (Blueprint $table) {
                $table->dropIndex('operation_cases_type_status_next_idx');
                $table->dropColumn('next_followup_at');
            });
        }
    }
};
