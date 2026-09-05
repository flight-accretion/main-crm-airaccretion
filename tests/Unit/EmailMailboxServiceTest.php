<?php

namespace Tests\Unit\Support {

class EmailMailboxImapFake
{
    public static array $messages = [];

    public static array $searchCriteria = [];

    public static function reset(): void
    {
        self::$messages = [];
        self::$searchCriteria = [];
    }

    public static function open($mailbox, $username, $password)
    {
        return 'fake-imap-connection';
    }

    public static function search($connection, string $criteria, int $flags = 0)
    {
        self::$searchCriteria[] = $criteria;

        return array_keys(self::$messages);
    }

    public static function msgno($connection, int $uid): int
    {
        return $uid;
    }

    public static function headerinfo($connection, int $msgNo)
    {
        $message = self::$messages[(string) $msgNo];

        return (object) [
            'from' => [
                (object) [
                    'mailbox' => $message['from_mailbox'],
                    'host' => $message['from_host'],
                ],
            ],
            'to' => [
                (object) [
                    'mailbox' => $message['to_mailbox'],
                    'host' => $message['to_host'],
                ],
            ],
            'subject' => $message['subject'],
            'date' => $message['date'],
        ];
    }

    public static function fetchOverview($connection, int $msgNo, int $sequence = 0): array
    {
        $message = self::$messages[(string) $msgNo];

        return [
            (object) [
                'subject' => $message['subject'],
                'message_id' => $message['message_id'],
            ],
        ];
    }

    public static function fetchStructure($connection, int $msgNo)
    {
        return (object) [
            'encoding' => 0,
            'subtype' => 'PLAIN',
        ];
    }

    public static function body($connection, int $msgNo): string
    {
        return self::$messages[(string) $msgNo]['body'];
    }

    public static function close($connection): void
    {
    }

    public static function lastError(): string
    {
        return 'fake IMAP error';
    }

    public static function mimeHeaderDecode(string $value): array
    {
        return [
            (object) [
                'text' => $value,
            ],
        ];
    }
}

}

namespace {
    if (! defined('SE_UID')) {
        define('SE_UID', 1);
    }

    if (! function_exists('imap_open')) {
        function imap_open($mailbox, $username, $password)
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::open(
                $mailbox,
                $username,
                $password
            );
        }
    }

    if (! function_exists('imap_search')) {
        function imap_search($connection, string $criteria, int $flags = 0)
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::search(
                $connection,
                $criteria,
                $flags
            );
        }
    }

    if (! function_exists('imap_msgno')) {
        function imap_msgno($connection, int $uid): int
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::msgno(
                $connection,
                $uid
            );
        }
    }

    if (! function_exists('imap_headerinfo')) {
        function imap_headerinfo($connection, int $msgNo)
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::headerinfo(
                $connection,
                $msgNo
            );
        }
    }

    if (! function_exists('imap_fetch_overview')) {
        function imap_fetch_overview($connection, int $msgNo, int $sequence = 0): array
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::fetchOverview(
                $connection,
                $msgNo,
                $sequence
            );
        }
    }

    if (! function_exists('imap_fetchstructure')) {
        function imap_fetchstructure($connection, int $msgNo)
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::fetchStructure(
                $connection,
                $msgNo
            );
        }
    }

    if (! function_exists('imap_body')) {
        function imap_body($connection, int $msgNo): string
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::body(
                $connection,
                $msgNo
            );
        }
    }

    if (! function_exists('imap_close')) {
        function imap_close($connection): void
        {
            \Tests\Unit\Support\EmailMailboxImapFake::close(
                $connection
            );
        }
    }

    if (! function_exists('imap_last_error')) {
        function imap_last_error(): string
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::lastError();
        }
    }

    if (! function_exists('imap_mime_header_decode')) {
        function imap_mime_header_decode(string $value): array
        {
            return \Tests\Unit\Support\EmailMailboxImapFake::mimeHeaderDecode(
                $value
            );
        }
    }
}

