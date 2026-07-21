<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sync_at')) {
                // tinyInteger to store 0 (not synced) / 1 (synced)
                $table->tinyInteger('sync_at')->default(0)->after('status')->comment('0 = not synced, 1 = synced');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'sync_at')) {
                $table->dropColumn('sync_at');
            }
        });
    }
};
