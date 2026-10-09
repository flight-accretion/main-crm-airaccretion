<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL . $error->getTraceAsString() . PHP_EOL);
    exit(1);
});

use App\Http\Controllers\WhatsAppInboxController;
use App\Models\User;
use App\Models\UserType;
use App\Services\Review\GoogleDriveReviewMediaService;
use App\Services\WhatsAppConversationVisibilityService;
use App\Services\WhatsAppHistoryRetentionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function verifyHistory(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $description);
    }
    echo 'PASS: ' . $description . PHP_EOL;
}

// No production/local business rows or remote files are touched by this script.
config(['database.default' => 'sqlite', 'database.connections.sqlite' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
], 'crm.whatsapp_history_retention_months' => 6]);
DB::purge('sqlite');
Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00'));
Schema::create('users', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('name'); });
Schema::create('leads', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('crm_lead_code'); $t->index('crm_lead_code', 'p10_leads_crm_code_idx');
});
Schema::create('lead_followups', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); });
Schema::create('whatsapp_contacts', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('name')->nullable(); });
Schema::create('whatsapp_conversations', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->uuid('contact_id')->nullable(); $t->uuid('assigned_user_id')->nullable();
    $t->uuid('lead_id')->nullable(); $t->text('last_message')->nullable(); $t->timestamp('last_message_at')->nullable();
    $t->integer('unread_count')->default(0); $t->string('status')->default('open'); $t->timestamps();
});
Schema::create('whatsapp_messages', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->uuid('conversation_id'); $t->uuid('sender_user_id')->nullable();
    $t->string('direction')->default('incoming'); $t->text('body')->nullable(); $t->text('raw_payload')->nullable();
    $t->timestamp('message_at')->nullable(); $t->timestamp('crm_read_at')->nullable();
    $t->string('google_drive_file_id')->nullable(); $t->timestamps();
});
$drive = new class extends GoogleDriveReviewMediaService {
    public array $deleted = [];
    public bool $fail = true;
    public function delete(string $fileId): bool {
        if ($fileId === 'retry' && $this->fail) { throw new RuntimeException('simulated Drive failure'); }
        $this->deleted[] = $fileId;
        return true;
    }
};
$retention = new WhatsAppHistoryRetentionService($drive);
$app->instance(WhatsAppHistoryRetentionService::class, $retention);
$user = new User(['name' => 'Fixture']);
$user->id = (string) Str::uuid();
$user->setRelation('userType', new UserType(['user_type' => UserType::SUPER_ADMIN]));
DB::table('users')->insert(['id' => $user->id, 'name' => $user->name]);
Auth::setUser($user);
$conversation = (string) Str::uuid();
DB::table('whatsapp_conversations')->insert(['id' => $conversation, 'assigned_user_id' => $user->id,
    'last_message' => 'expired preview', 'last_message_at' => now()->subMonths(7), 'unread_count' => 5,
    'created_at' => now(), 'updated_at' => now()]);
$insert = function ($at, ?string $file = null) use ($conversation) {
    $id = (string) Str::uuid();
    DB::table('whatsapp_messages')->insert(['id' => $id, 'conversation_id' => $conversation,
        'body' => 'fixture content', 'raw_payload' => '{"private":"fixture"}', 'message_at' => $at,
        'google_drive_file_id' => $file, 'created_at' => $at ?: now()->subMonths(7), 'updated_at' => now()]);
    return $id;
};
for ($i = 0; $i < 123; $i++) { $insert(now()->subDays(1)); }
$boundary = $insert($retention->cutoff());
$shared = $insert(now()->subDays(2), 'shared');
$expired = $insert(now()->subMonths(7), 'old-file');
$retry = $insert(now()->subMonths(7), 'retry');
$insert(now()->subMonths(7), 'shared');
$insert(null);
$controller = new WhatsAppInboxController();
$visibility = new WhatsAppConversationVisibilityService();
$cursor = null;
$seen = [];
$sizes = [];
do {
    $request = Request::create('/fixture', 'GET', $cursor ? ['before_id' => $cursor] : []);
    $response = $controller->messages($request, $conversation, $visibility)->getData(true);
    $sizes[] = count($response['messages']);
    $keys = array_column($response['messages'], 'sort_key');
    $sorted = $keys; sort($sorted);
    verifyHistory($keys === $sorted, 'Page preserves chronological order including tied timestamps');
    $seen = array_merge($seen, array_column($response['messages'], 'id'));
    $cursor = $response['meta']['next_before_id'];
} while ($response['meta']['has_more']);
verifyHistory($sizes === [50, 50, 25], 'Latest 50 and older pages cover all six-month history');
verifyHistory(count(array_unique($seen)) === 125 && in_array($boundary, $seen, true)
    && !in_array($expired, $seen, true), 'No duplicates, exact cutoff retained, expired content excluded');
