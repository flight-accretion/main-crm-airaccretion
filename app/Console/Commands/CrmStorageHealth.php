<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CrmStorageHealth extends Command
{
    protected $signature = 'crm:storage-health';

    protected $description = 'Report CRM storage, logs, backups, and disk health.';

    public function handle(): int
    {
        $paths = [
            'storage' => storage_path(),
            'logs' => storage_path('logs'),
            'backups' => (string) config('crm_backup.temp_dir'),
            'uploads_public' => storage_path('app/public'),
        ];

        foreach ($paths as $label => $path) {
            $bytes = is_dir($path) ? $this->directorySize($path) : 0;
            $this->line($label . ': ' . $this->humanSize($bytes) . ' (' . $path . ')');
        }

        $total = @disk_total_space(storage_path()) ?: 0;
        $free = @disk_free_space(storage_path()) ?: 0;
        $usedPercent = $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;

        $this->line('disk_used: ' . $usedPercent . '%');
        $this->line('disk_free: ' . $this->humanSize((int) $free));

        $warning = (int) config('crm.disk_warning_percent', 85);

        if ($usedPercent >= $warning) {
            Log::warning('crm.storage.high_disk_usage', [
                'used_percent' => $usedPercent,
                'threshold' => $warning,
                'free_bytes' => $free,
                'total_bytes' => $total,
            ]);
        }

        return self::SUCCESS;
    }

    private function directorySize(string $path): int
    {
        $size = 0;

        foreach (File::allFiles($path) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = max(0, $bytes);
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, 2) . ' ' . $units[$index];
    }
}
