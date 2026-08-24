<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAiTrackingToWhatsappMessagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('whatsapp_messages', 'ai_reply_batch_id')) {
                    $table->uuid('ai_reply_batch_id')->nullable()->after('lead_followup_id');
                }

                if (!Schema::hasColumn('whatsapp_messages', 'ai_processed_at')) {
                    $table->timestamp('ai_processed_at')->nullable()->after('ai_reply_batch_id');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp_messages')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                if (Schema::hasColumn('whatsapp_messages', 'ai_processed_at')) {
                    $table->dropColumn('ai_processed_at');
                }

                if (Schema::hasColumn('whatsapp_messages', 'ai_reply_batch_id')) {
                    $table->dropColumn('ai_reply_batch_id');
                }
            });
        }
    }
}
