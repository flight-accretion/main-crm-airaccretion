<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ProductionSafetyCheck extends Command
{
    protected $signature = 'crm:production-safety-check';

    protected $description = 'Check production readiness without printing secrets.';

    private int $errors = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->info('CRM production safety check');

        $this->checkBoolean('APP_DEBUG must be false in production.', !config('app.debug'));
        $this->checkBoolean('APP_KEY is configured.', filled(config('app.key')));
        $this->checkBoolean('APP_URL is configured.', filled(config('app.url')));

        if (app()->environment('production')) {
            $this->checkBoolean('QUEUE_CONNECTION should not be sync in production.', config('queue.default') !== 'sync');
        } else {
            $this->warnLine('APP_ENV is not production. This is okay for local verification.');
        }

        $this->checkWritable(storage_path('logs'));
        $this->checkWritable(storage_path('framework'));

        $this->checkDatabaseTable('jobs');
        $this->checkDatabaseTable('failed_jobs');

        if (config('crm_backup.enabled')) {
            $this->checkConfig('Google Drive DB backup folder is configured.', 'services.google_drive_backup.db_folder_id');
            $this->checkConfig('Google Drive code backup folder is configured.', 'services.google_drive_backup.code_folder_id');
            $this->checkBoolean(
                'Local backup cleanup after successful Drive upload is enabled.',
                (bool) config('crm_backup.delete_local_after_upload')
            );
        } else {
            $this->warnLine('CRM backup is disabled.');
        }

        if (config('whatcrm.enabled', true) || config('whatcrm.legacy_lead_api_enabled', true)) {
            $this->checkConfig('WhatCRM webhook token is configured.', 'whatcrm.token');
        }

        $this->checkConfig('Call summary API token is configured.', 'call_summary.token');

        if (config('services.skyrack.enabled')) {
            $this->checkConfig('Skyrack token is configured.', 'services.skyrack.token');
        }

        if (config('services.google_chat.enabled')) {
            $this->checkConfig('Google Chat Pub/Sub audience is configured.', 'services.google_chat.pubsub_audience');
            $this->checkConfig('Google Chat service account is configured.', 'services.google_chat.pubsub_service_account');
        }

        $this->line('Warnings: ' . $this->warnings);
        $this->line('Errors: ' . $this->errors);

        return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkBoolean(string $label, bool $passed): void
    {
        if ($passed) {
            $this->info('[OK] ' . $label);
            return;
        }

        $this->errors++;
        $this->error('[FAIL] ' . $label);
    }

    private function checkConfig(string $label, string $key): void
    {
        $this->checkBoolean($label, filled(config($key)));
    }

    private function checkWritable(string $path): void
    {
        try {
            File::ensureDirectoryExists($path);

            $probe = rtrim($path, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '.crm-write-check-' . getmypid();

            File::put($probe, 'ok');
            File::delete($probe);

            $this->info('[OK] ' . $path . ' is writable.');
        } catch (\Throwable $e) {
            $this->errors++;
            $this->error('[FAIL] ' . $path . ' is writable.');
        }
    }

    private function checkDatabaseTable(string $table): void
    {
        try {
            $this->checkBoolean($table . ' table exists.', Schema::hasTable($table));
        } catch (\Throwable $e) {
            $this->errors++;
            $this->error('[FAIL] Could not check ' . $table . ' table: ' . substr($e->getMessage(), 0, 300));
        }
    }

    private function warnLine(string $label): void
    {
        $this->warnings++;
        $this->warn('[WARN] ' . $label);
    }
}
