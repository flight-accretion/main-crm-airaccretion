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
        Schema::table('lead_passengers', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_passengers', 'registration_slug')) {
                $table->string('registration_slug', 64)->nullable()->after('registration_token');
                $table->unique('registration_slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            if (Schema::hasColumn('lead_passengers', 'registration_slug')) {
                $table->dropUnique(['registration_slug']);
                $table->dropColumn('registration_slug');
            }
        });
    }
};
