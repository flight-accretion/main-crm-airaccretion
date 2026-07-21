<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCityColumnInClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop the old city column (assuming it's string/integer)
            $table->dropColumn('city');

            // Add the new city_id column as UUID
            $table->uuid('city_id')->after('country_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop the new city_id column
            $table->dropColumn('city_id');

            $table->string('city')->nullable();
        });
    }
}
