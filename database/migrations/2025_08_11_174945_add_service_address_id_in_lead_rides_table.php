<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddServiceAddressIdInLeadRidesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('lead_rides', function (Blueprint $table) {
            $table->uuid('service_address_id')->nullable()->after('to_place');
            $table->boolean('is_tba')->nullable()->default(0);
            $table->decimal('total_time', 5, 2)->nullable(); 
        });
    }

    public function down(): void
    {
        Schema::table('lead_rides', function (Blueprint $table) {
            $table->dropColumn('service_address_id');
        });
    }
}
