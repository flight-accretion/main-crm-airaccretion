<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiPreleadStateToWhatsappConversations extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'conversation_owner')) {
                $table->string('conversation_owner', 20)->default('AI')->index();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'ai_state')) {
                $table->json('ai_state')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'last_conversation_activity_at')) {
                $table->timestamp('last_conversation_activity_at')->nullable()->index();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'last_customer_message_at')) {
                $table->timestamp('last_customer_message_at')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'last_ai_message_at')) {
                $table->timestamp('last_ai_message_at')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'human_handoff_at')) {
                $table->timestamp('human_handoff_at')->nullable()->index();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'handoff_reason')) {
                $table->string('handoff_reason', 100)->nullable()->index();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'handoff_priority')) {
                $table->string('handoff_priority', 10)->nullable();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'human_summary')) {
                $table->text('human_summary')->nullable();
            }

            if (!Schema::hasColumn('whatsapp_conversations', 'activity_version')) {
                $table->unsignedInteger('activity_version')->default(0);
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            foreach (
                [
                    'conversation_owner',
                    'ai_state',
                    'last_conversation_activity_at',
                    'last_customer_message_at',
                    'last_ai_message_at',
                    'human_handoff_at',
                    'handoff_reason',
                    'handoff_priority',
                    'human_summary',
                    'activity_version',
                ] as $column
            ) {
                if (Schema::hasColumn('whatsapp_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
