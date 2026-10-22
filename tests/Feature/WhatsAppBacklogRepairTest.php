<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppAiReply;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppBacklogRepairTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'repair_test', 'database.connections.repair_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'whatcrm.ai_queue_connection' => 'database', 'whatcrm.ai_queue' => 'whatsapp-ai',
            'queue.connections.database.connection' => null]);
        DB::purge('repair_test');
        Carbon::setTestNow('2026-10-22 13:21:08');
        Storage::fake('local');
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
        DB::disconnect('repair_test');
        parent::tearDown();
    }

    public function test_dry_run_does_not_change_data_or_create_backup(): void
    {
        $id = $this->batch('processing', '2026-09-26 14:01:50');
        $this->job($id);
        $this->assertSame(0, Artisan::call('crm:repair-whatsapp-ai-backlog'));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $id, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_apply_is_bounded_and_backed_up_without_sending(): void
    {
        $stale = $this->batch('processing', '2026-09-26 14:01:50');
        $sent = $this->batch('sent');
        $handoff = $this->batch('handoff');
        $failed = $this->batch('failed');
        $pending = $this->batch('pending');
        $other = $this->batch('processing', '2026-10-01 10:00:00');
        foreach ([$stale, $sent, $handoff, $failed, $pending, $other, (string) Str::uuid()] as $id) $this->job($id);
        $this->job($sent, ['reserved_at' => now()->timestamp]);
        $this->job($sent, ['attempts' => 1]);
        $this->job($sent, ['queue' => 'default']);
        $this->job($sent, ['payload' => '{bad json']);
        $this->assertSame(0, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $stale, 'status' => 'skipped']);
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $other, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 8);
        $files = Storage::disk('local')->allFiles('private/repairs');
        $this->assertCount(1, $files);
        $backup = json_decode(Crypt::decryptString(Storage::disk('local')->get($files[0])), true);
        $this->assertCount(1, $backup['batches']);
        $this->assertCount(3, $backup['jobs']);
        $this->assertSame('processing', $backup['batches'][0]['status']);
        Http::assertNothingSent(); Mail::assertNothingSent(); Queue::assertNothingPushed();
        $this->assertSame(0, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseCount('jobs', 8);
        $this->assertCount(1, Storage::disk('local')->allFiles('private/repairs'));
    }

    public function test_ambiguous_stale_lock_or_excess_cleanup_aborts_without_changes(): void
    {
        $a = $this->batch('processing', '2026-09-26 14:01:50');
        $b = $this->batch('processing', '2026-09-26 14:01:50');
        $this->assertSame(1, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $a, 'status' => 'processing']);
        DB::table('whatsapp_ai_reply_batches')->where('id', $b)->delete();
        for ($i = 0; $i < 751; $i++) $this->job($a);
        $this->assertSame(1, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $a, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 751);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_backup_failure_prevents_all_database_changes(): void
    {
        $id = $this->batch('processing', '2026-10-07 16:07:05');
        $this->job($id);
        $disk = \Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($disk);
        $this->assertSame(1, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $id, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_fresh_jobs_and_recently_touched_incident_batch_are_retained(): void
    {
        $id = $this->batch('processing', '2026-10-07 16:07:05');
        DB::table('whatsapp_ai_reply_batches')->where('id', $id)->update(['updated_at' => now()]);
        $this->job($id);
        $sent = $this->batch('sent');
        $this->job($sent, ['created_at' => now()->timestamp]);
        $this->job($sent, ['available_at' => now()->addDay()->timestamp]);
        $this->assertSame(0, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertDatabaseHas('whatsapp_ai_reply_batches', ['id' => $id, 'status' => 'processing']);
        $this->assertDatabaseCount('jobs', 3);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_wrong_queue_database_is_rejected_and_retry_window_exceeds_job_timeouts(): void
    {
        $this->assertSame(240, config('queue.connections.database.retry_after'));
        foreach ([\App\Jobs\ProcessLeadAiScore::class, \App\Jobs\SendBookingReminderNotification::class,
            \App\Jobs\SendVoucherNotification::class] as $class) {
            $timeout = (new \ReflectionClass($class))->getDefaultProperties()['timeout'];
            $this->assertGreaterThan($timeout, config('queue.connections.database.retry_after'));
        }
        config(['queue.connections.database.connection' => 'other_database']);
        $this->assertSame(1, Artisan::call('crm:repair-whatsapp-ai-backlog', ['--apply' => true]));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    private function batch(string $status, ?string $lock = null): string
    {
        $id = (string) Str::uuid();
        DB::table('whatsapp_ai_reply_batches')->insert(['id' => $id, 'status' => $status,
            'locked_at' => $lock, 'process_after' => $lock, 'created_at' => $lock ?? now(), 'updated_at' => $lock ?? now()]);
        return $id;
    }

    private function job(string $id, array $overrides = []): void
    {
        DB::table('jobs')->insert(array_merge(['queue' => 'whatsapp-ai', 'payload' => json_encode([
            'displayName' => ProcessWhatsAppAiReply::class, 'data' => [
                'commandName' => ProcessWhatsAppAiReply::class, 'command' => serialize(new ProcessWhatsAppAiReply($id)),
            ],
        ]), 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->subDays(2)->timestamp], $overrides));
    }
}
