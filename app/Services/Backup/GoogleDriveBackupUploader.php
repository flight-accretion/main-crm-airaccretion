<?php

namespace App\Services\Backup;

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

class GoogleDriveBackupUploader
{
    private const CHUNK_SIZE =
        10 * 1024 * 1024;


    public function uploadAndVerify(
        string $localPath,
        string $remoteName,
        string $folderId,
        string $mimeType
    ): array {

        if (
            !is_file($localPath)
            || filesize($localPath) <= 0
        ) {
            throw new RuntimeException(
                'Backup file does not exist or is empty: '
                . $localPath
            );
        }


        $folderId =
            trim(
                $folderId
            );


        if ($folderId === '') {
            throw new RuntimeException(
                'Google Drive backup folder ID is missing.'
            );
        }


        $client =
            $this->client();


        $drive =
            new Drive(
                $client
            );


        $metadata =
            new DriveFile([
                'name' =>
                    $remoteName,

                'parents' => [
                    $folderId,
                ],
            ]);


        /*
         * Resumable/chunk upload.
         *
         * A potentially large CRM ZIP is therefore not
         * loaded completely into PHP memory.
         */
        $client->setDefer(
            true
        );


        $handle =
            null;


        try {

            $request =
                $drive
                    ->files
                    ->create(
                        $metadata,
                        [
                            'fields' =>
                                'id,name,size,md5Checksum,webViewLink,createdTime',

                            'supportsAllDrives' =>
                                true,
                        ]
                    );


            $media =
                new MediaFileUpload(
                    $client,
                    $request,
                    $mimeType,
                    null,
                    true,
                    self::CHUNK_SIZE
                );


            $localSize =
                (int)
                filesize(
                    $localPath
                );


            $media->setFileSize(
                $localSize
            );


            $handle =
                fopen(
                    $localPath,
                    'rb'
                );


            if (!$handle) {
                throw new RuntimeException(
                    'Unable to open backup file: '
                    . $localPath
                );
            }


            $result =
                false;


            while (
                !$result
                && !feof($handle)
            ) {

                $chunk =
                    fread(
                        $handle,
                        self::CHUNK_SIZE
                    );


                if ($chunk === false) {
                    throw new RuntimeException(
                        'Unable to read backup file during Google Drive upload.'
                    );
                }


                if (
                    $chunk === ''
                    && feof($handle)
                ) {
                    break;
                }


                $result =
                    $media
                        ->nextChunk(
                            $chunk
                        );
            }

        } finally {

            if (
                is_resource($handle)
            ) {
                fclose(
                    $handle
                );
            }


            $client->setDefer(
                false
            );
        }


        if (
            !$result
            || empty($result->id)
        ) {
            throw new RuntimeException(
                'Google Drive upload did not return a file ID.'
            );
        }


        /*
         * Fetch the file again from Google.
         *
         * Upload completed != verification completed.
         */
        $remote =
            $drive
                ->files
                ->get(
                    $result->id,
                    [
                        'fields' =>
                            'id,name,size,md5Checksum,webViewLink,createdTime',

                        'supportsAllDrives' =>
                            true,
                    ]
                );


        $remoteSize =
            (int)
            ($remote->size ?? 0);


        $localSize =
            (int)
            filesize(
                $localPath
            );


        if (
            $remoteSize !== $localSize
        ) {
            throw new RuntimeException(
                'Google Drive verification failed: '
                . 'file size mismatch for '
                . $remoteName
            );
        }


        $localMd5 =
            md5_file(
                $localPath
            );


        $remoteMd5 =
            trim(
                (string)
                ($remote->md5Checksum ?? '')
            );


        if (
            $remoteMd5 !== ''
            && $localMd5 !== false
            && !hash_equals(
                strtolower($localMd5),
                strtolower($remoteMd5)
            )
        ) {
            throw new RuntimeException(
                'Google Drive verification failed: '
                . 'MD5 mismatch for '
                . $remoteName
            );
        }


        return [

            'id' =>
                $remote->id,

            'name' =>
                $remote->name,

            'size' =>
                $remoteSize,

            'md5' =>
                $remoteMd5,

            'url' =>
                $remote->webViewLink,

            'created_time' =>
                $remote->createdTime,
        ];
    }


    private function client(): Client
    {
        $clientId =
            trim(
                (string)
                config(
                    'services.google_drive_backup.client_id'
                )
            );


        $clientSecret =
            trim(
                (string)
                config(
                    'services.google_drive_backup.client_secret'
                )
            );


        $refreshToken =
            trim(
                (string)
                config(
                    'services.google_drive_backup.refresh_token'
                )
            );


        $apiKey =
            trim(
                (string)
                config(
                    'services.google_drive_backup.api_key'
                )
            );


        if ($clientId === '') {
            throw new RuntimeException(
                'Google Drive backup client ID is missing.'
            );
        }


        if ($clientSecret === '') {
            throw new RuntimeException(
                'Google Drive backup client secret is missing.'
            );
        }


        if ($refreshToken === '') {
            throw new RuntimeException(
                'Google Drive backup refresh token is missing.'
            );
        }


        $client =
            new Client();


        $client->setApplicationName(
            'Accretion CRM Nightly Backup'
        );


        $client->setClientId(
            $clientId
        );


        $client->setClientSecret(
            $clientSecret
        );


        $client->setAccessType(
            'offline'
        );


        $client->setScopes([
            Drive::DRIVE,
        ]);


        if ($apiKey !== '') {
            $client->setDeveloperKey(
                $apiKey
            );
        }


        $token =
            $client
                ->fetchAccessTokenWithRefreshToken(
                    $refreshToken
                );


        if (
            !empty(
                $token['error']
            )
        ) {
            throw new RuntimeException(
                'Google Drive OAuth refresh failed: '
                . (
                    $token['error_description']
                    ?? $token['error']
                )
            );
        }


        if (
            empty(
                $token['access_token']
            )
        ) {
            throw new RuntimeException(
                'Google Drive access token was not returned.'
            );
        }


        return $client;
    }
}