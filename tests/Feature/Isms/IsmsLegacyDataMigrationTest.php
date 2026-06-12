<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsLegacyDataMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Models\{Organization, User};
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Migrationspfad Feature 044 → 046 (2026_06_11_090500 + 090600):
 *
 * RefreshDatabase beweist bereits, dass die Datenmigration auf leerer DB
 * durchläuft (sie ist Teil des Migrationsstapels). Dieser Test stellt
 * ZUSÄTZLICH den Alt-Daten-Pfad nach: die Legacy-Spalten werden auf dem
 * fertig migrierten Schema re-erzeugt, Alt-Zeilen eingespielt und die
 * Migrationsklassen erneut ausgeführt (beide sind bewusst idempotent/
 * defensiv mit hasColumn-/hasIndex-Guards, genau damit dieser Replay
 * möglich ist).
 */
class IsmsLegacyDataMigrationTest extends TestCase {
    use RefreshDatabase;

    public function test_legacy_controls_are_migrated_to_requirements_statements_and_measures(): void {
        $organization = Organization::factory()->create(['slug' => 'isms-legacy-mig']);
        $owner = User::factory()->create(['organization_id' => $organization->id]);

        $this->recreateLegacyColumns();

        // 1) Reiner Annex-A-Katalogeintrag ohne Eigenanteil → wird zu
        //    Requirement + Statement, Control-Zeile entfällt.
        $plainCatalogId = $this->insertLegacyControl($organization->id, [
            'code' => 'A.5.1',
            'title' => 'Informationssicherheitsrichtlinien',
            'source' => 'iso27001AnnexA',
            'applicable' => 1,
            'justification' => null,
            'implementation_status' => 'partial',
            'evidence_note' => 'Richtlinie v2 im DMS',
        ]);

        // 2) Nicht anwendbarer Katalogeintrag → Statement übernimmt
        //    applicable=false + Begründung.
        $this->insertLegacyControl($organization->id, [
            'code' => 'A.8.4',
            'title' => 'Zugriff auf Quellcode',
            'source' => 'iso27001AnnexA',
            'applicable' => 0,
            'justification' => 'Kein eigener Quellcode.',
            'implementation_status' => 'notApplicable',
        ]);

        // 3) Katalogeintrag MIT Eigenanteil (Owner) → bleibt als Maßnahme
        //    inkl. Mapping bestehen.
        $ownedCatalogId = $this->insertLegacyControl($organization->id, [
            'code' => 'A.5.2',
            'title' => 'Rollen und Verantwortlichkeiten',
            'source' => 'iso27001AnnexA',
            'applicable' => 1,
            'implementation_status' => 'implemented',
            'owner_user_id' => $owner->id,
        ]);

        // 4) Katalogeintrag mit Risiko-Verknüpfung → bleibt als Maßnahme.
        $riskId = (int) DB::table('isms_risks')->insertGetId([
            'organization_id' => $organization->id,
            'risk_no' => 1,
            'title' => 'Ransomware',
            'category' => 'technical',
            'likelihood' => 4,
            'impact' => 5,
            'score' => 20,
            'treatment' => 'mitigate',
            'status' => 'identified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $linkedCatalogId = $this->insertLegacyControl($organization->id, [
            'code' => 'A.8.7',
            'title' => 'Schutz gegen Schadsoftware',
            'source' => 'iso27001AnnexA',
            'applicable' => 1,
            'implementation_status' => 'partial',
        ]);
        DB::table('isms_control_risk')->insert(['control_id' => $linkedCatalogId, 'risk_id' => $riskId]);

        // 5) Eigene Maßnahme → Requirement (norm 'Eigene') + Statement,
        //    Zeile bleibt als Maßnahme + Mapping.
        $customId = $this->insertLegacyControl($organization->id, [
            'code' => 'M-07',
            'title' => 'Notfallhandbuch pflegen',
            'source' => 'custom',
            'applicable' => 1,
            'implementation_status' => 'open',
        ]);

        $this->runMigration('2026_06_11_090500_migrate_isms_controls_to_requirements.php');

        // Default-Scope + Risiko-Backfill
        $scopeId = DB::table('isms_scopes')
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->value('id');
        $this->assertNotNull($scopeId, 'Default-Scope wurde angelegt');
        $this->assertSame(
            (int) $scopeId,
            (int) DB::table('isms_risks')->where('id', $riskId)->value('isms_scope_id'),
            'Bestandsrisiko wurde auf den Default-Scope gebackfillt',
        );

        // Requirements: 4 Katalog + 1 eigene
        $this->assertSame(4, DB::table('isms_requirements')
            ->where('organization_id', $organization->id)
            ->where('norm', 'ISO/IEC 27001')->where('edition', '2022')->where('source', 'catalog')
            ->count());
        $this->assertDatabaseHas('isms_requirements', [
            'organization_id' => $organization->id,
            'norm' => 'Eigene',
            'edition' => '-',
            'ref_no' => 'M-07',
            'source' => 'custom',
        ]);

        // Statements übernehmen die SoA-Felder.
        $this->assertSame(5, DB::table('isms_applicability_statements')->where('isms_scope_id', $scopeId)->count());
        $a84 = DB::table('isms_applicability_statements')
            ->join('isms_requirements', 'isms_requirements.id', '=', 'isms_applicability_statements.isms_requirement_id')
            ->where('isms_requirements.ref_no', 'A.8.4')
            ->select('isms_applicability_statements.*')
            ->first();
        $this->assertNotNull($a84);
        $this->assertSame(0, (int) $a84->applicable);
        $this->assertSame('Kein eigener Quellcode.', $a84->justification);
        $this->assertSame('notApplicable', $a84->implementation_status);

        $a51 = DB::table('isms_applicability_statements')
            ->join('isms_requirements', 'isms_requirements.id', '=', 'isms_applicability_statements.isms_requirement_id')
            ->where('isms_requirements.ref_no', 'A.5.1')
            ->select('isms_applicability_statements.*')
            ->first();
        $this->assertNotNull($a51);
        $this->assertSame('partial', $a51->implementation_status);
        $this->assertSame('Richtlinie v2 im DMS', $a51->evidence_note);

        // Control-Zeilen: reine Katalogeinträge hart gelöscht, Eigenanteil bleibt.
        $this->assertDatabaseMissing('isms_controls', ['id' => $plainCatalogId]);
        $this->assertDatabaseHas('isms_controls', ['id' => $ownedCatalogId, 'owner_user_id' => $owner->id]);
        $this->assertDatabaseHas('isms_controls', ['id' => $linkedCatalogId]);
        $this->assertDatabaseHas('isms_controls', ['id' => $customId, 'title' => 'Notfallhandbuch pflegen']);

        // Mappings auf die jeweiligen Requirements.
        foreach ([['A.5.2', $ownedCatalogId], ['A.8.7', $linkedCatalogId], ['M-07', $customId]] as [$refNo, $controlId]) {
            $requirementId = DB::table('isms_requirements')
                ->where('organization_id', $organization->id)
                ->where('ref_no', $refNo)
                ->value('id');
            $this->assertTrue(
                DB::table('isms_control_requirement')
                    ->where('control_id', $controlId)
                    ->where('requirement_id', $requirementId)
                    ->exists(),
                "Mapping {$refNo} → Control {$controlId} fehlt",
            );
        }

        // Risiko-Verknüpfung der Maßnahme bleibt erhalten.
        $this->assertDatabaseHas('isms_control_risk', ['control_id' => $linkedCatalogId, 'risk_id' => $riskId]);

        // Idempotenz: zweiter Lauf ändert nichts.
        $this->runMigration('2026_06_11_090500_migrate_isms_controls_to_requirements.php');
        $this->assertSame(5, DB::table('isms_requirements')->where('organization_id', $organization->id)->count());
        $this->assertSame(5, DB::table('isms_applicability_statements')->where('isms_scope_id', $scopeId)->count());

        // Abschluss: Spalten-Drop läuft auch im Replay durch (Guards).
        $this->runMigration('2026_06_11_090600_drop_soa_columns_from_isms_controls.php');
        $this->assertFalse(Schema::hasColumn('isms_controls', 'code'));
        $this->assertFalse(Schema::hasColumn('isms_controls', 'source'));
        $this->assertFalse(Schema::hasColumn('isms_controls', 'applicable'));
        $this->assertFalse(Schema::hasColumn('isms_controls', 'justification'));
    }

    public function test_data_migration_is_a_noop_without_legacy_columns(): void {
        // Auf dem fertig migrierten Schema (ohne Alt-Spalten) ist die
        // Datenmigration ein No-Op — genau der Fall „leere Test-DB".
        $this->runMigration('2026_06_11_090500_migrate_isms_controls_to_requirements.php');

        $this->assertSame(0, DB::table('isms_scopes')->count());
        $this->assertSame(0, DB::table('isms_requirements')->count());
    }

    /** Legacy-Spalten (Stand 2026_06_10_190100) auf dem neuen Schema re-erzeugen. */
    private function recreateLegacyColumns(): void {
        Schema::table('isms_controls', function ($table): void {
            $table->string('code', 24)->nullable();
            $table->string('source', 24)->default('custom');
            $table->boolean('applicable')->default(true);
            $table->text('justification')->nullable();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertLegacyControl(int $organizationId, array $attributes): int {
        return (int) DB::table('isms_controls')->insertGetId([
            'organization_id' => $organizationId,
            'description' => null,
            'evidence_note' => null,
            'owner_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }

    private function runMigration(string $filename): void {
        /** @var Migration $migration */
        $migration = require database_path('migrations/' . $filename);
        $migration->up();
    }
}
