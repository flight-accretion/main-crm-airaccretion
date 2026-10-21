<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLES = ['lead_refunds', 'vendor_refunds'];

    public function up(): void
    {
        // Additive and nullable: existing refunds keep NULL (legacy, no approval recorded) and are not back-filled.
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'approval_status')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('approval_status', 20)->nullable()->index();
                $table->uuid('approval_decided_by')->nullable();
                $table->timestamp('approval_decided_at')->nullable();
                $table->text('approval_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'approval_status')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex([ 'approval_status' ]);
                $table->dropColumn(['approval_status', 'approval_decided_by', 'approval_decided_at', 'approval_note']);
            });
        }
    }
};
