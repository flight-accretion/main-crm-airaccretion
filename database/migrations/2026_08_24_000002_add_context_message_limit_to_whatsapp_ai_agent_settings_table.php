<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContextMessageLimitToWhatsappAiAgentSettingsTable extends Migration
{
    public function up()
    {
        if (
            Schema::hasTable('whatsapp_ai_agent_settings')
            && !Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'context_message_limit'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->unsignedInteger('context_message_limit')
                        ->default(10000)
                        ->after('buffer_seconds');
                }
            );
        }
    }

    public function down()
    {
        if (
            Schema::hasTable('whatsapp_ai_agent_settings')
            && Schema::hasColumn(
                'whatsapp_ai_agent_settings',
                'context_message_limit'
            )
        ) {
            Schema::table(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->dropColumn('context_message_limit');
                }
            );
        }
    }
}
