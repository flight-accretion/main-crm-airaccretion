<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadMultipleTripAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lead_multiple_trip_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID primary key
            $table->uuid('voucher_id')->nullable();
            $table->text('from_address')->nullable(); 
            $table->string('from_vendor')->nullable(); 
            $table->text('to_address')->nullable();
            $table->string('to_vendor')->nullable(); 
            $table->boolean('is_tba')->default(false); 
            $table->timestamp('ride_date_time')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_multiple_trip_addresses');
    }
}
