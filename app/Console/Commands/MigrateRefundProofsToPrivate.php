<?php

namespace App\Console\Commands;

use App\Models\LeadRefund;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateRefundProofsToPrivate extends Command
{
    protected $signature = 'refund-proofs:privatize {--execute} {--delete-public}';

    protected $description = 'Verify and copy referenced customer refund proofs to private storage; dry-run by default';

    public function handle(): int
    {
        if ($this->option('delete-public') && !$this->option('execute')) {
            $this->error('--delete-public requires --execute.');
            return 1;
        }

        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $failed = 0;
        foreach (LeadRefund::whereNotNull('refund_proof')->select('refund_proof')->distinct()->cursor() as $refund) {
            $path = $refund->refund_proof;
            if (!preg_match('~\A(?:refunds|customer-refunds)/[a-z0-9_-]+\.(?:pdf|jpe?g|png)\z~i', $path)) {
                $this->error('Invalid referenced path.');
                $failed++;
                continue;
            }

            try {
                if (!$public->exists($path)) {
                    if (!$private->exists($path)) {
                        throw new \RuntimeException('Referenced proof is missing.');
                    }
                    $this->line('Already private: '.$path);
                    continue;
                }
                $bytes = $public->get($path);
                $hash = hash('sha256', $bytes);
                if ($private->exists($path) && hash('sha256', $private->get($path)) !== $hash) {
                    throw new \RuntimeException('Private proof conflicts with public proof.');
                }
                if (!$this->option('execute')) {
                    $this->line('Would verify/copy: '.$path);
                    continue;
                }
                if (!$private->exists($path) && !$private->put($path, $bytes)) {
                    throw new \RuntimeException('Private write failed.');
                }
                if (hash('sha256', $private->get($path)) !== $hash) {
                    throw new \RuntimeException('Copied proof checksum differs.');
                }
                if ($this->option('delete-public')) {
                    // Recheck both copies immediately before removing the public original.
                    if (hash('sha256', $public->get($path)) !== $hash
                        || hash('sha256', $private->get($path)) !== $hash
                        || !$public->delete($path)) {
                        throw new \RuntimeException('Public proof removal failed verification.');
                    }
                }
                $this->info('Verified: '.$path);
            } catch (\Throwable $error) {
                $this->error($error->getMessage());
                $failed++;
            }
        }

        return $failed ? 1 : 0;
    }
}
