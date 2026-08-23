<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappConversationTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_contacts')) {
            Schema::create('whatsapp_contacts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('normalized_phone', 30)->unique();
                $table->string('raw_phone', 50)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('whatsapp_conversations')) {
            Schema::create('whatsapp_conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('contact_id');
                $table->uuid('lead_id')->nullable();
                $table->uuid('assigned_user_id')->nullable();
                $table->string('whatcrm_chat_id')->nullable();
                $table->string('status', 30)->default('open');
                $table->text('last_message')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->timestamps();

                $table->unique('contact_id');
                $table->index('lead_id');
                $table->index('assigned_user_id');
                $table->index('whatcrm_chat_id');
                $table->index('last_message_at');
                $table->index('unread_count');

                $table->foreign('contact_id')
                    ->references('id')
                    ->on('whatsapp_contacts')
                    ->cascadeOnDelete();

                $table->foreign('lead_id')
                    ->references('id')
                    ->on('leads')
                    ->nullOnDelete();

                $table->foreign('assigned_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('whatsapp_messages')) {
            Schema::create('whatsapp_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('conversation_id');
                $table->uuid('lead_followup_id')->nullable();
                $table->string('provider_message_id', 500)->nullable()->unique();
                $table->string('direction', 20);
                $table->string('sender_type', 30);
                $table->uuid('sender_user_id')->nullable();
                $table->string('message_type', 30)->default('text');
                $table->text('body')->nullable();
                $table->string('provider_status', 50)->nullable();
                $table->timestamp('message_at')->nullable();
                $table->timestamp('crm_read_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->index('conversation_id');
                $table->index('lead_followup_id');
                $table->index('direction');
                $table->index('sender_user_id');
                $table->index('message_at');
                $table->index('crm_read_at');

                $table->foreign('conversation_id')
                    ->references('id')
                    ->on('whatsapp_conversations')
                    ->cascadeOnDelete();

                $table->foreign('lead_followup_id')
                    ->references('id')
                    ->on('lead_followups')
                    ->nullOnDelete();

                $table->foreign('sender_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('whatcrm_agent_mappings')) {
            Schema::create('whatcrm_agent_mappings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('whatcrm_agent_id')->nullable();
                $table->string('whatcrm_agent_name')->nullable();
                $table->uuid('crm_user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('whatcrm_agent_id');
                $table->index('whatcrm_agent_name');
                $table->index('crm_user_id');
                $table->index('is_active');

                $table->foreign('crm_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatcrm_agent_mappings');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_contacts');
    }
}
