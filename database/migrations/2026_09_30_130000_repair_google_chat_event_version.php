<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RepairGoogleChatEventVersion extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('lead_chat_messages', 'google_event_version')) {
            Schema::table('lead_chat_messages', function (Blueprint $table) {
                $table->string('google_event_version', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Forward-only repair: the original hardening migration also owns this column.
    }
}
