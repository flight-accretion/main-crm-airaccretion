<?php

namespace App\Jobs;

use App\Models\Msg91WhatsAppSender;
use App\Services\WhatsApp\Msg91\Msg91TemplateSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMsg91SenderTemplates implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $timeout = 60;
    public $uniqueFor = 120;
    public $backoff = [30, 120];
    public function __construct(public string $senderId) {}
    public function uniqueId(): string { return $this->senderId; }
    public function handle(Msg91TemplateSyncService $sync): void
    {
        $sender = Msg91WhatsAppSender::find($this->senderId);
        if ($sender?->isVerifiedActive()) $sync->syncSender($sender);
    }
}
