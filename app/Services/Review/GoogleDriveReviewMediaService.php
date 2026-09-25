<?php

namespace App\Services\Review;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveReviewMediaService
{
    /**
     * Build authenticated Google client.
     *
     * No service-account JSON is used.
     * Authentication is OAuth 2.0 using:
     *
     * - Client ID
     * - Client Secret
     * - Refresh Token
     *
     * All credentials come from config/.env.
     */
    private function client(): Client
    {
        if (
            !config(
                'services.google_drive_review.enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'Google Drive review media is disabled.'
            );
        }

        $clientId =
            trim(
                (string) config(
                    'services.google_drive_review.client_id'
                )
            );

        $clientSecret =
            trim(
                (string) config(
                    'services.google_drive_review.client_secret'
                )
            );

        $refreshToken =
            trim(
                (string) config(
                    'services.google_drive_review.refresh_token'
                )
            );

        $apiKey =
            trim(
                (string) config(
                    'services.google_drive_review.api_key'
                )
            );

        if ($clientId === '') {
            throw new RuntimeException(
                'Google Drive client ID is missing.'
            );
        }

        if ($clientSecret === '') {
            throw new RuntimeException(
                'Google Drive client secret is missing.'
            );
        }

        if ($refreshToken === '') {
            throw new RuntimeException(
                'Google Drive refresh token is missing.'
            );
        }

        $client =
            new Client();

        $client->setApplicationName(
            'Accretion CRM Review Media'
        );

        $client->setClientId(
            $clientId
        );

        $client->setClientSecret(
            $clientSecret
        );

        /*
         * Needed so Google provides a refresh token
         * during the initial OAuth authorization.
         */
        $client->setAccessType(
            'offline'
        );

        /*
         * We need private Drive access.
         *
         * If you reuse an existing manually-created
         * Drive folder, DRIVE scope is the safest.
         */
        $client->setScopes([
            Drive::DRIVE,
        ]);

        /*
         * Optional API key.
         *
         * It may be used for project identification/quota,
         * but it is NOT the authorization credential.
         */
        if ($apiKey !== '') {
            $client->setDeveloperKey(
                $apiKey
            );
        }

        /*
         * Exchange refresh token for a fresh access token.
         *
         * Google Client automatically uses the returned
         * access token for subsequent Drive API calls.
         */
        $token =
            $client
                ->fetchAccessTokenWithRefreshToken(
                    $refreshToken
                );

        if (
            isset(
                $token['error']
            )
        ) {
            throw new RuntimeException(
                'Google Drive OAuth token refresh failed: '
                . (
                    $token['error_description']
                    ?? $token['error']
                )
            );
        }

        return $client;
    }

    /**
     * Google Drive API instance.
     */
    private function drive(): Drive
    {
        return new Drive(
            $this->client()
        );
    }

    /**
     * Upload customer media into the configured
     * private Google Drive folder.
     */
    public function upload(
        string $binary,
        string $name,
        string $mimeType
    ): array {
        $folderId =
            trim(
                (string) config(
                    'services.google_drive_review.folder_id'
                )
            );

        if ($folderId === '') {
            throw new RuntimeException(
                'Google Drive review folder ID is missing.'
            );
        }

        if ($binary === '') {
            throw new RuntimeException(
                'Cannot upload empty media to Google Drive.'
            );
        }

        $name =
            trim(
                $name
            );

        if ($name === '') {
            $name =
                'review-media-'
                . now()->format(
                    'Ymd-His'
                );
        }

        $mimeType =
            trim(
                $mimeType
            );

        if ($mimeType === '') {
            $mimeType =
                'application/octet-stream';
        }

        $metadata =
            new DriveFile([
                'name' =>
                    $name,

                'parents' => [
                    $folderId,
                ],
            ]);

        $file =
            $this->drive()
                ->files
                ->create(
                    $metadata,
                    [
                        'data' =>
                            $binary,

                        'mimeType' =>
                            $mimeType,

                        'uploadType' =>
                            'multipart',

                        'fields' =>
                            'id,name,mimeType,size,webViewLink,createdTime',
                    ]
                );

        if (
            !$file
            || empty(
                $file->id
            )
        ) {
            throw new RuntimeException(
                'Google Drive did not return an uploaded file ID.'
            );
        }

        return [
            'id' =>
                $file->id,

            'name' =>
                $file->name
                ?: $name,

            'mime_type' =>
                $file->mimeType
                ?: $mimeType,

            'size' =>
                $file->size,

            'view_url' =>
                $file->webViewLink,

            'created_time' =>
                $file->createdTime,
        ];
    }

    /**
     * Download/stream private Drive file.
     *
     * Useful for your authenticated CRM media proxy.
     */
    public function download(
        string $fileId
    ): array {
        $fileId =
            trim(
                $fileId
            );

        if ($fileId === '') {
            throw new RuntimeException(
                'Google Drive file ID is required.'
            );
        }

        $drive =
            $this->drive();

        $metadata =
            $drive
                ->files
                ->get(
                    $fileId,
                    [
                        'fields' =>
                            'id,name,mimeType,size',
                    ]
                );

        $response =
            $drive
                ->files
                ->get(
                    $fileId,
                    [
                        'alt' =>
                            'media',
                    ]
                );

        return [
            'binary' =>
                (string)
                $response
                    ->getBody(),

            'name' =>
                $metadata->name
                ?: 'media',

            'mime_type' =>
                $metadata->mimeType
                ?: 'application/octet-stream',

            'size' =>
                $metadata->size,
        ];
    }

    /**
     * Delete media from Google Drive.
     *
     * Useful for your 18-month retention process.
     */
    public function delete(
        string $fileId
    ): bool {
        $fileId =
            trim(
                $fileId
            );

        if ($fileId === '') {
            return false;
        }

        $this->drive()
            ->files
            ->delete(
                $fileId
            );

        return true;
    }

    /**
     * Check whether a Drive file exists.
     */
    public function exists(
        string $fileId
    ): bool {
        $fileId =
            trim(
                $fileId
            );

        if ($fileId === '') {
            return false;
        }

        try {

            $this->drive()
                ->files
                ->get(
                    $fileId,
                    [
                        'fields' =>
                            'id',
                    ]
                );

            return true;

        } catch (\Throwable $e) {

            return false;
        }
    }
}