<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lead_followups')) {
            return;
        }

        Schema::table('lead_followups', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_followups', 'source')) {
                $table->string('source', 20)->default('manual')->index();
            }

            if (!Schema::hasColumn('lead_followups', 'followup_type')) {
                $table->string('followup_type', 50)->default('general')->index();
            }

            if (!Schema::hasColumn('lead_followups', 'ride_date')) {
                $table->date('ride_date')->nullable()->index();
            }

            if (!Schema::hasColumn('lead_followups', 'due_at')) {
                $table->timestamp('due_at')->nullable()->index();
            }

            if (!Schema::hasColumn('lead_followups', 'completed_by')) {
                $table->uuid('completed_by')->nullable()->index();
            }

            if (!Schema::hasColumn('lead_followups', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_followups')) {
            return;
        }

        Schema::table('lead_followups', function (Blueprint $table) {
            foreach ([
                'completed_at',
                'completed_by',
                'due_at',
                'ride_date',
                'followup_type',
                'source',
            ] as $column) {
                if (Schema::hasColumn('lead_followups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
