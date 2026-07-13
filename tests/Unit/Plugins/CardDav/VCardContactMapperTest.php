<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VCardContactMapperTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins\CardDav;

use App\Plugins\CardDav\Services\VCardContactMapper;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Tests\TestCase;

/**
 * Bauturbo A9 (MVP-329): vCard → Kundenschema. Umlaute, mehrere Mails
 * (WORK bevorzugt), Telefontypen, ADR-Komponenten, UID-Fallback und die
 * Freitext-Länder-Leitplanke (nur ISO-2 wird übernommen).
 */
final class VCardContactMapperTest extends TestCase {
    private function parse(string $raw): VCard {
        /** @var VCard $card */
        $card = Reader::read($raw, Reader::OPTION_FORGIVING);

        return $card;
    }

    public function test_maps_full_vcard_with_umlauts_and_multiple_emails(): void {
        $card = $this->parse(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'UID:uid-mueller-1',
            'FN:Jürgen Müller',
            'N:Müller;Jürgen;;;',
            'ORG:Müller & Söhne GmbH;Vertrieb',
            'EMAIL;TYPE=HOME:privat@example.org',
            'EMAIL;TYPE=WORK:juergen.mueller@example.com',
            'TEL;TYPE=WORK,VOICE:+49 30 123456',
            'TEL;TYPE=CELL:+49 170 111222',
            'TEL;TYPE=FAX:+49 30 654321',
            'ADR;TYPE=WORK:;;Große Straße 5;München;;80331;Deutschland',
            'NOTE:Bevorzugt E-Mail',
            'END:VCARD',
        ]));

        $result = (new VCardContactMapper)->map($card, 'fallback');

        $this->assertSame('uid-mueller-1', $result['uid']);
        $this->assertSame('Jürgen Müller', $result['mapped']['name']);
        $this->assertSame('Müller & Söhne GmbH', $result['mapped']['company']);
        // WORK-Mail hat Vorrang vor der Dokumentreihenfolge.
        $this->assertSame('juergen.mueller@example.com', $result['mapped']['email']);
        $this->assertSame('+49 30 123456', $result['mapped']['phone']);
        $this->assertSame('+49 170 111222', $result['mapped']['mobile']);
        $this->assertSame('+49 30 654321', $result['mapped']['fax']);
        $this->assertSame('Große Straße 5', $result['mapped']['address_street']);
        $this->assertSame('80331', $result['mapped']['address_zip']);
        $this->assertSame('München', $result['mapped']['address_city']);
        $this->assertSame('Bevorzugt E-Mail', $result['mapped']['comment']);
        // Freitext-Land („Deutschland") wird NICHT ins country-Feld übernommen …
        $this->assertArrayNotHasKey('country', $result['mapped']);
        // … bleibt aber im verlustfreien raw-Snapshot erhalten (beide Mails ebenso).
        $this->assertSame('Deutschland', $result['raw']['address']['country']);
        $this->assertCount(2, $result['raw']['emails']);
    }

    public function test_falls_back_to_n_property_and_href_uid(): void {
        $card = $this->parse(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:Muster;Erika;;;',
            'EMAIL:erika@example.org',
            'END:VCARD',
        ]));

        $result = (new VCardContactMapper)->map($card, 'abc123.vcf');

        $this->assertSame('abc123.vcf', $result['uid']);
        $this->assertSame('Erika Muster', $result['mapped']['name']);
        $this->assertSame('erika@example.org', $result['mapped']['email']);
    }

    public function test_accepts_iso2_country_and_vcard40_types(): void {
        $card = $this->parse(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'UID:urn:uuid:4711',
            'FN:ACME AG',
            'ORG:ACME AG',
            'EMAIL;TYPE=work:info@acme.example',
            'ADR;TYPE=work:;;Hauptstr. 1;Berlin;;10115;de',
            'END:VCARD',
        ]));

        $result = (new VCardContactMapper)->map($card, 'fallback');

        $this->assertSame('urn:uuid:4711', $result['uid']);
        $this->assertSame('DE', $result['mapped']['country']);
        $this->assertSame('info@acme.example', $result['mapped']['email']);
    }

    public function test_untyped_phone_becomes_default_number(): void {
        $card = $this->parse(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'UID:uid-2',
            'FN:Ohne Typen',
            'TEL:+49 89 555',
            'END:VCARD',
        ]));

        $result = (new VCardContactMapper)->map($card, 'fallback');

        $this->assertSame('+49 89 555', $result['mapped']['phone']);
        $this->assertArrayNotHasKey('mobile', $result['mapped']);
        $this->assertArrayNotHasKey('fax', $result['mapped']);
    }
}
