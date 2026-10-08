<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRideReminderLogsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('ride_reminder_logs')) {
            return;
        }

        Schema::create('ride_reminder_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('ride_id');
            $table->uuid('lead_id')->nullable();

            $table->unsignedSmallInteger('hours_before');
            $table->string('channel', 30);
            $table->string('recipient')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['ride_id', 'hours_before', 'channel'], 'ride_reminder_logs_unique_delivery');
            $table->index('ride_id');
            $table->index('lead_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ride_reminder_logs');
    }
}