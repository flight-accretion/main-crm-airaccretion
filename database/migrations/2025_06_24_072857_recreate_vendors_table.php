<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateVendorsTable extends Migration
{
    public function up(): void
    {           Schema::dropIfExists('vendors');

        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique(); // NOT NULL, UNIQUE
            $table->string('email', 100)->unique(); // NOT NULL, UNIQUE
            $table->unsignedBigInteger('contact_number'); // Changed from VARCHAR(20) to BIGINT
            $table->uuid('city_id'); // NOT NULL
            $table->text('address'); // NOT NULL
            $table->string('profile_image', 255)->nullable(); // Country calling code / image path
            $table->text('map_link')->nullable(); // Google Maps URL
            $table->boolean('status')->default(true); // TRUE = Active, FALSE = Inactive
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
}
