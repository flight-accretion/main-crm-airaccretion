<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRideReminderLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
   public function up()
{
    Schema::create('ride_reminder_logs', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->uuid('ride_id');
        $table->uuid('lead_id');

        // 5, 3, or 1 day reminder
        $table->tinyInteger('reminder_type');

        $table->string('template_name')->nullable();

        $table->timestamp('sent_at');

        $table->timestamps();

        $table->softDeletes();

        $table->index(['ride_id', 'reminder_type']);
        $table->index('lead_id');

        $table->foreign('ride_id')
            ->references('id')
            ->on('lead_rides')
            ->onDelete('cascade');

        $table->foreign('lead_id')
            ->references('id')
            ->on('leads')
            ->onDelete('cascade');
    });
}

public function down()
{
    Schema::dropIfExists('ride_reminder_logs');
}
}
