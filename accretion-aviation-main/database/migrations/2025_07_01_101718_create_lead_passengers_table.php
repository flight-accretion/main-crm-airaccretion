<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadPassengersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
     public function up(): void
    {
        Schema::create('lead_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary(); 
            $table->uuid('voucher_id'); 
            $table->string('name'); 
            $table->integer('age')->nullable();
            $table->string('traveller_type')->nullable(); // Traveller type (Adult/Child/etc.)
            $table->decimal('weight', 5, 2)->nullable(); 
            $table->text('front_document')->nullable(); // Front ID document
            $table->text('back_document')->nullable(); // Back ID document
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_passengers');
    }
}
