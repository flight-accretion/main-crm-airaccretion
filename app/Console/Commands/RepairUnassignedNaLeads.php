<?php

namespace App\Console\Commands;

use App\Services\RepairUnassignedNaLeadService;
use Illuminate\Console\Command;

class RepairUnassignedNaLeads extends Command
{
    protected $signature = 'leads:repair-unassigned-na
        {--days=10 : Number of past days to inspect}
        {--commit : Apply changes. Without this flag the command is dry-run only.}';

    protected $description =
        'Safely repair old unassigned leads whose assignee/status show N/A.';

    public function handle(
        RepairUnassignedNaLeadService $service
    ): int {
        $days = max(1, (int) $this->option('days'));
        $commit = (bool) $this->option('commit');

        $result = $service->repair(
            days: $days,
            commit: $commit
        );

        $this->line(
            'Dry run: ' . ($result['dry_run'] ? 'yes' : 'no')
        );
        $this->line('Days inspected: ' . $result['days']);
        $this->line('Candidate leads scanned: ' . $result['scanned']);
        $this->line(
            'Would delete duplicate N/A leads: '
            . $result['would_delete_duplicates']
        );
        $this->line(
            'Deleted duplicate N/A leads: '
            . $result['deleted_duplicates']
        );
        $this->line(
            'Would activate single N/A leads: '
            . $result['would_activate']
        );
        $this->line('Activated single N/A leads: ' . $result['activated']);
        $this->line('Would assign now: ' . $result['would_assign']);
        $this->line('Assigned now: ' . $result['assigned']);
        $this->line('Would queue: ' . $result['would_queue']);
        $this->line('Queued: ' . $result['queued']);
        $this->line('Skipped: ' . $result['skipped']);

        if (!$commit) {
            $this->warn(
                'No database changes were made. Re-run with --commit after reviewing the counts.'
            );
        }

        return Command::SUCCESS;
    }
}
