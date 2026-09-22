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
                ? Carbon::parse(
                    (string) $this->option('date')
                )->startOfDay()
                : Carbon::now()->startOfDay();

        } catch (\Throwable $exception) {
            $this->error(
                'Invalid --date value. Use YYYY-MM-DD.'
            );

            return Command::FAILURE;
        }


        $apply =
            (bool) $this->option('apply');

        $limit =
            max(
                0,
                (int) $this->option('limit')
            );

        $chunkSize =
            max(
                1,
                (int) $this->option('chunk')
            );


        $result = [
            'scanned' => 0,
            'stale' => 0,
            'cleared' => 0,
        ];

        $samples = [];


        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Candidate query does NOT filter by status.
        |
        | We first find every today/missed follow-up having a due date.
        | Then we inspect the absolute latest follow-up for that lead.
        |
        | If latest status is Cancelled / Confirmed / Rejected,
        | the stale due date is removed.
        |
        */
        $this->candidateQuery(
            $targetDate
        )
            ->chunkById(
                $chunkSize,

                function ($followups) use (
                    $apply,
                    $limit,
                    &$result,
                    &$samples
                ) {
                    $leadIds =
                        $followups
                            ->pluck('lead_id')
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();


                    $latestByLead =
                        $this->latestFollowupsByLead(
                            $leadIds
                        );


                    foreach (
                        $followups
                        as $followup
                    ) {
                        $result['scanned']++;


                        $latestFollowup =
                            $latestByLead->get(
                                $followup->lead_id
                            );


                        /*
                         * Lead is still active/open.
                         * Do not touch its due date.
                         */
                        if (
                            !$latestFollowup
                            ||
                            !LeadFollowup::hiddenFromTodayFollowups(
                                $latestFollowup->status
                            )
                        ) {
                            continue;
                        }


                        $result['stale']++;


                        if (
                            count($samples) < 10
                        ) {
                            $samples[] = [
                                'lead_id' =>
                                    $followup->lead_id,

                                'stale_followup_id' =>
                                    $followup->id,

                                'stale_due_at' =>
                                    optional(
                                        $followup
                                            ->next_followup_date
                                    )->format(
                                        'Y-m-d H:i:s'
                                    ),

                                'latest_followup_id' =>
                                    $latestFollowup->id,

                                'latest_status' =>
                                    $latestFollowup->status,
                            ];
                        }


                        if ($apply) {
                            DB::transaction(
                                function () use (
                                    $followup,
                                    &$result
                                ) {
                                    /*
                                     * Direct update is intentional.
                                     *
                                     * This is historical cleanup and
                                     * does not need model side-effects.
                                     */
                                    $updated =
                                        LeadFollowup::query()
                                            ->whereKey(
                                                $followup->id
                                            )
                                            ->whereNotNull(
                                                'next_followup_date'
                                            )
                                            ->update([
                                                'next_followup_date' =>
                                                    null,

                                                'updated_at' =>
                                                    now(),
                                            ]);


                                    if ($updated > 0) {
                                        $result[
                                            'cleared'
                                        ]++;
                                    }
                                }
                            );
                        }


                        if (
                            $limit > 0
                            &&
                            $result['stale']
                                >= $limit
                        ) {
                            return false;
                        }
                    }
                },

                'id'
            );


        $this->newLine();

        $this->line(
            'Dry run: '
            . ($apply ? 'no' : 'yes')
        );

        $this->line(
            'Target date: '
            . $targetDate->toDateString()
        );

        $this->line(
            'Candidate due follow-ups scanned: '
            . $result['scanned']
        );

        $this->line(
            'Stale due follow-ups found: '
            . $result['stale']
        );

        $this->line(
            'Due dates cleared: '
            . $result['cleared']
        );


        if (
            !empty($samples)
        ) {
            $this->newLine();

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
            $this->newLine();

            $this->warn(
                'No database changes were made. Re-run with --apply after reviewing the counts.'
            );
        }


        return Command::SUCCESS;
    }


    /**
     * Get every follow-up that can currently appear
     * as Today / Missed based on its due date.
     *
     * Do NOT filter status here.
     *
     * The absolute latest status for the lead
     * is checked later.
     */
    private function candidateQuery(
        Carbon $targetDate
    ) {
        $date =
            $targetDate
                ->toDateString();


        return LeadFollowup::query()
            ->whereNotNull(
                'next_followup_date'
            )
            ->whereDate(
                'next_followup_date',
                '<=',
                $date
            );
    }


    /**
     * Find the absolute latest follow-up/status
     * for every candidate lead.
     */
    private function latestFollowupsByLead(
        array $leadIds
    ) {
        if (
            empty($leadIds)
        ) {
            return collect();
        }


        return LeadFollowup::query()
            ->whereIn(
                'lead_id',
                $leadIds
            )
            ->orderBy(
                'lead_id'
            )
            ->orderByDesc(
                'created_at'
            )
            ->orderByDesc(
                'updated_at'
            )
            ->get()
            ->groupBy(
                'lead_id'
            )
            ->map(
                fn ($followups) =>
                    $followups->first()
            );
    }
}