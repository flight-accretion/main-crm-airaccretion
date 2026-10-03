<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadTransfer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CleanupProcessedLeadTransfers extends Command
{
    protected $signature =
        'leads:cleanup-processed-transfers
        {--dry-run : Show what would be processed without changing data}
        {--limit=0 : Maximum number to process, 0 means all}';

    protected $description =
        'Move processed lead-transfer history to Lead Follow-up History and delete processed transfer rows.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = LeadTransfer::query()
            ->whereIn('status', ['accepted', 'rejected', 'cancelled'])
            ->orderBy('created_at')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $transfers = $query->get();

        $this->info(
            'Processed transfer rows found: ' . $transfers->count()
        );

        $createdNotes = 0;
        $alreadyNoted = 0;
        $deleted = 0;
        $missingLead = 0;
        $failed = 0;

        foreach ($transfers as $transfer) {
            try {
                $lead = Lead::query()->find($transfer->lead_id);

                if (!$lead) {
                    $missingLead++;

                    $this->warn(
                        'Skipped transfer ' . $transfer->id
                        . ': lead ' . $transfer->lead_id . ' not found.'
                    );

                    continue;
                }

                $marker = '[LEAD TRANSFER:' . $transfer->id . ']';
                $noteExists = LeadFollowup::query()
                    ->where('lead_id', $lead->id)
                    ->where('followup_note', 'like', '%' . $marker . '%')
                    ->exists();

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRY RUN] %s | Lead %s | %s | %s',
                        $transfer->id,
                        $lead->id,
                        $transfer->status,
                        $noteExists
                            ? 'note already exists'
                            : 'note will be created'
                    ));

                    continue;
                }

                DB::transaction(function () use (
                    $transfer,
                    $lead,
                    $marker,
                    &$createdNotes,
                    &$alreadyNoted,
                    &$deleted
                ) {
                    $lockedTransfer = LeadTransfer::query()
                        ->where('id', $transfer->id)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$lockedTransfer
                        || !in_array(
                            $lockedTransfer->status,
                            ['accepted', 'rejected', 'cancelled'],
                            true
                        )
                    ) {
                        return;
                    }

                    $existingNote = LeadFollowup::query()
                        ->where('lead_id', $lead->id)
                        ->where('followup_note', 'like', '%' . $marker . '%')
                        ->exists();

                    if ($existingNote) {
                        $alreadyNoted++;
                    } else {
                        $this->createTransferNote($lead, $lockedTransfer);
                        $createdNotes++;
                    }

                    $lockedTransfer->delete();
                    $deleted++;
                });
            } catch (Throwable $e) {
                $failed++;

                report($e);

                $this->error(
                    'Failed transfer ' . $transfer->id . ': '
                    . $e->getMessage()
                );
            }
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Found', $transfers->count()],
                ['Notes Created', $createdNotes],
                ['Existing Notes', $alreadyNoted],
                ['Deleted', $deleted],
                ['Missing Lead', $missingLead],
                ['Failed', $failed],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN only. No data was changed.');
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function createTransferNote(
        Lead $lead,
        LeadTransfer $transfer
    ): void {
        $fromUser = User::query()->find($transfer->from_user_id);
        $toUser = User::query()->find($transfer->to_user_id);
        $requestedBy = User::query()->find($transfer->requested_by);
        $respondedBy = $transfer->responded_by
            ? User::query()->find($transfer->responded_by)
            : null;

        $outcome = match ($transfer->status) {
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            default => 'Cancelled',
        };

        $processedAt =
            $transfer->responded_at
            ?: $transfer->updated_at
            ?: now();

        $lines = [
            '[LEAD TRANSFER:' . $transfer->id . ']',
            'Lead Transfer ' . $outcome,
            'Transferred From: ' . (optional($fromUser)->name ?: 'Unknown'),
            'Requested For: ' . (optional($toUser)->name ?: 'Unknown'),
            'Requested By: ' . (optional($requestedBy)->name ?: 'Unknown'),
            'Processed By: ' . (optional($respondedBy)->name ?: 'System'),
            'Reason: ' . (
                trim((string) $transfer->reason) !== ''
                    ? trim((string) $transfer->reason)
                    : '-'
            ),
            'Processed Date: '
                . $processedAt
                    ->copy()
                    ->timezone('Asia/Kolkata')
                    ->format('d-m-Y H:i'),
        ];

        if (trim((string) $transfer->response_note) !== '') {
            $lines[] = 'Response Note: '
                . trim((string) $transfer->response_note);
        }

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $processedAt,
            'followup_note' => implode(PHP_EOL, $lines),
            'status' => 1,
            'followed_by' => $transfer->responded_by,
            'created_at' => $processedAt,
            'updated_at' => $processedAt,
        ]);
    }
}
