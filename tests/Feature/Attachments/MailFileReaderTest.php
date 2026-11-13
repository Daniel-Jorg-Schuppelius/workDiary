<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFileReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Attachments;

use App\Enums\Attachments\{MailFileFailure, MailFileFormat};
use App\Exceptions\MailFileUnreadableException;
use App\Services\Attachments\FileAttacher;
use App\Services\Attachments\Mail\Dto\MailFileMessage;
use App\Services\Attachments\Mail\MailFileReader;
use Tests\Support\MailFiles;
use Tests\TestCase;

/**
 * Mail-Leser (Feature 204, MVP-1652): ein Ergebnis für .eml und .msg, Grenzen
 * und Schutz vor teuren Eingaben — alles mit erfundenen Mails. Die Fälle der
 * Sicherheitsprüfung (H1, M1, N1) sind als Regression enthalten.
 */
final class MailFileReaderTest extends TestCase {
    private MailFileReader $reader;

    protected function setUp(): void {
        parent::setUp();
        $this->reader = new MailFileReader;
    }

    public function test_eml_with_html_body_reads_header_text_and_attachments(): void {
        $mail = $this->reader->read(MailFiles::eml(), MailFileFormat::Eml);

        $this->assertSame(MailFileFormat::Eml, $mail->format);
        $this->assertSame('Angebot für die Übergabe', $mail->subject);
        $this->assertSame(['name' => 'Erika Mustermann (Büro)', 'address' => 'erika.mustermann@example.org'], $mail->from);
        $this->assertSame('Max Beispiel <max.beispiel@example.com>', MailFileMessage::addressList($mail->to));
        $this->assertSame('buero@example.com', MailFileMessage::addressList($mail->cc));
        $this->assertSame('2026-11-04T09:15:00+00:00', $mail->sentAt()?->toAtomString());
        $this->assertSame('angebot-0815@example.org', $mail->messageId);

        // Nur HTML: Text über htmlToText, ohne Skript- und Titelinhalt.
        $this->assertTrue($mail->textFromHtml);
        $this->assertTrue($mail->hasHtml);
        $this->assertStringContainsString('anbei das Angebot für die Übergabe.', $mail->text);
        $this->assertStringNotContainsString('pwned', $mail->text);
        $this->assertStringNotContainsString('Kopf', $mail->text);

        $names = array_map(static fn ($part) => $part->name, $mail->attachments);
        $this->assertSame(['logo.png', 'Angebot Übergabe.pdf', 'weitergeleitet.eml'], $names);

        $logo = $mail->attachments[0];
        $this->assertSame('image/png', $logo->mime);
        $this->assertSame('logo@beispiel.test', $logo->contentId);
        $this->assertTrue($logo->inline);

        $pdf = $mail->attachments[1];
        $this->assertSame('application/pdf', $pdf->mime, 'Typ am Inhalt erkannt');
        $this->assertTrue($pdf->isPreviewable());
        $this->assertTrue($pdf->safe);
        $this->assertSame(MailFiles::PDF, $pdf->content);

        $nested = $mail->attachments[2]->message;
        $this->assertNotNull($nested);
        $this->assertSame('Ursprüngliche Anfrage', $nested->subject);
        $this->assertStringContainsString('Bitte um Rückruf', $nested->text);
        $this->assertSame($nested, $mail->messageAt('2'));
        $this->assertNull($mail->part('2.0'));
    }

    public function test_msg_reads_umlaut_subject_in_unicode_and_codepage(): void {
        foreach (['rueckfrage-unicode.msg', 'rueckfrage-ansi.msg'] as $fixture) {
            $mail = $this->reader->read(MailFiles::fixture($fixture), MailFileFormat::Msg);

            $this->assertSame('Rückfrage zur Übergabe – Größe & Maße', $mail->subject, $fixture);
            $this->assertSame('Erika Mustermann <erika.mustermann@example.org>', MailFileMessage::addressLine($mail->from), $fixture);
            $this->assertSame('Max Beispiel <max.beispiel@example.com>', MailFileMessage::addressList($mail->to), $fixture);
            $this->assertSame('Büro Beispiel <buero@example.com>', MailFileMessage::addressList($mail->cc), $fixture);
            $this->assertSame('2026-11-04T09:15:00+00:00', $mail->sentAt()?->toAtomString(), $fixture);
            $this->assertStringContainsString('Grüße aus Köln', $mail->text, $fixture);
            $this->assertFalse($mail->textFromHtml, $fixture);
            $this->assertTrue($mail->hasHtml, $fixture);

            $this->assertSame('Angebot Übergabe.pdf', $mail->attachments[0]->name, $fixture);
            $this->assertSame('application/pdf', $mail->attachments[0]->mime, $fixture);
            $this->assertTrue($mail->attachments[1]->inline, $fixture);
            $this->assertSame('logo@beispiel.test', $mail->attachments[1]->contentId, $fixture);
            $this->assertSame('Ursprüngliche Anfrage', $mail->attachments[2]->message?->subject, $fixture);
            $this->assertSame('notiz.txt', $mail->part('2.0')?->name, $fixture);
        }
    }

