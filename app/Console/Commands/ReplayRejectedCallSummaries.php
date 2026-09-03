<?php

namespace App\Console\Commands;

use App\Models\CallSummaryIntegration;
use App\Services\CallSummaryIntegrationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReplayRejectedCallSummaries extends Command
{
    protected $signature =
        'call-summary:replay-rejected
        {--date= : Last log date in YYYY-MM-DD format; defaults to today}
        {--days=7 : Number of daily Laravel logs to scan ending at --date/today}
        {--phone= : Only replay one customer phone}
        {--path= : Specific Laravel log file path}
        {--limit=25 : Maximum rejected payloads to replay}
        {--dry-run : Show what would be replayed without saving}
        {--allow-truncated : Replay log previews even when the original summary was longer}';

    protected $description =
        'Replay rejected call-summary webhooks from Laravel logs when the payload can be safely rebuilt.';

    public function handle(
        CallSummaryIntegrationService $service
    ): int {
        $paths = $this->logPaths();

        if (empty($paths)) {
            $this->warn('No Laravel log file found to scan.');

            return Command::SUCCESS;
        }

        $phoneFilter = $this->normalizePhone(
            $this->option('phone')
        );
        $limit = max(1, (int) $this->option('limit'));
        $allowTruncated = (bool) $this->option(
            'allow-truncated'
        );
        $dryRun = (bool) $this->option('dry-run');

        $stats = [
            'scanned' => 0,
            'candidates' => 0,
            'replayed' => 0,
            'already_exists' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                $stats['skipped']++;
                $this->warn("Log file is not readable: {$path}");

                continue;
            }

            $file = new \SplFileObject($path, 'r');

            while (!$file->eof()) {
                $line = (string) $file->fgets();

                if ($line === '') {
                    continue;
                }

                $stats['scanned']++;

                if (
                    !str_contains(
                        $line,
                        'Call Summary API validation failed.'
                    )
                ) {
                    continue;
                }

                $payload = $this->payloadFromLogLine(
                    $line,
                    $allowTruncated
                );

                if (!$payload) {
                    $stats['skipped']++;

                    continue;
                }

                if (
                    $phoneFilter
                    && $this->normalizePhone(
                        $payload['phone_number']
                    ) !== $phoneFilter
                ) {
                    continue;
                }

                if (
                    $this->alreadyReplayed($payload)
                ) {
                    $stats['already_exists']++;

                    continue;
                }

                $stats['candidates']++;

                if ($dryRun) {
                    $this->line(
                        'Would replay call summary for '
                        . $payload['phone_number']
                        . ' recording '
                        . ($payload['followup_recording_id'] ?? 'N/A')
                    );

                    if ($stats['candidates'] >= $limit) {
                        break 2;
                    }

                    continue;
                }

                try {
                    $integration = $service->receive($payload);

                    $stats['replayed']++;

                    $this->line(
                        'Replayed call summary for '
                        . $payload['phone_number']
                        . ' recording '
                        . ($payload['followup_recording_id'] ?? 'N/A')
                        . ' => '
                        . $integration->status
                    );
                } catch (\Throwable $exception) {
                    $stats['failed']++;

                    $this->error(
                        'Replay failed for '
                        . $payload['phone_number']
                        . ': '
                        . $exception->getMessage()
                    );
                }

                if ($stats['candidates'] >= $limit) {
                    break 2;
                }
            }
        }

        $this->info(
            'Scanned: '
            . $stats['scanned']
            . '; candidates: '
            . $stats['candidates']
            . '; replayed: '
            . $stats['replayed']
            . '; already exists: '
            . $stats['already_exists']
            . '; skipped: '
            . $stats['skipped']
            . '; failed: '
            . $stats['failed']
        );

        return $stats['failed'] > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function logPaths(): array
    {
        $path = trim((string) $this->option('path'));

        if ($path !== '') {
            return [
                $this->absolutePath($path),
            ];
        }

        $date = trim((string) $this->option('date'));

        if ($date === '') {
            $date = now()->toDateString();
        }

        $endDate = Carbon::parse($date);
        $days = max(1, (int) $this->option('days'));
        $paths = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $paths[] = storage_path(
                'logs/laravel-'
                . $endDate
                    ->copy()
                    ->subDays($offset)
                    ->toDateString()
                . '.log'
            );
        }

        $paths[] = storage_path('logs/laravel.log');

        return array_values(
            array_filter(
                array_unique($paths),
                fn ($path) => is_file($path)
            )
        );
    }

    private function absolutePath(string $path): string
    {
        if (
            str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        ) {
            return $path;
        }

        return base_path($path);
    }

    private function payloadFromLogLine(
        string $line,
        bool $allowTruncated
    ): ?array {
        $marker = 'Call Summary API validation failed.';
        $markerPosition = strpos($line, $marker);

        if ($markerPosition === false) {
            return null;
        }

        $jsonPosition = strpos($line, '{', $markerPosition);

        if ($jsonPosition === false) {
            return null;
        }

        $context = json_decode(
            substr($line, $jsonPosition),
            true
        );

        if (!is_array($context)) {
            return null;
        }

        if (!$this->canReplayErrors($context['errors'] ?? [])) {
            return null;
        }

        $debug = $context['payload_debug'] ?? null;

        if (!is_array($debug)) {
            return null;
        }

        foreach (
            [
                'phone_number',
                'agent_name',
                'call_start_at',
                'call_end_at',
            ]
            as $field
        ) {
            if (trim((string) ($debug[$field] ?? '')) === '') {
                return null;
            }
        }

        if (!($debug['summary_present'] ?? false)) {
            return null;
        }

        $summary = trim(
            (string) ($debug['summary_preview'] ?? '')
        );

        if ($summary === '') {
            return null;
        }

        $summaryLength = $debug['summary_length'] ?? null;

        if (
            !$allowTruncated
            && is_numeric($summaryLength)
            && (int) $summaryLength > mb_strlen($summary)
        ) {
            return null;
        }

        $direction = strtolower(
            trim((string) ($debug['direction'] ?? 'unknown'))
        );

        if (
            !in_array(
                $direction,
                [
                    'incoming',
                    'outgoing',
                    'unknown',
                ],
                true
            )
        ) {
            $direction = 'unknown';
        }

        $payload = [
            'phone_number' => (string) $debug['phone_number'],
            'summary' => $summary,
            'followup_date' => $debug['followup_date'] ?? null,
            'call_start_at' => (string) $debug['call_start_at'],
            'call_end_at' => (string) $debug['call_end_at'],
            'agent_name' => (string) $debug['agent_name'],
            'direction' => $direction,
            'sentiment_score' => $debug['sentiment_score'] ?? null,
        ];

        if (!empty($debug['lead_id'])) {
            $payload['lead_id'] = (string) $debug['lead_id'];
        }

        if (
            array_key_exists('followup_recording_id', $debug)
            && $debug['followup_recording_id'] !== null
            && $debug['followup_recording_id'] !== ''
        ) {
            $payload['followup_recording_id'] =
                $debug['followup_recording_id'];
        }

        return $payload;
    }

    private function canReplayErrors(array $errors): bool
    {
        if (array_keys($errors) !== ['direction']) {
            return false;
        }

        return true;
    }

    private function alreadyReplayed(array $payload): bool
    {
        if (!empty($payload['followup_recording_id'])) {
            return CallSummaryIntegration::query()
                ->where(
                    'followup_recording_id',
                    $payload['followup_recording_id']
                )
                ->exists();
        }

        return CallSummaryIntegration::query()
            ->where(
                'call_fingerprint',
                $this->fingerprintFor($payload)
            )
            ->exists();
    }

    private function fingerprintFor(array $payload): string
    {
        $start = Carbon::parse($payload['call_start_at']);
        $end = Carbon::parse($payload['call_end_at']);

        return hash(
            'sha256',
            implode(
                '|',
                [
                    $this->normalizePhone($payload['phone_number']),
                    $this->normalizeAgent($payload['agent_name']),
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s'),
                    strtolower(trim((string) $payload['direction'])),
                ]
            )
        );
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (!$digits) {
            return '';
        }

        return strlen($digits) > 10
            ? substr($digits, -10)
            : $digits;
    }

    private function normalizeAgent(?string $name): string
    {
        $name = trim(
            mb_strtolower((string) $name)
        );

        return preg_replace('/\s+/u', ' ', $name) ?: '';
    }
}
