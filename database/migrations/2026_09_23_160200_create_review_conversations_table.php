<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_conversations')) {
            return;
        }

        Schema::create('review_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('operation_case_id')->nullable();
            $table->uuid('ai_agent_id')->nullable();
            $table->uuid('whatsapp_conversation_id')->nullable();
            $table->string('customer_phone', 30);
            $table->string('status', 40)->default('waiting_for_reply');
            $table->string('sentiment', 20)->nullable();
            $table->string('intent', 30)->nullable();
            $table->boolean('customer_replied')->default(false);
            $table->boolean('needs_human')->default(false);
            $table->unsignedTinyInteger('reminder_count')->default(0);
            $table->timestamp('initial_message_sent_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('next_reminder_at')->nullable();
            $table->timestamp('review_link_sent_at')->nullable();
            $table->timestamp('operations_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('ai_state')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['status', 'next_reminder_at']);
            $table->index('operation_case_id');
            $table->index('whatsapp_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_conversations');
    }
};
