<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppAiReply;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppAiQueueCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'cleanup_test', 'database.connections.cleanup_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'whatcrm.ai_queue_connection' => 'database', 'whatcrm.ai_queue' => 'whatsapp-ai',
            'whatcrm.ai_processing_stale_minutes' => 30]);
        DB::purge('cleanup_test');
        Carbon::setTestNow('2026-10-22 16:00:00');
        Http::fake(); Mail::fake(); Queue::fake();
        Schema::create('whatsapp_ai_reply_batches', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('status'); $t->timestamp('locked_at')->nullable();
            $t->timestamp('process_after')->nullable(); $t->timestamp('processed_at')->nullable();
            $t->text('error')->nullable(); $t->timestamps();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('queue'); $t->text('payload');
            $t->integer('attempts')->default(0); $t->integer('reserved_at')->nullable();
            $t->integer('available_at'); $t->integer('created_at');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('cleanup_test');
        parent::tearDown();
    }

    public function test_auto_dispatch_is_off_by_default(): void
    {
        // No whatsapp-ai worker runs in production; the scheduler sends replies.
        $this->assertStringContainsString(
            "env('WHATCRM_AI_AUTO_DISPATCH', false)",
            file_get_contents(config_path('whatcrm.php'))
        );
    }

    public function test_abandoned_processing_batch_is_skipped_but_active_one_is_not(): void
    {
        $abandoned = $this->batch('processing', now()->subHours(2));
        $active = $this->batch('processing', now()->subMinutes(5));
        $this->artisan('whatsapp:cleanup-ai-queue')->assertExitCode(0);
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $abandoned, 'status' => 'skipped']);
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $active, 'status' => 'processing']);
        Http::assertNothingSent(); Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_only_noop_jobs_are_removed(): void
    {
        $keep = [];
        foreach (['sent', 'skipped', 'handoff', 'failed'] as $status) $this->job($this->batch($status));
        $this->job((string) Str::uuid()); // batch no longer exists
        $keep[] = $this->job($this->batch('pending'));
        $keep[] = $this->job($this->batch('processing', now()->subMinutes(5)));
        $keep[] = $this->job($this->batch('sent'), ['reserved_at' => now()->timestamp]);
        $keep[] = $this->job($this->batch('sent'), ['queue' => 'default']);
        $keep[] = $this->job($this->batch('sent'), ['payload' => '{bad json']);

        $this->artisan('whatsapp:cleanup-ai-queue')->assertExitCode(0);

        $this->assertEqualsCanonicalizing($keep, DB::table('jobs')->pluck('id')->all());
        Http::assertNothingSent(); Mail::assertNothingSent(); Queue::assertNothingPushed();
    }

    public function test_dry_run_changes_nothing_and_rerun_is_idempotent(): void
    {
        $stale = $this->batch('processing', now()->subDays(20));
        $this->job($stale);
        $this->job($this->batch('sent'));

        $this->artisan('whatsapp:cleanup-ai-queue', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $stale, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 2);

        // The stale batch is skipped first, so its job is a no-op in the same run.
        $this->artisan('whatsapp:cleanup-ai-queue')->assertExitCode(0);
        $this->assertDatabaseCount('jobs', 0);
        $this->artisan('whatsapp:cleanup-ai-queue')->assertExitCode(0);
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $stale, 'status' => 'skipped']);
    }

    public function test_large_backlog_drains_over_runs_within_limit(): void
    {
        $sent = $this->batch('sent');
        for ($i = 0; $i < 900; $i++) $this->job($sent);
        Artisan::call('whatsapp:cleanup-ai-queue', ['--limit' => 500]);
        $this->assertDatabaseCount('jobs', 400);
        Artisan::call('whatsapp:cleanup-ai-queue', ['--limit' => 500]);
        $this->assertDatabaseCount('jobs', 0);
    }

    private function batch(string $status, ?Carbon $lock = null): string
    {
        $id = (string) Str::uuid();
        DB::table('whatsapp_ai_reply_batches')->insert(['id' => $id, 'status' => $status, 'locked_at' => $lock,
            'process_after' => $lock, 'created_at' => $lock ?? now(), 'updated_at' => $lock ?? now()]);
        return $id;
    }

    private function job(string $batchId, array $overrides = []): int
    {
        return DB::table('jobs')->insertGetId(array_merge(['queue' => 'whatsapp-ai', 'payload' => json_encode([
            'displayName' => ProcessWhatsAppAiReply::class, 'data' => [
                'commandName' => ProcessWhatsAppAiReply::class, 'command' => serialize(new ProcessWhatsAppAiReply($batchId)),
            ],
        ]), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->subDays(2)->timestamp], $overrides));
    }
}
