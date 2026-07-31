<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxCsvParserTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Plugins;

use App\Plugins\Fritzbox\Sources\{FritzboxCall, FritzboxCsvParser};
use PHPUnit\Framework\TestCase;

class FritzboxCsvParserTest extends TestCase {
    private FritzboxCsvParser $parser;

    protected function setUp(): void {
        parent::setUp();
        $this->parser = new FritzboxCsvParser;
    }

    /** Realistischer Export: ISO-8859-1, sep=-Vorzeile, Umlaute, 2-stelliges Jahr. */
    private function fixture(): string {
        $utf8 = implode("\r\n", [
            'sep=;',
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '1;30.07.26 15:41;Müller GmbH;004592459500;Dänemark;ISDN Gerät;97911585;0:05',
            '2;30.07.26 14:37;;024339392801;Hückelhoven;;97911585;0:00',
            '4;23.07.26 09:08;;030208477964;Berlin;ISDN Gerät;97911585;0:02',
            '1;30.07.26 20:11;;01607661343;;;Internet: 58399100;1:05',
            '',
        ]);

        return mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
    }

    public function test_parses_fritzbox_export_with_encoding_and_sep_line(): void {
        $calls = $this->parser->parse($this->fixture(), 'Europe/Berlin');

        $this->assertCount(4, $calls);

        $first = $calls[0];
        $this->assertSame(FritzboxCall::TYPE_INCOMING, $first->type);
        $this->assertSame(FritzboxCall::DIR_IN, $first->direction);
        $this->assertSame('Müller GmbH', $first->name);
        $this->assertSame('+4592459500', $first->e164); // 0049-Schreibweise? Nein: 0045 → Dänemark
        $this->assertSame(5, $first->durationMinutes);
        $this->assertSame('97911585', $first->ownLine);
        // 30.07.2026 15:41 CEST (UTC+2) → 13:41 UTC; Ende = Start + Dauer.
        $this->assertSame('2026-07-30 13:41:00', $first->startedAt->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $first->startedAt->timezoneName);
        $this->assertSame('2026-07-30 13:46:00', $first->endedAt->format('Y-m-d H:i:s'));
    }

    public function test_missed_and_outgoing_types(): void {
        $calls = $this->parser->parse($this->fixture(), 'Europe/Berlin');

        $missed = $calls[1];
        $this->assertSame(FritzboxCall::TYPE_MISSED, $missed->type);
        $this->assertTrue($missed->isMissed());
        $this->assertSame(0, $missed->durationMinutes);

        $outgoing = $calls[2];
        $this->assertSame(FritzboxCall::TYPE_OUTGOING, $outgoing->type);
        $this->assertSame(FritzboxCall::DIR_OUT, $outgoing->direction);
        $this->assertFalse($outgoing->isMissed());
        $this->assertSame('+4930208477964', $outgoing->e164);
    }

    public function test_duration_is_hours_colon_minutes_and_internet_line_is_normalized(): void {
        $calls = $this->parser->parse($this->fixture(), 'Europe/Berlin');

        $long = $calls[3];
        $this->assertSame(65, $long->durationMinutes); // 1:05 = 1 h 5 min
        $this->assertSame('58399100', $long->ownLine); // "Internet: "-Präfix entfernt
        $this->assertSame('+491607661343', $long->e164);
        $this->assertNull($long->name);
    }

    public function test_type3_is_rejected_by_default_but_outgoing_with_duration_or_flag(): void {
        $csv = implode("\n", [
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '3;18.07.26 13:21;;03375295297;;;97911585;0:09',
            '3;18.07.26 14:00;;03375295297;;;97911585;0:00',
        ]);

        $calls = $this->parser->parse($csv, 'Europe/Berlin');
        $this->assertSame(FritzboxCall::DIR_OUT, $calls[0]->direction); // Dauer > 0 → faktisch ausgehend
        $this->assertSame(FritzboxCall::DIR_IN, $calls[1]->direction);
        $this->assertTrue($calls[1]->isMissed());

        $legacy = $this->parser->parse($csv, 'Europe/Berlin', type3Outgoing: true);
        $this->assertSame(FritzboxCall::DIR_OUT, $legacy[1]->direction);
    }

    public function test_suppressed_number_and_broken_rows(): void {
        $csv = implode("\n", [
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '1;30.07.26 10:00;;;;;97911585;0:04',
            'x;kaputt;;;;;97911585;abc',
            '1;kein Datum;;0123456789;;;97911585;0:04',
        ]);

        $calls = $this->parser->parse($csv, 'Europe/Berlin');
        $this->assertCount(1, $calls); // kaputte Zeilen übersprungen
        $this->assertNull($calls[0]->e164);
        $this->assertSame('', $calls[0]->numberRaw);
    }

    public function test_call_key_is_deterministic_and_distinct(): void {
        $calls = $this->parser->parse($this->fixture(), 'Europe/Berlin');
        $again = $this->parser->parse($this->fixture(), 'Europe/Berlin');

        $this->assertSame($calls[0]->callKey(), $again[0]->callKey());
        $this->assertNotSame($calls[0]->callKey(), $calls[2]->callKey());
        $this->assertStringStartsWith('call:', $calls[0]->callKey());
    }

    public function test_rejects_non_fritzbox_content(): void {
        $this->expectException(\RuntimeException::class);
        $this->parser->parse("Date,Client,Project,Duration\n2026-01-01,Acme,Web,1:00", 'Europe/Berlin');
    }

    public function test_looks_like_call_report_sniffs_content(): void {
        $this->assertTrue(FritzboxCsvParser::looksLikeCallReport($this->fixture()));
        $this->assertTrue(FritzboxCsvParser::looksLikeCallReport("\u{FEFF}Typ;Datum;Name;Rufnummer;;;Eigene Rufnummer;Dauer\n"));
        $this->assertFalse(FritzboxCsvParser::looksLikeCallReport("Date,Client,Project\n2026-01-01,Acme,Web"));
        $this->assertFalse(FritzboxCsvParser::looksLikeCallReport(''));
    }

    public function test_winter_time_offset(): void {
        $csv = implode("\n", [
            'Typ;Datum;Name;Rufnummer;Landes-/Ortsnetzbereich;Nebenstelle;Eigene Rufnummer;Dauer',
            '1;15.01.26 10:00;;0123456789;;;97911585;0:10',
        ]);

        $calls = $this->parser->parse($csv, 'Europe/Berlin');
        // 15.01.2026 10:00 CET (UTC+1) → 09:00 UTC.
        $this->assertSame('2026-01-15 09:00:00', $calls[0]->startedAt->format('Y-m-d H:i:s'));
    }
}
