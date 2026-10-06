<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubTwinRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Http\Controllers\Club\Concerns\ConvertsEventTimesToUtc;
use App\Services\Club\Demo\ClubDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Regeln, die im Vereinsmodul doppelt standen (Konsolidierungs-Audit 2026-10, k3-12, k3-13). */
final class ClubTwinRulesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** Zwei der drei Kopien machten aus einem leeren Ende die aktuelle Uhrzeit. */
    public function test_empty_end_time_stays_empty(): void {
        $converter = new class {
            use ConvertsEventTimesToUtc;

            /** @param array<string, mixed> $data @return array<string, mixed> */
            public function convert(array $data): array {
                return $this->withUtcTimes($data);
            }
        };

        $data = $converter->convert(['timezone' => 'Europe/Berlin', 'started_at' => '2026-07-01 18:00', 'ended_at' => null]);

        $this->assertSame('2026-07-01 16:00:00', $data['started_at']);
        $this->assertNull($data['ended_at']);
    }

    /** Der Demo-Reset ließ Spenden und Zuwendungsbestätigungen ohne Mitglied stehen. */
    public function test_demo_reset_removes_donations_and_receipts(): void {
        $receiptId = DB::table('club_donation_receipts')->insertGetId($this->row('club_donation_receipts'));
        DB::table('club_donations')->insert($this->row('club_donations', ['club_donation_receipt_id' => $receiptId]));

        app(ClubDemoSeeder::class)->purge($this->organization);

        $this->assertSame(0, DB::table('club_donations')->where('organization_id', $this->organization->id)->count());
        $this->assertSame(0, DB::table('club_donation_receipts')->where('organization_id', $this->organization->id)->count());
    }

    /**
     * Zeile mit den Pflichtspalten der Tabelle — die Probe braucht keine fachlich stimmige Spende.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(string $table, array $overrides = []): array {
        $row = ['organization_id' => $this->organization->id, 'created_at' => now(), 'updated_at' => now()];
        foreach (\Illuminate\Support\Facades\Schema::getColumns($table) as $column) {
            $name = $column['name'];
            if (isset($row[$name]) || $column['nullable'] || $column['default'] !== null || $column['auto_increment']) {
                continue;
            }
            $type = strtolower((string) $column['type_name']);
            $row[$name] = match (true) {
                str_contains($type, 'int'), str_contains($type, 'decimal'), str_contains($type, 'numeric') => 1,
                str_contains($type, 'date') || str_contains($type, 'time') => now(),
                // Gültig als Text und als JSON — MariaDB führt JSON-Spalten als longtext mit Prüfbedingung.
                default => '{}',
            };
        }

        return $overrides + $row;
    }
}