    public function test_limits_end_with_a_reason_instead_of_an_error(): void {
        $msg = MailFiles::fixture('rueckfrage-unicode.msg');

        config(['mail_files.max_attachments' => 1]);
        $this->assertFailure(MailFileFailure::TooManyAttachments, fn () => $this->reader->read($msg, MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::TooManyAttachments, fn () => $this->reader->read(MailFiles::plainEml('Viele', 2), MailFileFormat::Eml));

        config(['mail_files.max_attachments' => 100, 'mail_files.max_depth' => 0]);
        $this->assertFailure(MailFileFailure::TooDeep, fn () => $this->reader->read($msg, MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::TooDeep, fn () => $this->reader->read(MailFiles::eml(), MailFileFormat::Eml));

        config(['mail_files.max_depth' => 2, 'mail_files.max_attachment_kb' => 0]);
        $this->assertFailure(MailFileFailure::AttachmentsTooLarge, fn () => $this->reader->read(MailFiles::eml(), MailFileFormat::Eml));

        config(['mail_files.max_attachment_kb' => 25600, 'mail_files.max_kb' => 1]);
        $this->assertFailure(MailFileFailure::TooLarge, fn () => $this->reader->read($msg, MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::TooLarge, fn () => $this->reader->precheck($msg, MailFileFormat::Msg));
    }

    /** M1: Mail-Dateien haben eine eigene, kleinere Bytegrenze als allgemeine Anhänge. */
    public function test_mail_byte_limit_is_its_own_and_capped_by_the_attachment_limit(): void {
        config(['mail_files.max_kb' => 25600, 'uploads.attachment_kb' => 102400]);
        $this->assertSame(25600 * 1024, MailFileReader::limits()['bytes']);

        config(['uploads.attachment_kb' => 10240]);
        $this->assertSame(10240 * 1024, MailFileReader::limits()['bytes']);
    }

    public function test_damaged_or_foreign_files_are_malformed(): void {
        $msg = MailFiles::fixture('rueckfrage-unicode.msg');

        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read(substr($msg, 0, 1500), MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read(MailFiles::fixture('word-dokument.doc'), MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read(MailFiles::fixture('ole-ohne-nachricht.bin'), MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read('', MailFileFormat::Eml));
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read("Content-Type: multipart/mixed\r\n\r\nkein boundary", MailFileFormat::Eml));
    }

    public function test_precheck_is_cheap_and_only_checks_structure(): void {
        $this->reader->precheck(MailFiles::eml(), MailFileFormat::Eml);
        $this->reader->precheck(MailFiles::fixture('rueckfrage-unicode.msg'), MailFileFormat::Msg);
        // Abgeschnittene .msg: die Kennung stimmt, gelesen wird erst im Hintergrund.
        $this->reader->precheck(substr(MailFiles::fixture('rueckfrage-unicode.msg'), 0, 1500), MailFileFormat::Msg);

        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->precheck('kein Compound File', MailFileFormat::Msg));
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->precheck("<html><body>kein Kopf</body></html>\r\n\r\nx", MailFileFormat::Eml));
    }

    /**
     * webklex entfernt Kopfzeilen eines Teils Zeile für Zeile per substr():
     * ein Teil ohne Leerzeile kostete bei 4 MB rund 100 s.
     */
    public function test_mime_part_without_header_end_is_rejected_quickly(): void {
        $eml = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n--b\r\nX: y\r\n"
            . str_repeat("aaaaaaaaaa\r\n", 400_000) . "--b--\r\n";

        $this->assertQuickFailure(MailFileFailure::TooComplex, $eml);
    }

    /** H1a: gefaltete Content-Type-Zeile umging die Vorprüfung (Wert erst in der Folgezeile). */
    public function test_folded_content_type_does_not_skip_the_precheck(): void {
        $eml = "From: a@example.org\r\nSubject: x\r\nContent-Type:\r\n multipart/mixed; boundary=\"b\"\r\n\r\n--b\r\nX: y\r\n"
            . str_repeat("aaaaaaaaaa\r\n", 200_000) . "--b--\r\n";

        $this->assertQuickFailure(MailFileFailure::TooComplex, $eml);
    }

    /** H1b: Adresslisten zerlegt webklex quadratisch — Adressen über alle Teile begrenzt. */
    public function test_address_headers_are_counted_across_parts(): void {
        $to = 'To: ' . implode(', ', array_fill(0, 18_000, 'a@b.c'));
        $parts = '';
        for ($i = 0; $i < 10; $i++) {
            $parts .= "--b\r\n{$to}\r\nContent-Type: text/plain\r\n\r\nText {$i}\r\n";
        }
        $this->assertQuickFailure(MailFileFailure::TooComplex, "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n{$parts}--b--\r\n");

        // Unter der Größengrenze je Kopf, aber über der Adressgrenze.
        $few = 'To: ' . implode(', ', array_fill(0, 1_200, 'a@b.c'));
        $this->assertQuickFailure(MailFileFailure::TooComplex, "From: a@example.org\r\n{$few}\r\nSubject: x\r\n\r\nText\r\n");

        // Ein ganzer Verteiler mit 100 000 Empfängern im Kopf der Mail.
        $all = implode(', ', array_map(static fn (int $i): string => "u{$i}@example.org", range(1, 100_000)));
        $this->assertQuickFailure(MailFileFailure::TooComplex, "From: a@example.org\r\nTo: {$all}\r\nSubject: x\r\n\r\nText\r\n");
    }

    /** H1c: viele Kopfzeilen vor einem großen Körper — webklex kopiert den Rest je Zeile. */
    public function test_many_header_lines_before_a_big_body_are_rejected(): void {
        $headers = str_repeat("X-A: b\r\n", 998) . "Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename=\"a.bin\"\r\n";
        $body = str_repeat(str_repeat('a', 998) . "\r\n", 8 * 1024);
        $eml = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"b\"\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\nhi\r\n--b\r\n"
            . $headers . "\r\n" . $body . "\r\n--b--\r\n";

        $this->assertQuickFailure(MailFileFailure::TooComplex, $eml);
    }

    /** M1: leere Boundary zerlegte einen Strich-Teppich in Millionen Teile. */
    public function test_boundary_must_have_one_to_seventy_characters(): void {
        $started = microtime(true);
        $base = memory_get_usage();
        memory_reset_peak_usage();
        $flood = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"\"\r\n\r\n" . str_repeat('-', 24 * 1024 * 1024);
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->read($flood, MailFileFormat::Eml));
        $this->assertLessThan(3.0, microtime(true) - $started);
        $this->assertLessThan(80 * 1024 * 1024, memory_get_peak_usage() - $base, 'kein Zerlegen in Teile');

        $long = str_repeat('x', 71);
        $this->assertFailure(MailFileFailure::Malformed, fn () => $this->reader->precheck("From: a@example.org\r\nContent-Type: multipart/mixed; boundary=\"{$long}\"\r\n\r\n--{$long}\r\n\r\nx\r\n--{$long}--\r\n", MailFileFormat::Eml));

        // Ein Teppich aus gültigen Grenzen: gezählt, nicht zerlegt.
        $carpet = "From: a@example.org\r\nContent-Type: multipart/mixed; boundary=\"z\"\r\n\r\n" . str_repeat('--z', 2_000_000);
        $this->assertQuickFailure(MailFileFailure::TooComplex, $carpet);
    }

    public function test_deeply_nested_multipart_is_rejected(): void {
        // Grenzen ohne gemeinsames Präfix („--n1“ träfe sonst auch „--n10“).
        $body = "Text\r\n";
        for ($level = 20; $level >= 1; $level--) {
            $body = "--n{$level}x\r\nContent-Type: multipart/mixed; boundary=\"n" . ($level + 1) . "x\"\r\n\r\n" . $body . "--n{$level}x--\r\n";
        }
        $eml = "From: a@example.org\r\nSubject: x\r\nContent-Type: multipart/mixed; boundary=\"n1x\"\r\n\r\n" . $body;

        $this->assertQuickFailure(MailFileFailure::TooComplex, $eml);
    }

    /** StringHelper::htmlToText lief bei <style> ohne Gegenstück quadratisch (1 MB: 80 s). */
    public function test_html_to_text_is_linear_on_unclosed_blocks(): void {
        $started = microtime(true);
        $text = MailFileReader::htmlToText(str_repeat('<style>x', 125_000) . '<p>Ende</p>');

        $this->assertLessThan(3.0, microtime(true) - $started);
        $this->assertStringContainsString('Ende', $text);
        $this->assertSame('Hallo Welt', MailFileReader::htmlToText('<head><title>T</title></head><style>p{}</style><p>Hallo <b>Welt</b></p><script>x()</script>'));

        // Reste dürfen nach dem Entfernen keinen neuen Öffner bilden („<sty“ + „le>“).
        $started = microtime(true);
        MailFileReader::htmlToText(str_repeat('<sty<script></script>le>x', 60_000));
        $this->assertLessThan(3.0, microtime(true) - $started);
    }

    public function test_frame_html_inlines_cid_images_and_drops_navigation(): void {
        $mail = $this->reader->read(MailFiles::eml(), MailFileFormat::Eml);
        $html = (string) $this->reader->frameHtml($mail);

        $this->assertStringContainsString('src="data:image/png;base64,' . MailFiles::PNG_BASE64 . '"', $html);
        $this->assertStringNotContainsString('cid:logo@beispiel.test', $html);
        $this->assertStringNotContainsString('refresh', $html);
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('<script', $html);
        // Fremdbild und Formular bleiben im Markup — CSP und sandbox sperren sie (MailAttachmentViewTest).
        $this->assertStringContainsString('https://tracker.example.invalid/pixel.gif', $html);
    }

    /** N1: Bereinigung über den HTML5-Parser statt Regex — Umgehungen der Sicherheitsprüfung. */
    public function test_frame_html_removes_meta_refresh_regardless_of_spelling(): void {
        $cases = [
            'gt-in-attr' => '<meta content="0;url=https://evil.example/?a=>" http-equiv="refresh"><p>x</p>',
            'entity' => '<meta http-equiv="&#114;efresh" content="0;url=https://evil.example/"><p>x</p>',
            'comment' => '<!--><meta http-equiv=refresh content="0;url=https://evil.example/">--><p>x</p>',
            'noscript' => '<noscript><meta http-equiv=refresh content="0;url=https://evil.example/"></noscript><p>x</p>',
            'link-gt' => '<link data-x=">" rel="prefetch" href="https://evil.example/"><p>x</p>',
            'base-gt' => '<base data-x=">" href="https://evil.example/"><p>x</p>',
        ];
        foreach ($cases as $label => $html) {
            $out = (string) $this->reader->frameHtml(new MailFileMessage(MailFileFormat::Eml, 's', hasHtml: true, html: $html));

            $this->assertStringNotContainsString('evil.example', $out, $label);
            $this->assertStringContainsString('<p>x</p>', $out, $label);
        }

        // Zu verschachtelt bzw. zu groß für den Parser: keine Originaldarstellung statt Wartezeit.
        $this->assertNull($this->reader->frameHtml(new MailFileMessage(MailFileFormat::Eml, 's', hasHtml: true, html: str_repeat('<div>', 60_000))));
        $this->assertNull($this->reader->frameHtml(new MailFileMessage(MailFileFormat::Eml, 's', hasHtml: true, html: str_repeat('a', 3 * 1024 * 1024))));
    }

    /** M2: Bidi- und andere Formatzeichen verschwinden aus Namen. */
    public function test_display_names_drop_format_characters(): void {
        $this->assertSame('rechnungfdp.exe', FileAttacher::displayName("rechnung\u{202E}fdp.exe"));
        $this->assertSame('a b.pdf', FileAttacher::displayName("a\u{200B} b.pdf"));
        $this->assertSame('file', FileAttacher::displayName("\u{202E}\u{2066}"));
    }

    public function test_search_text_and_stored_form(): void {
        $mail = $this->reader->read(MailFiles::fixture('rueckfrage-unicode.msg'), MailFileFormat::Msg);
        $text = $this->reader->searchText($mail);

        $this->assertStringContainsString('Rückfrage zur Übergabe', $text);
        $this->assertStringContainsString('erika.mustermann@example.org', $text);
        $this->assertStringContainsString('Grüße aus Köln', $text);
        $this->assertStringContainsString('Ursprüngliche Anfrage', $text);
        $this->assertStringContainsString('Angebot Übergabe.pdf', $text);

        $array = $mail->toArray();
        $this->assertArrayNotHasKey('html', $array);
        $this->assertArrayNotHasKey('content', $array['attachments'][0]);
        $restored = MailFileMessage::fromArray($array);
        $this->assertSame($mail->subject, $restored->subject);
        $this->assertSame('notiz.txt', $restored->part('2.0')?->name);
        $this->assertSame('', $restored->attachments[0]->content);
        $this->assertTrue($restored->attachments[0]->safe);
    }

    private function assertQuickFailure(MailFileFailure $expected, string $eml): void {
        foreach (['precheck', 'read'] as $step) {
            $started = microtime(true);
            $this->assertFailure($expected, fn () => $step === 'read' ? $this->reader->read($eml, MailFileFormat::Eml) : $this->reader->precheck($eml, MailFileFormat::Eml));
            $this->assertLessThan(3.0, microtime(true) - $started, $step);
        }
    }

    private function assertFailure(MailFileFailure $expected, callable $read): void {
        try {
            $read();
            $this->fail('Erwartet: ' . $expected->value);
        } catch (MailFileUnreadableException $e) {
            $this->assertSame($expected, $e->reason);
        }
    }
}
