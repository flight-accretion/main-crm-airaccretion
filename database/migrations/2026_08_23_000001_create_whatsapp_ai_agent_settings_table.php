<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappAiAgentSettingsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_ai_agent_settings')) {
            Schema::create(
                'whatsapp_ai_agent_settings',
                function (Blueprint $table) {
                    $table->uuid('id')->primary();
                    $table->boolean('enabled')->default(false);
                    $table->boolean('auto_reply_enabled')->default(false);
                    $table->string('provider', 50)->default('openai');
                    $table->string('model')->default('gpt-4o-mini');
                    $table->text('prompt')->nullable();
                    $table->text('api_key_encrypted')->nullable();
                    $table->unsignedInteger('buffer_seconds')->default(10);
                    $table->timestamps();
                }
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_ai_agent_settings');
    }
}
