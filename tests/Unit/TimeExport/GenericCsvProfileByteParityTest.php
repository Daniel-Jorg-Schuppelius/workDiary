<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericCsvProfileByteParityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\TimeExport;

use App\Services\TimeExport\Profiles\GenericCsvProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Byte-Parität des CSV-Serializers (Audit 2026-08, W2.8).
 *
 * Der app-lokale Always-Quote-Serializer wurde durch
 * `CsvStringHelper::encodeLine(..., QuotingStyle::ALWAYS)` ersetzt. Weil der
 * `payload_hash` des Lohn-Exports revisionsrelevant ist (er weist den
 * ausgelieferten Stand nach), hält dieser Test die erzeugten Bytes fest:
 * Ändert sich das Toolkit-Verhalten, schlägt der Test an, statt still neue
 * Hashes für unveränderte Daten zu erzeugen.
 */
class GenericCsvProfileByteParityTest extends TestCase {
    /** Referenzimplementierung, wie sie bis W2.8 in der App stand. */
    private function legacyLine(array $fields, string $enclosure = '"', string $delimiter = ';'): string {
        $escaped = array_map(
            static fn (string $v): string => $enclosure . str_replace($enclosure, $enclosure . $enclosure, $v) . $enclosure,
            $fields,
        );

        return implode($delimiter, $escaped);
    }

    private function currentLine(array $fields): string {
        $method = new ReflectionMethod(GenericCsvProfile::class, 'csvLine');

        return (string) $method->invoke(new GenericCsvProfile, $fields);
    }

    /** @return array<string, array{0: list<string>}> */
    public static function rowProvider(): array {
        return [
            'typische Zeile' => [['1', 'stunden', '', '2026-08-01', '2026-08-31', '10.5000', 'h', '']],
            'Quote im Wert' => [['7', 'zuschlag', 'KST-1', '2026-08-01', '2026-08-31', '1.0000', 'h', 'Notiz mit "Anführung"']],
            'Trenner im Wert' => [['9', 'x', 'a;b', '2026-08-01', '2026-08-31', '0.0000', 'h', "Zeile\nUmbruch"]],
            'alles leer' => [['', '', '', '', '', '', '', '']],
        ];
    }

    /** @param  list<string>  $fields */
    #[DataProvider('rowProvider')]
    public function test_toolkit_serializer_is_byte_identical_to_the_previous_implementation(array $fields): void {
        $this->assertSame($this->legacyLine($fields), $this->currentLine($fields));
    }

    /** Der Hash über eine feste Zeile darf sich nicht ändern (payload_hash-Stabilität). */
    public function test_payload_hash_of_a_fixed_row_is_stable(): void {
        $line = $this->currentLine(['42', 'stunden', 'KST-9', '2026-08-01', '2026-08-31', '7.2500', 'h', 'Regelarbeit']);

        $this->assertSame(
            hash('sha256', '"42";"stunden";"KST-9";"2026-08-01";"2026-08-31";"7.2500";"h";"Regelarbeit"'),
            hash('sha256', $line),
        );
    }
}
