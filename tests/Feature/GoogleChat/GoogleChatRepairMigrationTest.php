<?php

namespace Tests\Feature\GoogleChat;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GoogleChatRepairMigrationTest extends GoogleChatTestCase
{
    public function test_repairs_previously_applied_hardening_and_is_safe_on_fresh_install(): void
    {
        $this->harden();
        Schema::table('lead_chat_messages', fn (Blueprint $t) => $t->dropColumn('google_event_version'));
        require_once database_path('migrations/2026_09_30_130000_repair_google_chat_event_version.php');
        $migration = new \RepairGoogleChatEventVersion;
        $migration->up();
        $this->assertTrue(Schema::hasColumn('lead_chat_messages', 'google_event_version'));
        $migration->up();
        $migration->down();
        $this->assertTrue(Schema::hasColumn('lead_chat_messages', 'google_event_version'));
    }
}
