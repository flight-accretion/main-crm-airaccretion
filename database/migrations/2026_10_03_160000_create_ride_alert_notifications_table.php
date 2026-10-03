<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ride_alert_notifications')) {
            return;
        }

        Schema::create('ride_alert_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('ride_id')->nullable();
            $table->uuid('recipient_user_id')->nullable();
            $table->string('recipient_number', 30);
            $table->string('alert_type', 40);
            $table->date('ride_date');
            $table->timestamp('due_at');
            $table->string('status', 30)->default('pending');
            $table->string('template_name', 100);
            $table->json('template_variables')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['lead_id', 'ride_date']);
            $table->unique(
                ['lead_id', 'recipient_number', 'ride_date', 'alert_type'],
                'ride_alert_unique_delivery'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_alert_notifications');
    }
};
