<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DropUniqueEmailOnClients extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Postgres names unique indexes like "clients_email_unique"
            // Use raw statement to be safe across DB engines
            if (Schema::hasColumn('clients', 'email')) {
                try {
                    // For MySQL and SQLite
                    $table->dropUnique(['email']);
                } catch (\Exception $e) {
                    // Fallback for Postgres: drop index by name
                    DB::statement('DROP INDEX IF EXISTS clients_email_unique');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unique('email');
        });
    }
}
