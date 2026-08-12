<?php

$host = 'mail.accretionaviation.com';
$port = 993;
$username = 'ops@accretionaviation.com';

echo 'Email Password: ';

if (PHP_OS_FAMILY === 'Windows') {
    $password = trim(fgets(STDIN));
} else {
    $password = trim(fgets(STDIN));
}

$mailbox = sprintf(
    '{%s:%d/imap/ssl}INBOX',
    $host,
    $port
);

echo PHP_EOL;
echo "Testing: {$mailbox}" . PHP_EOL;

$connection = @imap_open(
    $mailbox,
    $username,
    $password
);

if (!$connection) {
    echo 'FAILED: '
        . (imap_last_error() ?: 'Unknown IMAP error')
        . PHP_EOL;

    imap_errors();

    exit(1);
}

echo "SUCCESS - IMAP login working." . PHP_EOL;

$status = imap_status(
    $connection,
    $mailbox,
    SA_MESSAGES | SA_UNSEEN
);

if ($status) {
    echo "Total messages: {$status->messages}" . PHP_EOL;
    echo "Unread messages: {$status->unseen}" . PHP_EOL;
}

imap_close($connection);