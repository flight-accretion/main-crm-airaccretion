<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->tinyInteger('status')->default(1);
            $table->string('alpha_code', 5)->nullable();
            $table->string('symbol', 10)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('isd_code', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('countries');
    }
}
