<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeLeadApiKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:lead-api-key {--set : Write the generated key into .env} {--force : Overwrite existing LEAD_API_KEY in .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure LEAD_API_KEY and optionally write it to the .env file.';

    public function handle()
    {
        try {
            $key = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            $this->error('Failed to generate random bytes: ' . $e->getMessage());
            return 1;
        }

        if ($this->option('set')) {
            $envPath = base_path('.env');
            if (!file_exists($envPath)) {
                $this->error('.env file not found at ' . $envPath);
                return 1;
            }

            $contents = file_get_contents($envPath);
            if (preg_match('/^LEAD_API_KEY=.*$/m', $contents)) {
                if (!$this->option('force')) {
                    $this->warn('LEAD_API_KEY already exists in .env. Use --force to overwrite.');
                    $this->line('Generated key (not written): ' . $key);
                    return 0;
                }
                $contents = preg_replace('/^LEAD_API_KEY=.*$/m', 'LEAD_API_KEY=' . $key, $contents);
            } else {
                $contents = rtrim($contents, "\n") . PHP_EOL . 'LEAD_API_KEY=' . $key . PHP_EOL;
            }

            file_put_contents($envPath, $contents);
            $this->info('LEAD_API_KEY written to .env');
            $this->line('LEAD_API_KEY=' . $key);

            if ($this->confirm('Clear config cache now?')) {
                $this->call('config:clear');
            }

            return 0;
        }

        $this->info('Generated LEAD_API_KEY:');
        $this->line($key);
        $this->line('Run `php artisan make:lead-api-key --set` to write to .env');
        return 0;
    }
}
