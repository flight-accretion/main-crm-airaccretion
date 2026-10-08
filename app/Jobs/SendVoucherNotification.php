<?php

namespace App\Jobs;

use App\Http\Controllers\SendMessageController;
use App\Mail\VoucherMail;
use App\Models\Voucher;
use App\Services\ActivityAuditService;
use App\Support\SafeLogContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendVoucherNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;
    public array $backoff = [60, 300, 900];

    public function __construct(
        private string $voucherId,
        private string $channel,
        private array $payload = []
    ) {
    }

    public function handle(SendMessageController $sendMessageController): void
    {
        $voucher = Voucher::findOrFail($this->voucherId);

        try {
            if ($this->channel === 'email') {
                Mail::to($this->payload['recipient'] ?? null)->send(new VoucherMail(
                    $this->payload['template'],
                    $this->payload['subject'],
                    $this->payload['data'] ?? [],
                    $this->payload['file'] ?? null
                ));

                $this->markVoucherCustomerSent($voucher, 'email');
                $this->recordSentAudit($voucher, 'sent_email', $this->payload['recipient'] ?? null);
                return;
            }

            if ($this->channel === 'whatsapp') {
                $method = $this->payload['method'] ?? 'sendWhatsAppMessage';
                $args = $this->payload['args'] ?? [];

                if (!method_exists($sendMessageController, $method)) {
                    throw new \RuntimeException('Unsupported voucher WhatsApp sender.');
                }

                $result = $sendMessageController->{$method}(...$args);

                if (is_array($result) && array_key_exists('success', $result) && !$result['success']) {
                    throw new \RuntimeException($result['message'] ?? $result['error'] ?? 'Voucher WhatsApp send failed.');
                }

                $this->markVoucherCustomerSent($voucher, 'whatsapp');
                $this->recordSentAudit($voucher, 'sent_whatsapp', $args[0] ?? null);
                return;
            }

            throw new \RuntimeException('Unsupported voucher notification channel.');
        } catch (\Throwable $e) {
            Log::error('voucher_notification.failed', SafeLogContext::exception($e, [
                'voucher_id' => $this->voucherId,
                'channel' => $this->channel,
            ]));

            throw $e;
        }
    }

    private function markVoucherCustomerSent(Voucher $voucher, string $via): void
    {
        Voucher::query()
            ->where('id', $voucher->id)
            ->whereNull('customer_sent_at')
            ->update([
                'customer_sent_at' => now(),
                'customer_sent_by' => $voucher->operation_team_user_id ?: $voucher->created_by,
                'customer_sent_via' => $via,
                'updated_at' => now(),
            ]);
    }

    private function recordSentAudit(Voucher $voucher, string $action, ?string $recipient): void
    {
        app(ActivityAuditService::class)->record(
            'voucher',
            $action,
            $voucher,
            [],
            [
                'recipient' => $recipient,
                'customer_sent_at' => now(),
            ],
            [
                'lead_id' => $voucher->lead_id,
                'client_id' => $voucher->lead?->client_id,
                'channel' => $this->channel,
            ]
        );
    }
}
