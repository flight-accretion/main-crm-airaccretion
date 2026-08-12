<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailLeadLogsTable extends Migration
{
    public function up()
    {
        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('message_id', 500)->unique();
            $table->string('imap_uid', 100)->nullable();

            $table->string('sender_email', 255);
            $table->string('recipient_email', 255)->nullable();
            $table->text('subject')->nullable();

            $table->string('customer_name', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();

            $table->string('service_name', 255)->nullable();

            $table->date('departure_date')->nullable();
            $table->string('departure_time', 50)->nullable();
            $table->integer('passenger_count')->nullable();

            $table->longText('email_body')->nullable();
            $table->json('parsed_data')->nullable();

            $table->uuid('lead_id')->nullable();

            $table->string(
                'processing_status',
                50
            )->default('received');

            $table->text('processing_message')->nullable();

            $table->timestamp(
                'received_at'
            )->nullable();

            $table->timestamp(
                'processed_at'
            )->nullable();

            $table->timestamp(
                'followup_created_at'
            )->nullable();

            $table->timestamps();

            $table->index('lead_id');
            $table->index('sender_email');
            $table->index('processing_status');
            $table->index('received_at');
            $table->index('customer_phone');
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_lead_logs');
    }
}