<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivateNaLeadStatus extends Command
{
    private const ACTIVE_STATUS = 1;
    private const VALID_STATUSES = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];

    protected $signature = 'leads:activate-na-status
        {--commit : Apply changes. Without this flag the command is dry-run only.}';

    protected $description =
        'Set leads whose displayed status is N/A to Active without changing allocation data.';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $result = [
            'scanned' => 0,
            'na_found' => 0,
            'would_create' => 0,
            'would_update' => 0,
            'created' => 0,
            'updated' => 0,
        ];

        Lead::query()
            ->select('id', 'representative_user_id')
            ->orderBy('id')
            ->chunk(200, function ($leads) use ($commit, &$result) {
                if ($commit) {
                    DB::transaction(function () use (
                        $leads,
                        &$result
                    ) {
                        $this->processLeads(
                            $leads,
                            true,
                            $result
                        );
                    });

                    return;
                }

                $this->processLeads(
                    $leads,
                    false,
                    $result
                );
            });

        $this->line('Dry run: ' . ($commit ? 'no' : 'yes'));
        $this->line('Leads scanned: ' . $result['scanned']);
        $this->line('N/A status leads found: ' . $result['na_found']);
        $this->line(
            'Would create active follow-ups: '
            . $result['would_create']
        );
        $this->line(
            'Would update latest follow-ups: '
            . $result['would_update']
        );
        $this->line(
            'Created active follow-ups: '
            . $result['created']
        );
        $this->line(
            'Updated latest follow-ups: '
            . $result['updated']
        );

        if (!$commit) {
            $this->warn(
                'No database changes were made. Re-run with --commit after reviewing the counts.'
            );
        }

        return Command::SUCCESS;
    }

    private function processLeads(
        $leads,
        bool $commit,
        array &$result
    ): void {
        $latestFollowups = LeadFollowup::query()
            ->whereIn('lead_id', $leads->pluck('id')->all())
            ->orderBy('lead_id')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lead_id')
            ->map(
                fn ($followups) => $followups->first()
            );

        foreach ($leads as $lead) {
            $result['scanned']++;
            $latestFollowup = $latestFollowups->get($lead->id);

            if (!$latestFollowup) {
                $result['na_found']++;
                $result['would_create']++;

                if ($commit) {
                    LeadFollowup::query()->create([
                        'id' => (string) Str::uuid(),
                        'lead_id' => $lead->id,
                        'next_followup_date' => null,
                        'followup_note' =>
                            'One-time repair: N/A status set to Active.',
                        'followed_by' => $lead->representative_user_id,
                        'status' => self::ACTIVE_STATUS,
                    ]);

                    $result['created']++;
                }

                continue;
            }

            if ($this->isValidStatus($latestFollowup->status)) {
                continue;
            }

            $result['na_found']++;
            $result['would_update']++;

            if ($commit) {
                $latestFollowup->status = self::ACTIVE_STATUS;
                $latestFollowup->save();
                $result['updated']++;
            }
        }
    }

    private function isValidStatus($status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        if (!is_numeric($status)) {
            return false;
        }

        return in_array(
            (int) $status,
            self::VALID_STATUSES,
            true
        );
    }
}
