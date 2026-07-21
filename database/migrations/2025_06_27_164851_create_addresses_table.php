<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up(): void
    {
        Schema::create('service_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable(); 
            $table->uuid('service_id')->nullable();
            $table->string('contact_person_name', 100);
            $table->text('address');
            $table->string('contact_number', 20);
            $table->uuid('city_id'); 
            $table->string('map_link')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
}
