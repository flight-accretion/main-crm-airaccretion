<?php

namespace App\Console\Commands;

use App\Models\LeadFollowup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearHiddenTodayFollowups extends Command
{
    protected $signature = 'leads:clear-hidden-today-followups
        {--date= : Target date in YYYY-MM-DD format. Defaults to today.}
        {--apply : Apply changes. Without this flag the command is dry-run only.}
        {--limit=0 : Maximum stale follow-ups to report or clear. 0 means no limit.}
        {--chunk=200 : Number of candidate follow-ups to scan per chunk.}';

    protected $description =
        'Clear stale due dates from today/missed follow-ups when the latest lead status is cancelled, confirmed, or rejected.';

    public function handle(): int
    {
        try {
            $targetDate = $this->option('date')
                ? Carbon::parse((string) $this->option('date'))->startOfDay()
                : Carbon::now()->startOfDay();
        } catch (\Throwable $exception) {
            $this->error('Invalid --date value. Use YYYY-MM-DD.');

            return Command::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $chunkSize = max(1, (int) $this->option('chunk'));

        $result = [
            'scanned' => 0,
            'stale' => 0,
            'cleared' => 0,
        ];
        $samples = [];

        $this->candidateQuery($targetDate)
            ->chunkById(
                $chunkSize,
                function ($followups) use (
                    $apply,
                    $limit,
                    &$result,
                    &$samples
                ) {
                    $latestByLead = $this->latestFollowupsByLead(
                        $followups
                            ->pluck('lead_id')
                            ->unique()
                            ->values()
                            ->all()
                    );

                    foreach ($followups as $followup) {
                        $result['scanned']++;

                        $latestFollowup =
                            $latestByLead->get($followup->lead_id);

                        if (
                            !$latestFollowup
                            || !LeadFollowup::hiddenFromTodayFollowups(
                                $latestFollowup->status
                            )
                        ) {
                            continue;
                        }

                        $result['stale']++;

                        if (count($samples) < 10) {
                            $samples[] = [
                                'lead_id' => $followup->lead_id,
                                'stale_followup_id' => $followup->id,
                                'stale_due_at' => optional(
                                    $followup->next_followup_date
                                )->format('Y-m-d H:i:s'),
                                'latest_followup_id' => $latestFollowup->id,
                                'latest_status' => $latestFollowup->status,
                            ];
                        }

                        if ($apply) {
                            DB::transaction(function () use (
                                $followup,
                                &$result
                            ) {
                                LeadFollowup::query()
                                    ->whereKey($followup->id)
                                    ->update([
                                        'next_followup_date' => null,
                                        'updated_at' => now(),
                                    ]);

                                $result['cleared']++;
                            });
                        }

                        if ($limit > 0 && $result['stale'] >= $limit) {
                            return false;
                        }
                    }
                },
                'id'
            );

        $this->line('Dry run: ' . ($apply ? 'no' : 'yes'));
        $this->line('Target date: ' . $targetDate->toDateString());
        $this->line('Candidate due follow-ups scanned: ' . $result['scanned']);
        $this->line('Stale due follow-ups found: ' . $result['stale']);
        $this->line('Due dates cleared: ' . $result['cleared']);

        if (!empty($samples)) {
            $this->table(
                [
                    'lead_id',
                    'stale_followup_id',
                    'stale_due_at',
                    'latest_followup_id',
                    'latest_status',
                ],
                $samples
            );
        }

        if (!$apply) {
            $this->warn(
                'No database changes were made. Re-run with --apply after reviewing the counts.'
            );
        }

        return Command::SUCCESS;
    }

    private function candidateQuery(Carbon $targetDate)
    {
        $date = $targetDate->toDateString();

        return LeadFollowup::query()
            ->whereNotNull('next_followup_date')
            ->where(function ($query) use ($date) {
                $query
                    ->where(function ($todayQuery) use ($date) {
                        $todayQuery
                            ->whereDate('next_followup_date', '=', $date)
                            ->whereNotIn(
                                'status',
                                LeadFollowup::TODAY_FOLLOWUP_HIDDEN_STATUSES
                            );
                    })
                    ->orWhere(function ($missedQuery) use ($date) {
                        $missedQuery
                            ->whereDate('next_followup_date', '<', $date)
                            ->whereIn(
                                'status',
                                LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES
                            );
                    });
            });
    }

    private function latestFollowupsByLead(array $leadIds)
    {
        if (empty($leadIds)) {
            return collect();
        }

        return LeadFollowup::query()
            ->whereIn('lead_id', $leadIds)
            ->orderBy('lead_id')
            ->orderByDesc('created_at')
            ->orderByDesc('next_followup_date')
            ->get()
            ->groupBy('lead_id')
            ->map(fn ($followups) => $followups->first());
    }
}
