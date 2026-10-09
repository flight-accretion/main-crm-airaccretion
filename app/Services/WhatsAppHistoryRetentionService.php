<?php

namespace App\Services;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\Review\GoogleDriveReviewMediaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppHistoryRetentionService
{
    public function __construct(private GoogleDriveReviewMediaService $drive)
    {
    }

    public function cutoff(): Carbon
    {
        return now()->subMonthsNoOverflow(max(1, (int) config('crm.whatsapp_history_retention_months', 6)));
    }

    public function retained($query, ?Carbon $cutoff = null)
    {
        return $query->whereRaw('COALESCE(message_at, created_at) >= ?', [$cutoff ?: $this->cutoff()]);
    }

    public function expired($query, ?Carbon $cutoff = null)
    {
        return $query->whereRaw('COALESCE(message_at, created_at) < ?', [$cutoff ?: $this->cutoff()]);
    }

    public function prune(bool $dryRun = false): array
    {
        $cutoff = $this->cutoff();
        $query = $this->expired(WhatsAppMessage::query(), $cutoff);
        $summary = ['messages' => 0, 'media_files' => 0, 'failed' => 0];
        if ($dryRun) {
            $summary['messages'] = (clone $query)->count();
            $summary['media_files'] = (clone $query)->whereNotNull('google_drive_file_id')
                ->distinct()->count('google_drive_file_id');
            return $summary;
        }

        $query->select('id')->chunkById(200, function ($messages) use ($cutoff, &$summary) {
            foreach ($messages as $candidate) {
                try {
                    $result = DB::transaction(function () use ($candidate, $cutoff) {
                        $message = $this->expired(WhatsAppMessage::query(), $cutoff)
                            ->whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$message) {
                            return [0, 0];
                        }
                        $deletedMedia = 0;
                        if ($message->google_drive_file_id && !$this->retained(WhatsAppMessage::query(), $cutoff)
                            ->where('google_drive_file_id', $message->google_drive_file_id)->exists()) {
                            try {
                                if (!$this->drive->delete($message->google_drive_file_id)) {
                                    throw new \RuntimeException('Drive media deletion did not complete.');
                                }
                            } catch (\Google\Service\Exception $error) {
                                // A file already removed on a previous attempt needs no further deletion.
                                if ((int) $error->getCode() !== 404) {
                                    throw $error;
                                }
                            }
                            $deletedMedia = 1;
                        }

                        $message->delete();
                        if ($message->direction === 'incoming' && !$message->crm_read_at) {
                            WhatsAppConversation::whereKey($message->conversation_id)->update([
                                'unread_count' => DB::raw('CASE WHEN unread_count > 0 THEN unread_count - 1 ELSE 0 END'),
                            ]);
                        }
                        WhatsAppConversation::whereKey($message->conversation_id)
                            ->where('last_message_at', '<', $cutoff)->update([
                                'last_message' => null, 'last_message_at' => null,
                            ]);
                        return [1, $deletedMedia];
                    });
                    $summary['messages'] += $result[0];
                    $summary['media_files'] += $result[1];
                } catch (\Throwable $error) {
                    $summary['failed']++;
                    Log::warning('WhatsApp history retention failed; message retained for retry.', [
                        'message_id' => $candidate->id, 'error' => $error->getMessage(),
                    ]);
                }
            }
        });
        return $summary;
    }
}
