<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenGoogleChatBridge extends Migration
{
    public function up(): void
    {
        Schema::table('lead_chat_conversations', function (Blueprint $t) {
            $t->uuid('operations_user_id')->nullable()->index();
            $t->string('google_connection_status', 20)->default('unmapped');
            $t->uuid('google_template_message_id')->nullable();
            $t->timestamp('google_reconciled_at')->nullable();
        });
        DB::table('lead_chat_conversations')->whereNotNull('google_thread_name')
            ->update(['google_connection_status' => 'ready']);
        Schema::table('lead_chat_messages', function (Blueprint $t) {
            $t->unsignedInteger('google_sync_version')->default(0);
            $t->string('google_event_version', 64)->nullable();
            $t->unsignedInteger('google_synced_version')->default(0);
            $t->timestamp('google_sync_attempted_at')->nullable()->index();
        });
        Schema::create('google_chat_identities', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('google_user_name')->unique();
            $t->uuid('user_id')->nullable()->unique();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('google_chat_events', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('delivery_id')->unique();
            $t->string('event_type');
            $t->timestamp('event_time')->nullable();
            $t->json('payload');
            $t->string('status', 20)->default('pending')->index();
            $t->unsignedInteger('attempts')->default(0);
            $t->unsignedInteger('processed_count')->default(0);
            $t->text('last_error')->nullable();
            $t->timestamp('attempted_at')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        // A deletion can arrive before the create event or its thread mapping.
        Schema::create('google_chat_tombstones', function (Blueprint $t) {
            $t->string('message_name', 500)->primary();
            $t->timestamp('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_chat_tombstones');
        Schema::dropIfExists('google_chat_events');
        Schema::dropIfExists('google_chat_identities');
        Schema::table('lead_chat_messages', function (Blueprint $t) {
            $t->dropColumn(['google_sync_version', 'google_synced_version', 'google_sync_attempted_at', 'google_event_version']);
        });
        Schema::table('lead_chat_conversations', function (Blueprint $t) {
            $t->dropColumn(['operations_user_id', 'google_connection_status', 'google_template_message_id', 'google_reconciled_at']);
        });
    }
}
