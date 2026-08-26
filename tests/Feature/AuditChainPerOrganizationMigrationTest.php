<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditChainPerOrganizationMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{AuditLog, Organization};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use RuntimeException;
use Tests\Concerns\IsolatesAuditChainProofs;
use Tests\TestCase;

/**
 * Sichert die Umkettungs-Migration je Organisation ab (MVP-722, Vollscan
 * 2026-08-23 A5): Bestand aus der Zeit der EINEN Tabellenkette wird auf
 * `tabelle:organisation` umgekettet — Nutzdaten unangetastet, Kettenköpfe
 * ersetzt, `audit:verify` grün. Eine echte Veränderung im Bestand bricht die
 * Migration ab, ohne etwas zu überschreiben.
 */
class AuditChainPerOrganizationMigrationTest extends TestCase {
    use IsolatesAuditChainProofs;
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->isolateAuditChainProofs();
    }

    protected function tearDown(): void {
        $this->releaseAuditChainProofs();
        parent::tearDown();
    }

    /**
     * Legt Zeilen an und verkettet sie anschließend so, wie es die frühere
     * EINE Tabellenkette getan hätte (id-Reihenfolge über alle Organisationen).
     *
     * @param  list<Organization>  $organizations
     * @return list<int> ids in Schreibreihenfolge
     */
    private function seedLegacyChain(array $organizations): array {
        // Das Anlegen der Organisationen hat selbst schon Audit-Zeilen (und
        // Kettenköpfe) erzeugt — der Alt-Zustand beginnt bei null.
        DB::table('audit_logs')->delete();
        DB::table('audit_chain_heads')->delete();

        $ids = [];
        foreach ($organizations as $index => $organization) {
            foreach (['created', 'updated'] as $event) {
                $ids[] = (int) DB::table('audit_logs')->insertGetId([
                    'organization_id' => $organization->id,
                    'user_id' => null,
                    'event' => $event,
                    'auditable_type' => 'TestModel',
                    'auditable_id' => $index + 1,
                    'changes' => json_encode(['after' => ['x' => $index]]),
                    'ip' => '10.0.0.1',
                    'user_agent' => 'phpunit',
                    'created_at' => '2026-01-0' . ($index + 1) . ' 10:00:00',
                    'updated_at' => '2026-01-0' . ($index + 1) . ' 10:00:00',
                ]);
            }
        }

        $prev = null;
        foreach (DB::table('audit_logs')->orderBy('id')->get() as $row) {
            $model = AuditLog::fromStorageRow((array) $row);
            $hash = AuditLog::chainHash($prev, $model->hashPayload());
            DB::table('audit_logs')->where('id', $row->id)->update(['prev_hash' => $prev, 'hash' => $hash]);
            $prev = $hash;
        }

        DB::table('audit_chain_heads')->where('chain', 'like', 'audit_logs%')->delete();
        DB::table('audit_chain_heads')->insert([
            'chain' => 'audit_logs',
            'head_hash' => $prev,
            'height' => count($ids),
        ]);

        return $ids;
    }

    private function runMigration(): void {
        $migration = require database_path('migrations/2027_02_19_103100_rechain_audit_hashes_per_organization.php');
        $migration->up();
    }

    public function test_legacy_table_chain_is_split_per_organization(): void {
        $one = Organization::factory()->create();
        $two = Organization::factory()->create();
        $ids = $this->seedLegacyChain([$one, $two]);

        $before = DB::table('audit_logs')->orderBy('id')->pluck('event', 'id')->all();
        $this->assertSame(1, Artisan::call('audit:verify'), 'Der Alt-Bestand verletzt die neue Aufteilung.');

        $this->runMigration();

        $this->assertSame(0, Artisan::call('audit:verify'), 'Nach der Umkettung müssen alle Ketten verifizieren.');
        $this->assertSame($before, DB::table('audit_logs')->orderBy('id')->pluck('event', 'id')->all(), 'Nutzdaten bleiben unverändert.');

        // Jede Organisation beginnt eine eigene Kette.
        $rows = DB::table('audit_logs')->orderBy('id')->get()->keyBy('id');
        $this->assertNull($rows[$ids[0]]->prev_hash);
        $this->assertNull($rows[$ids[2]]->prev_hash, 'Die zweite Organisation beginnt bei NULL.');
        $this->assertSame($rows[$ids[0]]->hash, $rows[$ids[1]]->prev_hash);
        $this->assertSame($rows[$ids[2]]->hash, $rows[$ids[3]]->prev_hash);

        // Genau EIN Nachweis (MVP-723): die Datei entsteht am Ende des Laufs,
        // gepuffert — nicht Zeile für Zeile und nicht ohne Umkettung.
        $this->assertCount(1, $this->auditChainProofs(), 'Eine echte Umkettung schreibt genau eine Nachweisdatei.');
        // Nur die zweite Organisation musste umgekettet werden — die erste
        // beginnt in beiden Welten bei NULL und bleibt byte-identisch.
        $this->assertSame(2, substr_count((string) file_get_contents($this->auditChainProofs()[0]), "\n"));

        // Kettenköpfe: je Organisation einer, der alte Tabellenkopf ist weg.
        $this->assertDatabaseMissing('audit_chain_heads', ['chain' => 'audit_logs']);
        foreach ([[$one, $ids[1]], [$two, $ids[3]]] as [$organization, $lastId]) {
            $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs:' . $organization->id)->first();
            $this->assertNotNull($head);
            $this->assertSame($rows[$lastId]->hash, $head->head_hash);
            $this->assertSame(2, (int) $head->height);
        }
    }

    /**
     * Auf leerer Datenbank (migrate:fresh) gibt es nichts umzuketten — dann
     * darf auch kein Nachweis entstehen (MVP-723). Vorher legte jeder Lauf
     * eine Datei an, sobald überhaupt eine Zeile angefasst wurde.
     */
    public function test_empty_database_writes_no_proof(): void {
        foreach (array_keys((array) config('audit.chains', [])) as $table) {
            DB::table((string) $table)->delete();
        }
        DB::table('audit_chain_heads')->delete();

        $this->runMigration();

        $this->assertSame([], $this->auditChainProofs(), 'Ohne Bestand darf kein GoBD-Nachweis entstehen.');
    }

    /** Ein Abbruch rollt zurück — dann darf kein Nachweis über die Rewrites liegen. */
    public function test_tampered_row_aborts_the_migration_without_rewriting(): void {
        $one = Organization::factory()->create();
        $ids = $this->seedLegacyChain([$one]);

        DB::table('audit_logs')->where('id', $ids[1])->update(['event' => 'tampered']);
        $untouched = DB::table('audit_logs')->orderBy('id')->pluck('hash', 'id')->all();

        try {
            $this->runMigration();
            $this->fail('Eine echte Veränderung muss die Umkettung abbrechen.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Abbruch ohne Umkettung', $exception->getMessage());
        }

        $this->assertSame($untouched, DB::table('audit_logs')->orderBy('id')->pluck('hash', 'id')->all(), 'Es darf nichts überschrieben worden sein.');
        $this->assertSame([], $this->auditChainProofs(), 'Ein Abbruch darf keinen Nachweis hinterlassen.');
    }
}
