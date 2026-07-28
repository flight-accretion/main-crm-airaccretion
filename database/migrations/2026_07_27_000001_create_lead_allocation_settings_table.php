<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadAllocationSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lead_allocation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_start_time')->default('10:30');
            $table->string('office_end_time')->default('19:30');
            $table->integer('popup_interval_minutes')->default(60);
            $table->integer('minimum_leads_before_popup')->default(1);
            $table->boolean('auto_allocation_enabled')->default(true);
            $table->string('allocation_method')->default('balanced');
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
        Schema::dropIfExists('lead_allocation_settings');
    }
}
