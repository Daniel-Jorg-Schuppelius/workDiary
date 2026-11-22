<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImapHeaderDecoderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Services\Mail\ImapHeaderDecoder;
use Tests\TestCase;
use Webklex\PHPIMAP\{Config, Header};

/**
 * Kopfzeilen dekodiert genau einmal, ob `ext-imap` geladen ist oder nicht
 * (Befund 2026-11-22: mit `ext-imap` wurde aus „Büro“ „Bro“, ohne blieb ein
 * kodierter Betreff im IMAP-Postfach roh). Alle Adressen sind erfunden.
 */
final class ImapHeaderDecoderTest extends TestCase {
    private function decoder(): ImapHeaderDecoder {
        $decoder = Config::make(['decoding' => ImapHeaderDecoder::DECODING])->getDecoder('header');
        $this->assertInstanceOf(ImapHeaderDecoder::class, $decoder);

        return $decoder;
    }

    public function test_encoded_words_are_decoded_once(): void {
        $decoder = $this->decoder();

        $this->assertSame('Angebot für die Übergabe', $decoder->decode('=?UTF-8?Q?Angebot_f=C3=BCr_die_=C3=9Cbergabe?='));
        $this->assertSame('Büro', $decoder->decode('=?ISO-8859-1?Q?B=FCro?='));
        $this->assertSame('Grüße', $decoder->decode('=?UTF-8?B?R3LDvMOfZQ==?='));
    }

    public function test_decoded_and_raw_utf8_text_stays_unchanged(): void {
        $decoder = $this->decoder();

        // Der Weg mit ext-imap: imap_mime_header_decode() hat schon dekodiert.
        $this->assertSame('Erika Mustermann (Büro)', $decoder->decode('Erika Mustermann (Büro)'));
        // Rohe UTF-8-Kopfzeile (RFC 6532).
        $this->assertSame('Jörg Müller', $decoder->decode('Jörg Müller'));
        $this->assertSame(['Grüße', 'Größe'], $decoder->decode(['Grüße', '=?UTF-8?Q?Gr=C3=B6=C3=9Fe?=']));
    }

    public function test_splitting_never_decodes_so_the_result_does_not_depend_on_ext_imap(): void {
        $parts = $this->decoder()->mimeHeaderDecode('=?UTF-8?Q?Erika_Mustermann_=28B=C3=BCro=29?=');

        $this->assertCount(1, $parts);
        $this->assertSame('=?UTF-8?Q?Erika_Mustermann_=28B=C3=BCro=29?=', $parts[0]->text);
    }

    public function test_header_reads_display_names_and_subject(): void {
        $raw = implode("\r\n", [
            'From: =?UTF-8?Q?Erika_Mustermann_=28B=C3=BCro=29?= <erika.mustermann@example.org>',
            'To: Jörg Müller <joerg.mueller@example.com>, =?ISO-8859-1?Q?Max_B=E4cker?= <max@example.net>',
            'Subject: =?UTF-8?Q?Angebot_f=C3=BCr_die_=C3=9Cbergabe?=',
            '',
        ]);

        $header = new Header($raw, Config::make(['options' => ['rfc822' => false], 'decoding' => ImapHeaderDecoder::DECODING]));

        $this->assertSame('Erika Mustermann (Büro)', $header->get('from')->first()->personal);
        $this->assertSame(['Jörg Müller', 'Max Bäcker'], array_map(static fn ($address): string => $address->personal, $header->get('to')->all()));
        $this->assertSame('Angebot für die Übergabe', (string) $header->get('subject'));
    }
}
