<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEnquiryTripSegmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('enquiry_trip_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_enquiry_id');
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place', 100)->nullable();
            $table->string('to_place', 100)->nullable();
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enquiry_trip_segments');
    }
}
