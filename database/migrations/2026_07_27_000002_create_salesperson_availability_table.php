<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalespersonAvailabilityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('salesperson_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('state')->default('offline');
            $table->boolean('is_available')->default(false);
            $table->boolean('is_opted_in')->default(false);
            $table->timestamp('last_popup_at')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('state');
            $table->index('is_available');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('salesperson_availability');
    }
}
