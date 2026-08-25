<?php

namespace Tests\Unit;

use App\Services\WhatsAppAiReplyService;
use Tests\TestCase;

class ProcessWhatsAppAiRepliesCommandTest extends TestCase
{
    public function test_watch_mode_polls_due_ai_replies_inside_the_minute(): void
    {
        $this->mock(
            WhatsAppAiReplyService::class,
            function ($mock) {
                $mock->shouldReceive('processDue')
                    ->with(5)
                    ->atLeast()
                    ->twice()
                    ->andReturn([
                        'processed' => 0,
                        'failed' => 0,
                    ]);
            }
        );

        $this->artisan(
            'whatsapp:process-ai-replies',
            [
                '--limit' => 5,
                '--watch' => 0.03,
                '--sleep' => 0.01,
            ]
        )->assertExitCode(0);
    }
}
