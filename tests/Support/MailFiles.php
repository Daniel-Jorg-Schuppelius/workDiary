<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailFiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Http\UploadedFile;

/**
 * Erfundene Mail-Dateien für Feature 204 — keine echten Mails, keine echten
 * Personen (Adressen unter example.org/example.com).
 *
 * `.eml` baut diese Klasse als Text. Die `.msg`-Fixtures unter
 * `tests/Fixtures/mail/` sind mit dem Testschreiber des php-common-toolkits
 * (`tests/Support/OutlookMessageWriter` + `MessageSpec`, v2.17.0) erzeugt:
 * Betreff „Rückfrage zur Übergabe – Größe & Maße“, Absender Erika Mustermann,
 * An Max Beispiel, Kopie Büro Beispiel, Text- und HTML-Körper (HTML mit Skript,
 * Formular, Fremdbild und `cid:logo@beispiel.test`), Anhänge „Angebot
 * Übergabe.pdf“, `logo.png` (Content-ID, eingebettet) und eine eingebettete
 * Nachricht „Ursprüngliche Anfrage“ mit `notiz.txt`; einmal als Unicode
 * (001F), einmal als 8-Bit in Windows-1252. `word-dokument.doc` ist ein
 * Compound File mit WordDocument-Stream, `ole-ohne-nachricht.bin` eines ohne
 * Nachrichteneigenschaften.
 */
final class MailFiles {
    /** Kleinstes gültiges PDF (Fileinfo erkennt application/pdf). */
    public const string PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    /** 1×1-PNG. */
    public const string PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /**
     * Upload aus einer echten Temp-Datei: anders als `UploadedFile::fake()`
     * meldet getMimeType() hier den am Inhalt erkannten Typ, wie im Betrieb.
     */
    public static function upload(string $name, string $content): UploadedFile {
        $path = (string) tempnam(sys_get_temp_dir(), 'mailfile-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }

    public static function fixture(string $name): string {
        return File::read(self::fixturePath($name));
    }

    public static function fixturePath(string $name): string {
        return dirname(__DIR__) . '/Fixtures/mail/' . $name;
    }

    /**
     * `.eml` mit reinem HTML-Körper (Skript, Formular, Fremdbild, eingebettetes
     * Logo per cid), PDF-Anhang und einer als Anlage weitergeleiteten Mail.
     */
    public static function eml(): string {
        $inner = implode("\r\n", [
            'From: Max Beispiel <max.beispiel@example.com>',
            'To: erika.mustermann@example.org',
            'Subject: =?UTF-8?Q?Urspr=C3=BCngliche_Anfrage?=',
            'Date: Tue, 03 Nov 2026 09:00:00 +0100',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            'Bitte um Rückruf wegen der Maße.',
            '',
        ]);
        $html = '<html><head><meta http-equiv="refresh" content="0;url=https://phish.example.invalid/">'
            . '<link rel="stylesheet" href="https://cdn.example.invalid/x.css"><title>Kopf</title></head><body>'
            . '<p>Guten Tag Frau Mustermann,</p><p>anbei das <b>Angebot</b> für die Übergabe.</p>'
            . '<img src="cid:logo@beispiel.test" alt="Logo">'
            . '<img src="https://tracker.example.invalid/pixel.gif" alt="">'
            . '<script>alert("pwned")</script>'
            . '<form action="https://phish.example.invalid/"><input name="pw"></form>'
            . '</body></html>';

        return implode("\r\n", [
            'Return-Path: <erika.mustermann@example.org>',
            'From: =?UTF-8?Q?Erika_Mustermann_=28B=C3=BCro=29?= <erika.mustermann@example.org>',
            'To: Max Beispiel <max.beispiel@example.com>',
            'Cc: buero@example.com',
            'Subject: =?UTF-8?Q?Angebot_f=C3=BCr_die_=C3=9Cbergabe?=',
            'Date: Wed, 04 Nov 2026 10:15:00 +0100',
            'Message-ID: <angebot-0815@example.org>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="mix"',
            '',
            '--mix',
            'Content-Type: multipart/related; boundary="rel"',
            '',
            '--rel',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            rtrim(chunk_split(base64_encode($html), 76, "\r\n")),
            '--rel',
            'Content-Type: image/png',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <logo@beispiel.test>',
            'Content-Disposition: inline; filename="logo.png"',
            '',
            self::PNG_BASE64,
            '--rel--',
            '--mix',
            'Content-Type: application/pdf; name="angebot.pdf"',
            'Content-Transfer-Encoding: base64',
            "Content-Disposition: attachment; filename*=UTF-8''Angebot%20%C3%9Cbergabe.pdf",
            '',
            rtrim(chunk_split(base64_encode(self::PDF), 76, "\r\n")),
            '--mix',
            'Content-Type: message/rfc822',
            'Content-Disposition: attachment; filename="weitergeleitet.eml"',
            '',
            $inner,
            '--mix--',
            '',
        ]);
    }

    /** Einfache Text-Mail, wahlweise mit vielen kleinen Anhängen. */
    public static function plainEml(string $subject = 'Kurze Frage', int $attachments = 0): string {
        $lines = [
            'From: Erika Mustermann <erika.mustermann@example.org>',
            'To: max.beispiel@example.com',
            'Subject: ' . $subject,
            'Date: Wed, 04 Nov 2026 10:15:00 +0100',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="b"',
            '',
            '--b',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'Kurzer Text.',
        ];
        for ($i = 1; $i <= $attachments; $i++) {
            array_push($lines, '--b', 'Content-Type: text/plain; name="datei-' . $i . '.txt"', 'Content-Disposition: attachment; filename="datei-' . $i . '.txt"', '', 'Inhalt ' . $i);
        }
        $lines[] = '--b--';
        $lines[] = '';

        return implode("\r\n", $lines);
    }
}
