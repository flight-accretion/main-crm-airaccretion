<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PruneCrmFiles extends Command
{
    protected $signature = 'crm:prune-files
        {--dry-run : Show files without deleting them}
        {--log-days= : Override custom log retention days}
        {--temp-days= : Override temporary file retention days}';

    protected $description = 'Prune old custom logs and known temporary CRM files.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $logDays = max(1, (int) ($this->option('log-days') ?: config('crm.custom_log_retention_days', 14)));
        $tempDays = max(1, (int) ($this->option('temp-days') ?: config('crm.temp_file_retention_days', 7)));

        $deletedLogs = $this->pruneLogs($logDays, $dryRun);
        $deletedTemp = $this->pruneTemporaryFiles($tempDays, $dryRun);

        $this->line('custom_logs: ' . $deletedLogs . ($dryRun ? ' eligible' : ' deleted'));
        $this->line('temporary_files: ' . $deletedTemp . ($dryRun ? ' eligible' : ' deleted'));

        return self::SUCCESS;
    }

    private function pruneLogs(int $days, bool $dryRun): int
    {
        $logDir = storage_path('logs');

        if (!is_dir($logDir)) {
            return 0;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $count = 0;

        foreach (File::files($logDir) as $file) {
            $name = $file->getFilename();

            if (!str_ends_with($name, '.log')) {
                continue;
            }

            if ($file->getMTime() >= $cutoff) {
                continue;
            }

            $count++;

            if (!$dryRun) {
                @unlink($file->getPathname());
            }
        }

        return $count;
    }

    private function pruneTemporaryFiles(int $days, bool $dryRun): int
    {
        $directories = [
            storage_path('app/temp'),
            storage_path('app/tmp'),
            storage_path('app/private/temp'),
            storage_path('app/private/tmp'),
            storage_path('framework/cache/data'),
        ];

        $cutoff = now()->subDays($days)->getTimestamp();
        $count = 0;

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getMTime() >= $cutoff) {
                    continue;
                }

                $count++;

                if (!$dryRun) {
                    @unlink($file->getPathname());
                }
            }
        }

        return $count;
    }
}
