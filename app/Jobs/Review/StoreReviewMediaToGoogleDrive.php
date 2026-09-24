<?php

namespace App\Jobs\Review;

use App\Models\ReviewConversation;
use App\Models\WhatsAppMessage;
use App\Services\Review\GoogleDriveReviewMediaService;
use App\Services\Review\MetaWhatsAppMediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StoreReviewMediaToGoogleDrive implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $messageId,
        public string $reviewId
    ) {
    }

    public function handle(
        MetaWhatsAppMediaService $meta,
        GoogleDriveReviewMediaService $drive
    ): void {
        $message = WhatsAppMessage::find($this->messageId);
        $review = ReviewConversation::find($this->reviewId);

        if (!$message || !$review || $message->google_drive_file_id) {
            return;
        }

        if (!config('services.google_drive_review.enabled')) {
            Log::info('Review media Drive upload skipped because it is disabled.', [
                'message_id' => $message->id,
                'review_id' => $review->id,
            ]);

            return;
        }

        $media = $meta->download($message);
        $stored = $drive->upload(
            $media['binary'],
            $media['name'],
            $media['mime_type']
        );

        $message->update([
            'media_provider' => 'meta',
            'media_provider_id' => $media['provider_id'],
            'media_mime_type' => $media['mime_type'],
            'media_file_name' => $stored['name'],
            'google_drive_file_id' => $stored['id'],
            'google_drive_view_url' => $stored['view_url'],
        ]);
    }
}
