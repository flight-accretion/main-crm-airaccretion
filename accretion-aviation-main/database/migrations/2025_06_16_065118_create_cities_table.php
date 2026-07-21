<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->decimal('lat', 10, 6)->nullable();
            $table->decimal('lng', 10, 6)->nullable();
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('state_id')->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('utc', 20)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cities');
    }
}
