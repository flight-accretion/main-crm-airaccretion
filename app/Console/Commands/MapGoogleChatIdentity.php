<?php

namespace App\Console\Commands;

use App\Models\{GoogleChatIdentity, User};
use App\Services\GoogleChat\GoogleChatClient;
use Illuminate\Console\Command;

class MapGoogleChatIdentity extends Command
{
    protected $signature = 'google-chat:map-user {user : CRM user UUID} {google-user : Canonical users/ID} {--space=} {--confirm-identity}';
    protected $description = 'Record an administrator-verified CRM to Google identity mapping';

    public function handle(GoogleChatClient $client): int
    {
        $user = User::findOrFail($this->argument('user'));
        $google = (string) $this->argument('google-user');
        $space = (string) $this->option('space');
        if (!$this->option('confirm-identity') || !preg_match('~^users/[A-Za-z0-9_-]+$~D', $google)
            || !in_array($space, config('services.google_chat.allowed_spaces', []), true)) {
            $this->error('Provide an approved --space and --confirm-identity after verifying the actual person in Google Workspace.');
            return self::FAILURE;
        }
        $members = collect($client->listSpaceMembers($space));
        if (!$members->contains(fn ($m) => ($m['state'] ?? '') === 'JOINED' && data_get($m, 'member.name') === $google)) {
            $this->error('The Google identity is not a joined member of this Space.');
            return self::FAILURE;
        }
        $existing = GoogleChatIdentity::where('user_id', $user->id)->orWhere('google_user_name', $google)->first();
        if ($existing && ($existing->user_id !== $user->id || $existing->google_user_name !== $google)) {
            $this->error('Conflicting identity mapping exists; no changes made.');
            return self::FAILURE;
        }
        GoogleChatIdentity::updateOrCreate(['google_user_name' => $google], ['user_id' => $user->id, 'verified_at' => now()]);
        $this->info('Google identity mapping saved.');
        return self::SUCCESS;
    }
}
