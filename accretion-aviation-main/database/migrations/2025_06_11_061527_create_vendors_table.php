<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVendorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('email', 100)->unique();
            $table->string('contact_number', 20);
            $table->string('city', 100);
            $table->text('address');
            $table->string('country_id', 100)->nullable();
            $table->text('map_link')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vendors');
    }
}
