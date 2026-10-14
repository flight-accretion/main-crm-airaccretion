<?php

namespace Tests\Unit;

use App\Services\EmailMailboxService;
use App\Support\EmailTextNormalizer;
use PHPUnit\Framework\TestCase;

class EmailTextEncodingTest extends TestCase
{
    public function test_valid_utf8_and_ascii_are_preserved(): void
    {
        foreach (['Name: Customer', "\u{0928}\u{092E}\u{0938}\u{094D}\u{0924}\u{0947}", "\u{65E5}\u{672C}", "\u{1F680}"] as $value) {
            $this->assertSame($value, EmailTextNormalizer::toUtf8($value));
        }
    }

    public function test_legacy_bytes_and_nested_json_are_safe(): void
    {
        $this->assertSame("30\u{00B0} \u{2013} Goa", EmailTextNormalizer::toUtf8("30\xB0 \x96 Goa", 'Windows-1252'));
        $this->assertSame("caf\u{00E9}", EmailTextNormalizer::toUtf8("caf\xE9", 'ISO-8859-1'));
        $value = EmailTextNormalizer::normalize(["key\x96" => ['value' => "\xB0\0", 'number' => 3, 'empty' => null]]);
        $this->assertSame(3, $value["key\u{2013}"]['number']);
        $this->assertSame("\u{00B0}", $value["key\u{2013}"]['value']);
        json_encode($value, JSON_THROW_ON_ERROR);
    }

    public function test_missing_or_unknown_charset_has_safe_fallback(): void
    {
        $this->assertSame("\u{2013}", EmailTextNormalizer::toUtf8("\x96", 'unknown-mail-charset'));
        $this->assertSame("\u{00B0}", EmailTextNormalizer::toUtf8("\xB0", 'UTF-8'));
    }

    public function test_nested_plain_text_wins_over_html_and_attachments_are_not_read(): void
    {
        $structure = (object) ['type' => 1, 'parts' => [
            (object) ['type' => 1, 'parts' => [
                (object) ['type' => 0, 'subtype' => 'HTML', 'encoding' => 0],
                (object) ['type' => 0, 'subtype' => 'PLAIN', 'encoding' => 4, 'parameters' => [(object) ['attribute' => 'charset', 'value' => 'Windows-1252']]],
            ]],
            (object) ['type' => 0, 'subtype' => 'PLAIN', 'disposition' => 'ATTACHMENT'],
            (object) ['type' => 5, 'subtype' => 'JPEG'],
        ]];
        $read = [];
        $body = $this->body($structure, function ($number) use (&$read) {
            $read[] = $number;
            return ['1.1' => '<p>wrong HTML</p>', '1.2' => 'Name: Customer=0AOccasion: Birthday =96 party'][$number];
        });
        $this->assertSame("Name: Customer\nOccasion: Birthday \u{2013} party", $body);
        $this->assertSame(['1.2'], $read);
    }

    public function test_base64_html_charset_is_decoded_before_cleanup(): void
    {
        $structure = (object) ['type' => 0, 'subtype' => 'HTML', 'encoding' => 3, 'parameters' => [(object) ['attribute' => 'charset', 'value' => 'Windows-1252']]];
        $this->assertSame("Name: Andr\u{00E9}\nGuests: 2 & 3", $this->body($structure, fn () => base64_encode("<p>Name: Andr\xE9<br>Guests: 2 &amp; 3</p>")));
    }

    public function test_attachment_only_message_is_not_parsed_as_raw_mime(): void
    {
        $structure = (object) ['type' => 1, 'parts' => [
            (object) ['type' => 0, 'subtype' => 'PLAIN', 'parameters' => [(object) ['attribute' => 'name', 'value' => 'notes.txt']]],
            (object) ['type' => 5, 'subtype' => 'JPEG'],
        ]];
        $this->assertSame('', $this->body($structure, function () { throw new \RuntimeException('Attachment must not be read'); }));
    }

    public function test_mime_subject_is_converted_to_utf8(): void
    {
        if (!function_exists('imap_mime_header_decode')) {
            $this->markTestSkipped('IMAP extension is required');
        }
        $method = new \ReflectionMethod(EmailMailboxService::class, 'decodeMime');
        $this->assertSame("Trip \u{2013} Goa", $method->invoke(new EmailMailboxService(), '=?Windows-1252?Q?Trip_=96_Goa?='));
    }

    private function body(object $structure, callable $read): string
    {
        $method = new \ReflectionMethod(EmailMailboxService::class, 'bodyFromStructure');
        return $method->invoke(new EmailMailboxService(), $structure, $read);
    }
}
