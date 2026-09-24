<?php

namespace App\Services\Review;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppMediaService
{
    public function download(WhatsAppMessage $message): array
    {
        $mediaId = $message->media_provider_id
            ?: data_get($message->raw_payload, 'media.id')
            ?: data_get($message->raw_payload, 'image.id')
            ?: data_get($message->raw_payload, 'video.id')
            ?: data_get($message->raw_payload, 'document.id')
            ?: data_get($message->raw_payload, 'audio.id');

        if (!$mediaId) {
            throw new \RuntimeException('WhatsApp media id not found.');
        }

        $token = config('services.meta_whatsapp.token');

        if (!$token) {
            throw new \RuntimeException('Meta WhatsApp token is not configured.');
        }

        $metadata = Http::withToken($token)
            ->acceptJson()
            ->get('https://graph.facebook.com/v18.0/' . $mediaId)
            ->throw()
            ->json();

        $url = data_get($metadata, 'url');

        if (!$url) {
            throw new \RuntimeException('Meta media URL not found.');
        }

        $response = Http::withToken($token)
            ->get($url)
            ->throw();

        $mime = data_get($metadata, 'mime_type')
            ?: $response->header('Content-Type')
            ?: 'application/octet-stream';

        return [
            'binary' => $response->body(),
            'name' => $message->media_file_name ?: $mediaId . '.' . $this->extension($mime),
            'mime_type' => $mime,
            'provider_id' => $mediaId,
        ];
    }

    private function extension(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
