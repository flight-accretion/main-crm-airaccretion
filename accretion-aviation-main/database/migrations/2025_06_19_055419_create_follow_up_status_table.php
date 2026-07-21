<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFollowUpStatusTable extends Migration
{ 
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('follow_up_status', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('follow_up_status');
    }
}
