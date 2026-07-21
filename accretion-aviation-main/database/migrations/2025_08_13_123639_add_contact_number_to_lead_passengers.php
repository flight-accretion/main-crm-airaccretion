<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContactNumberToLeadPassengers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('lead_passengers', function (Blueprint $table) {
            $table->dropColumn('contact_number');
        });
    }
}
