<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

class EmailMailboxService
{
    private $connection;

    public function fetch(
        Carbon $from,
        Carbon $to
    ): array {
        $this->connect();

        try {
            $sender = config(
                'services.email_leads.allowed_sender'
            );

            /*
             * IMAP SINCE is inclusive.
             *
             * We fetch from yesterday/start date,
             * then strictly filter received_at below.
             */
            $criteria =
                'FROM "' . $sender . '" '
                . 'SINCE "'
                . $from->format('d-M-Y')
                . '"';

            $emails = imap_search(
                $this->connection,
                $criteria,
                SE_UID
            );

            if ($emails === false) {
                return [];
            }

            $records = [];

            foreach ($emails as $uid) {
                $record = $this->readMessage(
                    (string) $uid
                );

                if (!$record) {
                    continue;
                }

                $receivedAt = $record['received_at'];

                if (
                    $receivedAt->lt(
                        $from->copy()->startOfDay()
                    )
                    ||
                    $receivedAt->gt(
                        $to->copy()->endOfDay()
                    )
                ) {
                    continue;
                }

                /*
                 * Security/business check:
                 * actual From address must match.
                 */
                if (
                    strtolower($record['sender_email'])
                    !==
                    strtolower($sender)
                ) {
                    continue;
                }

                $records[] = $record;
            }

            return $records;
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        if (!function_exists('imap_open')) {
            throw new RuntimeException(
                'PHP IMAP extension is not installed.'
            );
        }

        $host = config(
            'services.email_leads.host'
        );

        $port = config(
            'services.email_leads.port',
            993
        );

        $encryption = config(
            'services.email_leads.encryption',
            'ssl'
        );

        $validateCert = filter_var(
            config(
                'services.email_leads.validate_cert',
                true
            ),
            FILTER_VALIDATE_BOOLEAN
        );

        $mailbox = config(
            'services.email_leads.mailbox',
            'INBOX'
        );

        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        if (!$validateCert) {
            $flags .= '/novalidate-cert';
        }

        $mailboxPath =
            '{'
            . $host
            . ':'
            . $port
            . $flags
            . '}'
            . $mailbox;

        $this->connection = @imap_open(
            $mailboxPath,
            config(
                'services.email_leads.username'
            ),
            config(
                'services.email_leads.password'
            )
        );

        if (!$this->connection) {
            throw new RuntimeException(
                'Unable to connect to lead mailbox: '
                . (imap_last_error() ?: 'Unknown IMAP error')
            );
        }
    }

    private function disconnect(): void
    {
        if ($this->connection) {
            imap_close($this->connection);

            $this->connection = null;
        }
    }

    private function readMessage(
        string $uid
    ): ?array {
        $msgNo = imap_msgno(
            $this->connection,
            (int) $uid
        );

        if (!$msgNo) {
            return null;
        }

        $header = imap_headerinfo(
            $this->connection,
            $msgNo
        );

        $overview = imap_fetch_overview(
            $this->connection,
            $msgNo,
            0
        );

        $overview = $overview[0] ?? null;

        if (!$header) {
            return null;
        }

        $sender = '';

        if (
            isset($header->from)
            && !empty($header->from)
        ) {
            $from = $header->from[0];

            $sender =
                strtolower(
                    ($from->mailbox ?? '')
                    . '@'
                    . ($from->host ?? '')
                );
        }

        $recipient = '';

        if (
            isset($header->to)
            && !empty($header->to)
        ) {
            $to = $header->to[0];

            $recipient =
                strtolower(
                    ($to->mailbox ?? '')
                    . '@'
                    . ($to->host ?? '')
                );
        }

        $subject = $this->decodeMime(
            $overview->subject
            ?? $header->subject
            ?? ''
        );

        $messageId = trim(
            $overview->message_id
            ?? $header->message_id
            ?? ''
        );

        /*
         * Message-ID should normally exist.
         * Use a deterministic fallback if it doesn't.
         */
        if ($messageId === '') {
            $messageId =
                'fallback:'
                . hash(
                    'sha256',
                    $uid
                    . '|'
                    . $sender
                    . '|'
                    . ($header->date ?? '')
                    . '|'
                    . $subject
                );
        }

        $receivedAt = Carbon::parse(
            $header->date
            ?? now()->toDateTimeString()
        )->timezone(
            config(
                'app.timezone',
                'Asia/Kolkata'
            )
        );

        $body = $this->getBody(
            $msgNo
        );

        return [
            'uid' => $uid,
            'message_id' => $messageId,
            'sender_email' => $sender,
            'recipient_email' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'received_at' => $receivedAt,
        ];
    }

    private function getBody(
        int $msgNo
    ): string {
        $structure = imap_fetchstructure(
            $this->connection,
            $msgNo
        );

        if (!$structure) {
            return trim(
                imap_body(
                    $this->connection,
                    $msgNo
                )
            );
        }

        /*
         * Single-part message.
         */
        if (empty($structure->parts)) {
            $body = imap_body(
                $this->connection,
                $msgNo
            );

            $body = $this->decodeBody(
                $body,
                $structure->encoding ?? 0
            );

            return $this->cleanBody(
                $body,
                $structure->subtype ?? 'PLAIN'
            );
        }

        $plain = null;
        $html = null;

        foreach (
            $structure->parts
            as $index => $part
        ) {
            $partNumber = (string) ($index + 1);

            /*
             * text/plain
             */
            if (
                ($part->type ?? null) === 0
                &&
                strtoupper(
                    $part->subtype ?? ''
                ) === 'PLAIN'
            ) {
                $content = imap_fetchbody(
                    $this->connection,
                    $msgNo,
                    $partNumber
                );

                $plain = $this->decodeBody(
                    $content,
                    $part->encoding ?? 0
                );

                break;
            }

            /*
             * text/html fallback
             */
            if (
                ($part->type ?? null) === 0
                &&
                strtoupper(
                    $part->subtype ?? ''
                ) === 'HTML'
            ) {
                $content = imap_fetchbody(
                    $this->connection,
                    $msgNo,
                    $partNumber
                );

                $html = $this->decodeBody(
                    $content,
                    $part->encoding ?? 0
                );
            }
        }

        if ($plain !== null) {
            return $this->cleanBody(
                $plain,
                'PLAIN'
            );
        }

        if ($html !== null) {
            return $this->cleanBody(
                $html,
                'HTML'
            );
        }

        return trim(
            imap_body(
                $this->connection,
                $msgNo
            )
        );
    }

    private function decodeBody(
        string $body,
        int $encoding
    ): string {
        switch ($encoding) {
            case 3:
                return base64_decode(
                    $body,
                    true
                ) ?: '';

            case 4:
                return quoted_printable_decode(
                    $body
                );

            default:
                return $body;
        }
    }

    private function cleanBody(
        string $body,
        string $subtype
    ): string {
        if (
            strtoupper($subtype)
            === 'HTML'
        ) {
            $body = str_ireplace(
                ['<br>', '<br/>', '<br />'],
                "\n",
                $body
            );

            $body = strip_tags(
                $body
            );

            $body = html_entity_decode(
                $body,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        $body = str_replace(
            ["\r\n", "\r"],
            "\n",
            $body
        );

        $body = preg_replace(
            "/\n{3,}/",
            "\n\n",
            $body
        );

        return trim($body);
    }

    private function decodeMime(
        string $value
    ): string {
        if ($value === '') {
            return '';
        }

        $parts = imap_mime_header_decode(
            $value
        );

        $decoded = '';

        foreach ($parts as $part) {
            $decoded .= $part->text;
        }

        return trim($decoded);
    }
}