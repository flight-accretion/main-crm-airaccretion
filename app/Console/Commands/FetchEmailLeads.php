<?php

namespace App\Console\Commands;

use App\Services\EmailLeadService;
use App\Services\EmailMailboxService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchEmailLeads extends Command
{
    protected $signature =
        'email:fetch-leads
        {--from= : From date DD/MM/YYYY}
        {--to= : To date DD/MM/YYYY}';

    protected $description =
        'Fetch website lead emails from leads mailbox';

    public function handle(
        EmailMailboxService $mailboxService,
        EmailLeadService $leadService
    ) {
        try {
            /*
             * Default:
             * yesterday → today
             */
            $from = $this->option('from')
                ? Carbon::createFromFormat(
                    'd/m/Y',
                    $this->option('from')
                )->startOfDay()
                : now()
                    ->subDay()
                    ->startOfDay();

            $to = $this->option('to')
                ? Carbon::createFromFormat(
                    'd/m/Y',
                    $this->option('to')
                )->endOfDay()
                : now()
                    ->endOfDay();

            if ($from->gt($to)) {
                throw new \RuntimeException(
                    'From date cannot be after To date.'
                );
            }

            $emails =
                $mailboxService->fetch(
                    $from,
                    $to
                );

            $result = [
                'fetched' => count($emails),
                'created' => 0,
                'repeat_leads' => 0,
                'duplicate_emails' => 0,
                'errors' => 0,
            ];

            foreach ($emails as $email) {
                try {
                    $response =
                        $leadService->process(
                            $email
                        );

                    switch (
                        $response['status']
                        ?? null
                    ) {
                        case 'created_queued':
                            $result['created']++;
                            break;

                        case 'repeat_lead':
                            $result[
                                'repeat_leads'
                            ]++;
                            break;

                        case 'duplicate_email':
                            $result[
                                'duplicate_emails'
                            ]++;
                            break;

                        default:
                            $result['errors']++;
                            break;
                    }
                } catch (\Throwable $e) {
                    $result['errors']++;

                    Log::error(
                        'Email lead processing failed',
                        [
                            'message_id' =>
                                $email[
                                    'message_id'
                                ] ?? null,

                            'error' =>
                                $e->getMessage(),
                        ]
                    );
                }
            }

            $this->line(
                json_encode(
                    $result,
                    JSON_UNESCAPED_SLASHES
                )
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error(
                'Email lead fetch failed',
                [
                    'error' =>
                        $e->getMessage(),
                ]
            );

            $this->error(
                $e->getMessage()
            );

            return Command::FAILURE;
        }
    }
}