verifyHistory($response['conversation']['last_message'] === null, 'Expired preview hidden before cleanup');
$foreignConversation = (string) Str::uuid();
DB::table('whatsapp_conversations')->insert(['id' => $foreignConversation, 'assigned_user_id' => (string) Str::uuid()]);
$foreignMessage = (string) Str::uuid();
DB::table('whatsapp_messages')->insert(['id' => $foreignMessage, 'conversation_id' => $foreignConversation,
    'message_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
try {
    $controller->messages(Request::create('/fixture', 'GET', ['before_id' => $foreignMessage]), $conversation, $visibility);
    throw new RuntimeException('Foreign cursor was accepted');
} catch (Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    verifyHistory(true, 'Cursor cannot cross conversation boundaries');
}
$user->setRelation('userType', new UserType(['user_type' => UserType::SALES_EXECUTIVE]));
verifyHistory($visibility->canAccessConversation($user, $conversation)
    && !$visibility->canAccessConversation($user, $foreignConversation), 'Sales own-conversation scope unchanged');
try {
    $controller->messages(Request::create('/fixture'), $foreignConversation, $visibility);
    throw new RuntimeException('Foreign conversation was accepted');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    verifyHistory($e->getStatusCode() === 403, 'Out-of-scope history request is forbidden');
}
$before = DB::table('whatsapp_messages')->count();
verifyHistory($retention->prune(true)['messages'] === 4 && DB::table('whatsapp_messages')->count() === $before
    && $drive->deleted === [], 'Dry run does not delete messages or media');
$result = $retention->prune();
verifyHistory($result['messages'] === 3 && $result['failed'] === 1
    && DB::table('whatsapp_messages')->where('id', $retry)->exists(), 'Drive failure retains content for retry');
verifyHistory(in_array('old-file', $drive->deleted, true) && !in_array('shared', $drive->deleted, true)
    && DB::table('whatsapp_messages')->where('id', $shared)->exists(), 'Expired media removed, retained shared media protected');
$drive->fail = false;
verifyHistory($retention->prune()['messages'] === 1 && $retention->prune()['messages'] === 0,
    'Retry succeeds and repeated cleanup is safe');
verifyHistory(DB::table('whatsapp_conversations')->where('id', $conversation)->value('unread_count') === 1
    && DB::table('whatsapp_conversations')->where('id', $conversation)->value('last_message') === null,
    'Cleanup clears expired preview and adjusts unread count without underflow');

Schema::create('email_lead_logs', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('message_id'); $t->string('status'); $t->timestamps();
});
DB::table('email_lead_logs')->insert(['id' => (string) Str::uuid(), 'message_id' => 'historic-email',
    'status' => 'processed', 'created_at' => now()->subYears(2), 'updated_at' => now()]);
Artisan::call('crm:prune-technical-data');
verifyHistory(DB::table('email_lead_logs')->count() === 1
    && $app->make(App\Services\EmailLeadService::class)->process(['message_id' => 'historic-email'])['status'] === 'duplicate_email',
    'Historic email logs survive cleanup and still prevent duplicate lead ingestion');
Schema::create('google_chat_events', function (Blueprint $t) {
    $t->uuid('id')->primary(); $t->string('status'); $t->timestamps();
});
foreach (['pending', 'failed', 'processed'] as $status) {
    DB::table('google_chat_events')->insert(['id' => (string) Str::uuid(), 'status' => $status,
        'created_at' => now()->subMonths(3), 'updated_at' => now()]);
}
DB::table('google_chat_events')->insert(['id' => (string) Str::uuid(), 'status' => 'pending',
    'created_at' => now()->subMonth(), 'updated_at' => now()]);
Artisan::call('google-chat:prune-technical-logs');
verifyHistory(DB::table('google_chat_events')->count() === 1, 'Google Chat cleanup deletes all old statuses, preserves recent events');

require dirname(__DIR__, 2) . '/database/migrations/2026_10_09_180000_add_p10_queue_search_cleanup_indexes.php';
$migration = new AddP10QueueSearchCleanupIndexes();
$migration->up();
$indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads');
verifyHistory(!isset($indexes['p10_leads_crm_code_search_idx']), 'Index migration skips equivalent crm_lead_code index');
Schema::table('leads', fn (Blueprint $t) => $t->index('crm_lead_code', 'p10_leads_crm_code_search_idx'));
require dirname(__DIR__, 2) . '/database/migrations/2026_10_09_190000_remove_duplicate_crm_lead_code_index.php';
$repair = new RemoveDuplicateCrmLeadCodeIndex();
$repair->up(); $repair->up();
$indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads');
verifyHistory(isset($indexes['p10_leads_crm_code_idx']) && !isset($indexes['p10_leads_crm_code_search_idx']),
    'Repair removes duplicate only and is repeatable');
Schema::table('leads', fn (Blueprint $t) => $t->dropIndex('p10_leads_crm_code_idx'));
Schema::table('leads', fn (Blueprint $t) => $t->index('crm_lead_code', 'p10_leads_crm_code_search_idx'));
$repair->up();
verifyHistory(isset(DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads')['p10_leads_crm_code_search_idx']),
    'Repair preserves the only crm_lead_code index');
Carbon::setTestNow();
