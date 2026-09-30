<?php

namespace App\Console\Commands;

use App\Services\GoogleChat\GoogleChatClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class ListGoogleChatMembers extends Command
{
    protected $signature = 'google-chat:list-members {space : spaces/ID from list-spaces}';
    protected $description = 'Discover canonical Google user IDs without changing memberships';

    public function handle(GoogleChatClient $client): int
    {
        try {
            $members = $client->listSpaceMembers((string) $this->argument('space'));
            $this->table(['Display name', 'Google user ID', 'State'], array_map(fn ($m) => [
                data_get($m, 'member.displayName', ''), data_get($m, 'member.name', ''), $m['state'] ?? '',
            ], $members));
            $this->line('Verify each identity with Workspace administration before using google-chat:map-user.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Membership lookup failed ('.class_basename($e).').');
            if ($e instanceof RequestException) {
                $this->line('HTTP '.$e->response->status().' '.$e->response->json('error.status'));
            }
            $this->line('For insufficient scope, re-authorize at /admin/google-chat/oauth with chat.memberships.readonly.');
            return self::FAILURE;
        }
    }
}