namespace App\Services {
    function imap_open($mailbox, $username, $password)
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::open(
            $mailbox,
            $username,
            $password
        );
    }

    function imap_search($connection, string $criteria, int $flags = 0)
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::search(
            $connection,
            $criteria,
            $flags
        );
    }

    function imap_msgno($connection, int $uid): int
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::msgno(
            $connection,
            $uid
        );
    }

    function imap_headerinfo($connection, int $msgNo)
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::headerinfo(
            $connection,
            $msgNo
        );
    }

    function imap_fetch_overview($connection, int $msgNo, int $sequence = 0): array
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::fetchOverview(
            $connection,
            $msgNo,
            $sequence
        );
    }

    function imap_fetchstructure($connection, int $msgNo)
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::fetchStructure(
            $connection,
            $msgNo
        );
    }

    function imap_body($connection, int $msgNo): string
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::body(
            $connection,
            $msgNo
        );
    }

    function imap_close($connection): void
    {
        \Tests\Unit\Support\EmailMailboxImapFake::close(
            $connection
        );
    }

    function imap_last_error(): string
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::lastError();
    }

    function imap_mime_header_decode(string $value): array
    {
        return \Tests\Unit\Support\EmailMailboxImapFake::mimeHeaderDecode(
            $value
        );
    }
}

namespace Tests\Unit {

use App\Services\EmailMailboxService;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Unit\Support\EmailMailboxImapFake;

class EmailMailboxServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        EmailMailboxImapFake::reset();

        config()->set('app.timezone', 'Asia/Kolkata');
        config()->set('services.email_leads.host', 'mail.accretionaviation.com');
        config()->set('services.email_leads.port', 993);
        config()->set('services.email_leads.encryption', 'ssl');
        config()->set('services.email_leads.validate_cert', true);
        config()->set('services.email_leads.username', 'leads@accretionaviation.com');
        config()->set('services.email_leads.password', 'secret');
        config()->set('services.email_leads.mailbox', 'INBOX');
    }

    public function test_fetch_accepts_any_sender_when_allowed_sender_is_blank(): void
    {
        config()->set('services.email_leads.allowed_sender', '');

        EmailMailboxImapFake::$messages = [
            '11' => [
                'from_mailbox' => 'customer',
                'from_host' => 'example.test',
                'to_mailbox' => 'leads',
                'to_host' => 'accretionaviation.com',
                'subject' => 'Website enquiry',
                'message_id' => '<customer-11@example.test>',
                'date' => 'Fri, 04 Sep 2026 11:00:00 +0530',
                'body' => implode(PHP_EOL, [
                    'Name: Email Customer',
                    'Phone No: 9876543210',
                    'Services: Helicopter Ride',
                ]),
            ],
        ];

        $records = app(EmailMailboxService::class)->fetch(
            Carbon::create(2026, 9, 3, 0, 0, 0),
            Carbon::create(2026, 9, 5, 23, 59, 59)
        );

        $this->assertSame(
            ['SINCE "03-Sep-2026"'],
            EmailMailboxImapFake::$searchCriteria
        );
        $this->assertCount(1, $records);
        $this->assertSame(
            'customer@example.test',
            $records[0]['sender_email']
        );
        $this->assertSame(
            'leads@accretionaviation.com',
            $records[0]['recipient_email']
        );
    }

    public function test_fetch_keeps_sender_filter_when_allowed_sender_is_configured(): void
    {
        config()->set(
            'services.email_leads.allowed_sender',
            'noreply@accretionaviation.com'
        );

        EmailMailboxImapFake::$messages = [
            '11' => [
                'from_mailbox' => 'noreply',
                'from_host' => 'accretionaviation.com',
                'to_mailbox' => 'leads',
                'to_host' => 'accretionaviation.com',
                'subject' => 'Website enquiry',
                'message_id' => '<noreply-11@accretionaviation.com>',
                'date' => 'Fri, 04 Sep 2026 11:00:00 +0530',
                'body' => implode(PHP_EOL, [
                    'Name: Website Customer',
                    'Phone No: 9876543210',
                    'Services: Helicopter Ride',
                ]),
            ],
            '12' => [
                'from_mailbox' => 'customer',
                'from_host' => 'example.test',
                'to_mailbox' => 'leads',
                'to_host' => 'accretionaviation.com',
                'subject' => 'Direct enquiry',
                'message_id' => '<customer-12@example.test>',
                'date' => 'Fri, 04 Sep 2026 11:05:00 +0530',
                'body' => implode(PHP_EOL, [
                    'Name: Direct Customer',
                    'Phone No: 9876543211',
                    'Services: Helicopter Ride',
                ]),
            ],
        ];

        $records = app(EmailMailboxService::class)->fetch(
            Carbon::create(2026, 9, 3, 0, 0, 0),
            Carbon::create(2026, 9, 5, 23, 59, 59)
        );

        $this->assertSame(
            ['FROM "noreply@accretionaviation.com" SINCE "03-Sep-2026"'],
            EmailMailboxImapFake::$searchCriteria
        );
        $this->assertCount(1, $records);
        $this->assertSame(
            'noreply@accretionaviation.com',
            $records[0]['sender_email']
        );
    }
}

}
