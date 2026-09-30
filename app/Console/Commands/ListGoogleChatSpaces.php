<?php

namespace App\Console\Commands;

use App\Services\GoogleChat\GoogleChatClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class ListGoogleChatSpaces extends Command
{
    protected $signature = 'google-chat:list-spaces';
    protected $description = 'List Spaces visible to the configured Google Chat integration account';

    public function handle(GoogleChatClient $client): int
    {
        try {
            $spaces = $client->listSpaces();
            $this->table(['Space name', 'Resource ID'], array_map(
                fn ($space) => [$space['displayName'] ?? 'Unnamed', $space['name']], $spaces
            ));
            $this->info('Use the selected resource IDs in GOOGLE_CHAT_ALLOWED_SPACES (comma-separated).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Google Chat discovery failed ('.class_basename($e).').');
            if ($e instanceof RequestException) {
                $this->line('HTTP '.$e->response->status().' '.$e->response->json('error.status'));
            }
            $this->line('Check OAuth credentials and re-authorize with the Chat scopes. No configuration changed.');
            return self::FAILURE;
        }
    }
}
