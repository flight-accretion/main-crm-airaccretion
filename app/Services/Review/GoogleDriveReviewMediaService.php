<?php

namespace App\Services\Review;

class GoogleDriveReviewMediaService
{
    private function drive()
    {
        if (!class_exists(\Google\Client::class)) {
            throw new \RuntimeException('Google API client is not installed.');
        }

        $client = new \Google\Client();
        $client->setApplicationName('Accretion CRM Review Media');
        $client->setScopes([\Google\Service\Drive::DRIVE_FILE]);

        $path = config('services.google_drive_review.service_account_path');
        $base64 = config('services.google_drive_review.service_account_json_base64');

        if ($path) {
            $client->setAuthConfig($path);
        } elseif ($base64) {
            $json = base64_decode($base64, true);

            if ($json === false) {
                throw new \RuntimeException('Invalid Google Drive credential.');
            }

            $client->setAuthConfig(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw new \RuntimeException('Google Drive credentials missing.');
        }

        return new \Google\Service\Drive($client);
    }

    public function upload(string $binary, string $name, string $mime): array
    {
        if (!config('services.google_drive_review.enabled')) {
            throw new \RuntimeException('Google Drive review upload is disabled.');
        }

        $folder = config('services.google_drive_review.folder_id');

        if (!$folder) {
            throw new \RuntimeException('Google Drive review folder missing.');
        }

        $metadata = new \Google\Service\Drive\DriveFile([
            'name' => $name,
            'parents' => [$folder],
        ]);

        $file = $this->drive()->files->create($metadata, [
            'data' => $binary,
            'mimeType' => $mime,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,webViewLink',
        ]);

        return [
            'id' => $file->id,
            'name' => $file->name,
            'mime_type' => $file->mimeType,
            'view_url' => $file->webViewLink,
        ];
    }

    public function download(string $fileId): array
    {
        $response = $this->drive()->files->get($fileId, [
            'alt' => 'media',
        ]);

        return [
            'body' => (string) $response->getBody(),
            'mime_type' => $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
        ];
    }
}
