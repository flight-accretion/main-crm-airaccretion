<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
      public function up()
    {
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->string('name', 150);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('states');
    }
}
