<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactColumnsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-869: Anschriften liegen im Satelliten `contact_addresses`, nicht
 * als `street`/`zip`/`postal_code`/`city` an der Partei. Ausnahmen sind keine
 * Parteiadressen (Einsatzort, Objekt) oder die dokumentierte Projektion der
 * Primäradresse an Kunde/Lieferant.
 */
class ContactColumnsRuleTest extends TestCase {
    use ScansSourceTree;

    private const COLUMN = '/^(?:address_)?(?:street|zip|postal_code|city)$/';

    /** Migrationen nach diesem Zeitstempel dürfen keine Adressspalten mehr anlegen (die Umzugsmigration selbst stellt sie in down() wieder her). */
    private const CUTOFF = '2027_02_24_140000';

    /** @var array<string, string> Tabelle → Grund */
    private const TABLES_ALLOWED = [
        'contact_addresses' => 'Der Satellit selbst.',
        'customers' => 'Projektion der Primäradresse (ContactDetailsProjectionObserver) für Listen und Lexoffice-Abgleich.',
        'suppliers' => 'Projektion der Primäradresse (ContactDetailsProjectionObserver).',
        'diary_entries' => 'Einsatzort des Auftrags, keine Parteiadresse.',
        'sites' => 'Objektadresse (Standort), keine Parteiadresse.',
        'buildings' => 'Objektadresse (Gebäude), keine Parteiadresse.',
        'organizations' => 'Absenderanschrift der Organisation (Branding/PDF).',
    ];

    public function test_schema_keeps_party_addresses_in_the_satellite(): void {
        $violations = [];
        foreach ($this->schemaTables() as $table => $definition) {
            if (isset(self::TABLES_ALLOWED[$table])) {
                continue;
            }
            foreach (array_keys($definition['columns']) as $column) {
                if (preg_match(self::COLUMN, $column) === 1) {
                    $violations[] = "{$table}.{$column}";
                }
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Adressspalten gehören nach contact_addresses (HasContactAndBankDetails, WritesContactDetails); Ausnahme mit Grund in TABLES_ALLOWED:\n" . implode("\n", $violations));
    }

    public function test_new_migrations_do_not_add_address_columns(): void {
        $violations = [];
        foreach ($this->phpFiles('database/migrations') as $file) {
            $name = basename($file, '.php');
            if (strcmp(substr($name, 0, strlen(self::CUTOFF)), self::CUTOFF) <= 0) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match_all('/->(?:string|text|char)\(\s*[\'"]((?:address_)?(?:street|zip|postal_code|city))[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[1] as [$column, $offset]) {
                $table = $this->tableBefore($source, $offset);
                if (isset(self::TABLES_ALLOWED[$table])) {
                    continue;
                }
                $violations[] = "{$name}: {$table}.{$column}";
            }
        }

        $this->assertSame([], $violations, "Neue Adressspalten nur in contact_addresses oder mit Grund in TABLES_ALLOWED:\n" . implode("\n", $violations));
    }

    /** Tabellenname des umgebenden Schema::create/table-Aufrufs. */
    private function tableBefore(string $source, int $offset): string {
        if (preg_match_all('/Schema::(?:create|table)\(\s*[\'"](\w+)[\'"]/', substr($source, 0, $offset), $matches, PREG_OFFSET_CAPTURE) === 0) {
            return '';
        }
        $last = end($matches[1]);

        return $last === false ? '' : $last[0];
    }
}
