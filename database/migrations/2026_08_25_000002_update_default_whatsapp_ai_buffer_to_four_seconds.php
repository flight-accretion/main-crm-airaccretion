<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateDefaultWhatsappAiBufferToFourSeconds extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('whatsapp_ai_agent_settings')) {
            return;
        }

        DB::table('whatsapp_ai_agent_settings')
            ->where('buffer_seconds', 10)
            ->update([
                'buffer_seconds' => 4,
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        if (!Schema::hasTable('whatsapp_ai_agent_settings')) {
            return;
        }

        DB::table('whatsapp_ai_agent_settings')
            ->where('buffer_seconds', 4)
            ->update([
                'buffer_seconds' => 10,
                'updated_at' => now(),
            ]);
    }
}
