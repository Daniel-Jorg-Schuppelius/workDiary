<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileInstallerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Models\Audit\AuditLog;
use App\Models\Classification\{Classification, ClassificationRequirement, Tag};
use App\Models\Platform\{Organization, User};
use App\Models\{ProcedureStepDef, ProcedureTemplate, RoomRequirementTemplate, Software};
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchProfileInstallerTest extends TestCase {
    use RefreshDatabase;

    private BranchProfileInstaller $installer;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();

        $this->installer = new BranchProfileInstaller;
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_install_it_profile_creates_classifications_requirements_and_tags(): void {
        $result = $this->installer->install($this->org, 'it', $this->actor);

        $this->assertSame('it', $result['profile_code']);
        // Version aus der Profildatei statt hart kodiert — Profil-Updates
        // (z. B. v2 durch Feature 100 Entsorgung) brechen den Test sonst.
        $profile = require database_path('data/branchprofiles/it.php');
        $this->assertSame((int) $profile['version'], $result['version']);

        $this->assertGreaterThan(0, Classification::query()->where('organization_id', $this->org->id)->count());
        $this->assertGreaterThan(0, ClassificationRequirement::query()->where('organization_id', $this->org->id)->count());
        $this->assertGreaterThan(0, Tag::query()->count());

        $audit = AuditLog::query()->where('event', 'branch_profile.installed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->org->id, $audit->organization_id);
        $this->assertSame($this->actor->id, $audit->user_id);
    }

    public function test_install_is_idempotent_without_force(): void {
        $first = $this->installer->install($this->org, 'it', $this->actor);
        $second = $this->installer->install($this->org, 'it', $this->actor);

        $this->assertGreaterThan(0, $first['created']['classifications']);
        $this->assertSame(0, $second['created']['classifications']);
        $this->assertGreaterThan(0, $second['skipped']['classifications']);
        $this->assertSame(0, $second['updated']['classifications']);
    }

    public function test_install_with_force_updates_existing_entries(): void {
        $this->installer->install($this->org, 'it', $this->actor);

        $classification = Classification::query()
            ->where('organization_id', $this->org->id)
            ->where('domain', 'entry_type')
            ->where('code', 'incident')
            ->firstOrFail();

        $classification->update(['label' => 'Incident MANUELL']);

        $result = $this->installer->install($this->org, 'it', $this->actor, true);

        $this->assertGreaterThan(0, $result['updated']['classifications']);

        $classification->refresh();
        $this->assertSame('Incident', $classification->label);
    }

    public function test_install_it_profile_seeds_software_idempotent(): void {
        $first = $this->installer->install($this->org, 'it', $this->actor);
        $this->assertGreaterThan(0, $first['created']['software']);

        $this->assertGreaterThan(0, Software::query()
            ->where('organization_id', $this->org->id)
            ->where('kind', 'operating_system')
            ->count());
        $this->assertGreaterThan(0, Software::query()
            ->where('organization_id', $this->org->id)
            ->where('kind', 'application')
            ->count());

        $second = $this->installer->install($this->org, 'it', $this->actor);
        $this->assertSame(0, $second['created']['software']);
        $this->assertGreaterThan(0, $second['skipped']['software']);
    }

    /** Vollaudit 2026-07 (N13): Qualifikations-Seeds je Gewerk, idempotent. */
    public function test_install_elektro_profile_seeds_qualifications_idempotent(): void {
        $first = $this->installer->install($this->org, 'elektro', $this->actor);
        $this->assertGreaterThan(0, $first['created']['qualifications']);

        $this->assertDatabaseHas('qualifications', [
            'organization_id' => $this->org->id,
            'abbreviation' => 'DGUV V3',
        ]);

        $second = $this->installer->install($this->org, 'elektro', $this->actor);
        $this->assertSame(0, $second['created']['qualifications']);
        $this->assertGreaterThan(0, $second['skipped']['qualifications']);
    }

    public function test_install_handwerk_profile_creates_expected_domain_entries(): void {
        $result = $this->installer->install($this->org, 'handwerk', $this->actor);

        $this->assertSame('handwerk', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'aufmass',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'repair',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_handwerk_profile_is_idempotent_without_force(): void {
        $first = $this->installer->install($this->org, 'handwerk', $this->actor);
        $second = $this->installer->install($this->org, 'handwerk', $this->actor);

        $this->assertGreaterThan(0, $first['created']['classifications']);
        $this->assertSame(0, $second['created']['classifications']);
        $this->assertGreaterThan(0, $second['skipped']['classifications']);
    }

    public function test_install_elektro_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'elektro', $this->actor);

        $this->assertSame('elektro', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'eCheck',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'stoerung',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_shk_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'shk', $this->actor);

        $this->assertSame('shk', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'druckpruefung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'wartung',
            'required_domain' => 'product_group',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_spedition_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'spedition', $this->actor);

        $this->assertSame('spedition', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'transportauftrag',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'schaden',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_steuerberater_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'steuerberater', $this->actor);

        $this->assertSame('steuerberater', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'steuererklaerung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'voranmeldung',
            'required_domain' => 'result',
            'enforce_phase' => 'beforeComplete',
        ]);
    }

    public function test_install_veranstaltungstechnik_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'veranstaltungstechnik', $this->actor);

        $this->assertSame('veranstaltungstechnik', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'safetyCheck',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'schaden',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_bau_ausbau_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'bau-ausbau', $this->actor);

        $this->assertSame('bau-ausbau', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'bautagesbericht',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'mangel',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_galabau_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'galabau', $this->actor);

        $this->assertSame('galabau', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'winterdienst',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'abnahme',
            'required_domain' => 'result',
            'enforce_phase' => 'beforeComplete',
        ]);
    }

    public function test_install_gebaeudereinigung_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'gebaeudereinigung', $this->actor);

        $this->assertSame('gebaeudereinigung', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'unterhaltsreinigung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'reklamation',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_facility_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'facility', $this->actor);

        $this->assertSame('facility', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'objektkontrolle',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'schluessel',
            'required_domain' => 'activity',
            'enforce_phase' => 'onCreate',
        ]);
    }

    public function test_install_facility_and_cleaning_profiles_publish_reference_procedures(): void {
        // MVP-034: Facility und Gebäudereinigung sind vollwertige Referenz-
        // profile — ihre deklarativen Prozedurvorlagen werden veröffentlicht.
        foreach (['facility' => 'FM_OBJEKTKONTROLLE', 'gebaeudereinigung' => 'GR_QS_KONTROLLE'] as $profile => $code) {
            $org = Organization::factory()->create();
            $result = $this->installer->install($org, $profile, $this->actor);

            $this->assertSame($profile, $result['profile_code']);
            $this->assertGreaterThan(0, $result['created']['procedure_templates']);

            $template = ProcedureTemplate::query()
                ->where('organization_id', $org->id)
                ->where('code', $code)
                ->first();
            $this->assertNotNull($template, "Referenz-Prozedur {$code} fehlt im Profil {$profile}.");

            $version = $template->versions()->firstOrFail();
            $this->assertTrue($version->isPublished());
            $this->assertGreaterThan(0, ProcedureStepDef::query()
                ->where('procedure_template_version_id', $version->id)
                ->count());
        }
    }

    public function test_install_partyservice_profile_creates_expected_entries_and_publishes_haccp_procedure(): void {
        $result = $this->installer->install($this->org, 'partyservice', $this->actor);

        $this->assertSame('partyservice', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);
        $this->assertGreaterThan(0, $result['created']['procedure_templates']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'menueplanung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'reklamation',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);

        $template = ProcedureTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'PS_HACCP_KUEHLKETTE')
            ->first();
        $this->assertNotNull($template);

        $version = $template->versions()->firstOrFail();
        $this->assertTrue($version->isPublished());
        $this->assertGreaterThan(0, ProcedureStepDef::query()
            ->where('procedure_template_version_id', $version->id)
            ->count());
    }

    public function test_install_veranstalter_profile_creates_expected_entries_and_requires_second_person(): void {
        $result = $this->installer->install($this->org, 'veranstalter', $this->actor);

        $this->assertSame('veranstalter', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);
        $this->assertGreaterThan(0, $result['created']['procedure_templates']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'durchfuehrung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'zwischenfall',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);

        // Das Sicherheitskonzept wird im Vier-Augen-Prinzip freigegeben.
        $template = ProcedureTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'VA_SICHERHEITSKONZEPT')
            ->first();
        $this->assertNotNull($template);

        $version = $template->versions()->firstOrFail();
        $this->assertTrue($version->isPublished());
        $this->assertGreaterThan(0, ProcedureStepDef::query()
            ->where('procedure_template_version_id', $version->id)
            ->where('requires_second_person', true)
            ->count());
    }

    public function test_install_pflege_profile_creates_expected_entries_and_publishes_five_r_procedure(): void {
        $result = $this->installer->install($this->org, 'pflege', $this->actor);

        $this->assertSame('pflege', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);
        $this->assertGreaterThan(0, $result['created']['procedure_templates']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'behandlungspflege',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'behandlungspflege',
            'required_domain' => 'activity',
            'enforce_phase' => 'onCreate',
        ]);

        // Die Medikamentengabe (5-R) wird veröffentlicht; die BtM-Kontrolle
        // erzwingt eine zweite Person (Vier-Augen-Prinzip).
        $template = ProcedureTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'PF_MEDIKAMENTENGABE')
            ->first();
        $this->assertNotNull($template);

        $version = $template->versions()->firstOrFail();
        $this->assertTrue($version->isPublished());
        $this->assertGreaterThan(0, ProcedureStepDef::query()
            ->where('procedure_template_version_id', $version->id)
            ->where('requires_second_person', true)
            ->count());
    }

    public function test_install_facility_profile_creates_maintenance_plan_templates(): void {
        $result = $this->installer->install($this->org, 'facility', $this->actor);

        $this->assertGreaterThan(0, $result['created']['maintenance_plan_templates']);
        $this->assertDatabaseHas('maintenance_plan_templates', [
            'organization_id' => $this->org->id,
            'code' => 'FM-LEITER-12M',
        ]);

        $second = $this->installer->install($this->org, 'facility', $this->actor);
        $this->assertSame(0, $second['created']['maintenance_plan_templates']);
        $this->assertGreaterThan(0, $second['skipped']['maintenance_plan_templates']);
    }

    public function test_install_elektro_profile_creates_published_procedure_templates_with_steps(): void {
        $result = $this->installer->install($this->org, 'elektro', $this->actor);

        $this->assertGreaterThan(0, $result['created']['procedure_templates']);

        $template = ProcedureTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'EL_SICHERHEITSCHECK')
            ->first();
        $this->assertNotNull($template);

        $version = $template->versions()->firstOrFail();
        $this->assertTrue($version->isPublished());
        $this->assertGreaterThan(0, ProcedureStepDef::query()
            ->where('procedure_template_version_id', $version->id)
            ->count());

        // Eine der Vorlagen erzwingt eine zweite Person (Spannungsfreiheit).
        $this->assertGreaterThan(0, ProcedureStepDef::query()
            ->where('procedure_template_version_id', $version->id)
            ->where('requires_second_person', true)
            ->count());
    }

    public function test_install_procedure_templates_is_idempotent_and_preserves_published_versions(): void {
        $this->installer->install($this->org, 'elektro', $this->actor);

        $template = ProcedureTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'EL_SICHERHEITSCHECK')
            ->firstOrFail();
        $versionId = $template->versions()->firstOrFail()->id;
        $templateCount = ProcedureTemplate::query()->where('organization_id', $this->org->id)->count();

        // Erneutes Installieren – auch mit force – darf veröffentlichte
        // Checklisten weder duplizieren noch überschreiben.
        $second = $this->installer->install($this->org, 'elektro', $this->actor, true);

        $this->assertSame(0, $second['created']['procedure_templates']);
        $this->assertGreaterThan(0, $second['skipped']['procedure_templates']);
        $this->assertSame($templateCount, ProcedureTemplate::query()->where('organization_id', $this->org->id)->count());
        $this->assertSame($versionId, $template->fresh()?->versions()->firstOrFail()->id);
    }

    public function test_install_seeds_room_requirement_templates_idempotent(): void {
        $first = $this->installer->install($this->org, 'gebaeudereinigung', $this->actor);

        $this->assertGreaterThan(0, $first['created']['room_requirement_templates']);
        $this->assertDatabaseHas('room_requirement_templates', [
            'organization_id' => $this->org->id,
            'code' => 'gr_hygiene',
            'kind' => 'hygieneLevel',
        ]);

        $second = $this->installer->install($this->org, 'gebaeudereinigung', $this->actor);
        $this->assertSame(0, $second['created']['room_requirement_templates']);
        $this->assertGreaterThan(0, $second['skipped']['room_requirement_templates']);

        $this->assertSame(
            $first['created']['room_requirement_templates'],
            RoomRequirementTemplate::query()->where('organization_id', $this->org->id)->count(),
        );
    }

    /**
     * Struktur-Gate (MVP-342): JEDES Profil unter database/data/branchprofiles
     * lädt fehlerfrei und liefert die Pflichtstruktur (code/label/version,
     * mindestens Eintragstypen) — neue Profile werden per glob automatisch
     * mitgeprüft, der Wizard listet sie ohne Code-Änderung.
     */
    public function test_every_branch_profile_file_is_loadable_and_well_formed(): void {
        $files = glob(database_path('data/branchprofiles/*.php')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $profile = require $file;

            $this->assertIsArray($profile, "$file liefert kein Array.");
            $this->assertNotSame('', (string) ($profile['code'] ?? ''), "$file ohne code.");
            $this->assertSame(pathinfo($file, PATHINFO_FILENAME), (string) $profile['code'], "$file: code ≠ Dateiname.");
            $this->assertNotSame('', (string) ($profile['label'] ?? ''), "$file ohne label.");
            $this->assertGreaterThanOrEqual(1, (int) ($profile['version'] ?? 0), "$file ohne version.");
            $this->assertNotEmpty((array) (($profile['classifications'] ?? [])['entry_type'] ?? []), "$file ohne entry_type-Klassifikationen.");
        }
    }

    public function test_install_anlagenwartung_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'anlagenwartung', $this->actor);

        $this->assertSame('anlagenwartung', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);
        $this->assertGreaterThan(0, $result['created']['procedure_templates']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'kalibrierung',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'stoerung',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);

        $this->assertDatabaseHas('procedure_templates', [
            'organization_id' => $this->org->id,
            'code' => 'AW_WARTUNG',
        ]);
    }

    public function test_install_kfz_fuhrparkservice_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'kfz-fuhrparkservice', $this->actor);

        $this->assertSame('kfz-fuhrparkservice', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'reifenwechsel',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'schaden',
            'required_domain' => 'defect_type',
            'enforce_phase' => 'onCreate',
        ]);

        $this->assertDatabaseHas('procedure_templates', [
            'organization_id' => $this->org->id,
            'code' => 'KFZ_UEBERGABE',
        ]);
    }

    public function test_install_sicherheitsdienst_profile_creates_expected_entries(): void {
        $result = $this->installer->install($this->org, 'sicherheitsdienst', $this->actor);

        $this->assertSame('sicherheitsdienst', $result['profile_code']);
        $this->assertGreaterThan(0, $result['created']['classifications']);

        $this->assertDatabaseHas('classifications', [
            'organization_id' => $this->org->id,
            'domain' => 'entry_type',
            'code' => 'revierfahrt',
        ]);

        $this->assertDatabaseHas('classification_requirements', [
            'organization_id' => $this->org->id,
            'entry_type_code' => 'alarm',
            'required_domain' => 'priority',
            'enforce_phase' => 'onCreate',
        ]);

        $this->assertDatabaseHas('procedure_templates', [
            'organization_id' => $this->org->id,
            'code' => 'SD_ALARMVERFOLGUNG',
        ]);
    }

    public function test_force_install_does_not_overwrite_customised_room_requirement_template(): void {
        $this->installer->install($this->org, 'gebaeudereinigung', $this->actor);

        $template = RoomRequirementTemplate::query()
            ->where('organization_id', $this->org->id)
            ->where('code', 'gr_hygiene')
            ->firstOrFail();
        $template->update(['label' => 'Hygiene LOKAL']);

        // Ohne force bleibt die lokale Anpassung erhalten.
        $this->installer->install($this->org, 'gebaeudereinigung', $this->actor);
        $this->assertSame('Hygiene LOKAL', $template->fresh()?->label);

        // Mit force wird die Vorlage auf den Profilstand zurückgesetzt.
        $forced = $this->installer->install($this->org, 'gebaeudereinigung', $this->actor, true);
        $this->assertGreaterThan(0, $forced['updated']['room_requirement_templates']);
        $this->assertNotSame('Hygiene LOKAL', $template->fresh()?->label);
    }

    /** MVP-839: Das zuerst installierte Profil bleibt Hauptprofil, weitere Installationen registrieren nur. */
    public function test_second_profile_registers_without_changing_the_primary(): void {
        $this->installer->install($this->org, 'it', $this->actor);
        $this->installer->install($this->org, 'handwerk', $this->actor);

        $org = $this->org->refresh();
        $this->assertSame('it', $org->primaryBranchProfileCode());
        $this->assertSame(['it', 'handwerk'], $org->installedBranchProfileCodes());

        $this->installer->setPrimary($org, 'handwerk', $this->actor);
        $org->refresh();
        $this->assertSame('handwerk', $org->primaryBranchProfileCode());
        $this->assertSame(['handwerk', 'it'], $org->installedBranchProfileCodes());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'branch_profile.primaryChanged',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->installer->setPrimary($org, 'shk', $this->actor);
    }

    /** MVP-839 (P12-09): Uninstall entfernt nur Unbenutztes und behält Vorlagen. */
    public function test_uninstall_removes_unused_deactivates_referenced_and_keeps_templates(): void {
        $this->installer->install($this->org, 'it', $this->actor);
        $this->installer->install($this->org, 'handwerk', $this->actor);

        // Eine Handwerk-Klassifikation ist in Gebrauch (Pivot), eine andere nicht.
        $used = Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', 'entry_type')->where('code', 'repair')->firstOrFail();
        // Kunde trägt die Klassifikation (HasClassifications-Pivot), Auftrag den Tag.
        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->actor->id]);
        $customer->classifications()->attach($used->id);
        $entry = \App\Models\DiaryEntry::factory()->create(['organization_id' => $this->org->id, 'user_id' => $this->actor->id]);
        $usedTag = Tag::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('name', '#wartung')->firstOrFail();
        $entry->tags()->attach($usedTag->id);

        $proceduresBefore = \App\Models\ProcedureTemplate::query()->where('organization_id', $this->org->id)->count();
        $requirementsBefore = ClassificationRequirement::query()->where('organization_id', $this->org->id)->count();

        $result = $this->installer->uninstall($this->org, 'handwerk', $this->actor);

        $this->assertSame('handwerk', $result['profile_code']);
        $this->assertSame(1, $result['deactivated']['classifications']);
        $this->assertGreaterThan(0, $result['removed']['classifications']);
        $this->assertGreaterThan(0, $result['removed']['classification_requirements']);
        $this->assertGreaterThan(0, $result['removed']['tags']);
        $this->assertContains('procedure_templates', $result['kept']);
        $this->assertContains('tags_in_use', $result['kept']);
        $this->assertSame('it', $result['primary']);

        // Benutzte Klassifikation bleibt, aber deaktiviert; unbenutzte weg.
        $this->assertFalse((bool) $used->fresh()?->active);
        $this->assertNotNull($used->fresh()?->deprecated_at);
        $this->assertNull(Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', 'entry_type')->where('code', 'aufmass')->first());
        // Benutzter Tag bleibt, unbenutzter weg.
        $this->assertNotNull($usedTag->fresh());
        $this->assertNull(Tag::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->where('name', '#notdienst')->first());
        // Vorlagen bleiben; Pflichtregeln des Profils sind weg, die des IT-Profils nicht.
        $this->assertSame($proceduresBefore, \App\Models\ProcedureTemplate::query()->where('organization_id', $this->org->id)->count());
        $this->assertLessThan($requirementsBefore, ClassificationRequirement::query()->where('organization_id', $this->org->id)->count());
        $this->assertGreaterThan(0, ClassificationRequirement::query()->where('organization_id', $this->org->id)->count());

        // IT-Klassifikationen unberührt.
        $this->assertTrue(Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', 'entry_type')->where('code', 'incident')->where('active', true)->exists());

        $org = $this->org->refresh();
        $this->assertSame(['it'], $org->installedBranchProfileCodes());
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event' => 'branch_profile.uninstalled']);
    }

    /** MVP-839: Wird das Hauptprofil deinstalliert, rückt das nächste installierte nach. */
    public function test_uninstalling_the_primary_promotes_the_next_installed_profile(): void {
        $this->installer->install($this->org, 'it', $this->actor);
        $this->installer->install($this->org, 'shk', $this->actor);

        $result = $this->installer->uninstall($this->org, 'it', $this->actor);
        $this->assertSame('shk', $result['primary']);
        $this->assertSame('shk', $this->org->refresh()->primaryBranchProfileCode());

        $result = $this->installer->uninstall($this->org->refresh(), 'shk', $this->actor);
        $this->assertNull($result['primary']);
        $this->assertSame([], $this->org->refresh()->installedBranchProfileCodes());
        $this->assertNull($this->org->refresh()->primaryBranchProfileCode());
    }

    /** MVP-839: Die Modul-Empfehlung ist die Vereinigung aller installierten Profile. */
    public function test_module_recommendation_unions_all_installed_profiles(): void {
        $this->installer->install($this->org, 'it', $this->actor);
        $this->installer->install($this->org, 'partyservice', $this->actor);

        $recommendation = app(\App\Services\Licensing\ModuleScopeService::class)->branchProfileRecommendation($this->org->refresh());
        $this->assertNotNull($recommendation);
        $this->assertSame('it', $recommendation['code']);
        $this->assertStringContainsString('IT-Service', $recommendation['label']);
        $this->assertStringContainsString('Partyservice', $recommendation['label']);
        $this->assertContains('module.helpdesk', $recommendation['modules']);
        $this->assertContains('module.rental', $recommendation['modules']);
        $this->assertSame(count($recommendation['modules']), count(array_unique($recommendation['modules'])));
    }

    /**
     * Gate (MVP-841): Jedes Profil hat eine Übersetzungs-Beilage
     * `i18n/<code>.php`, jede Zeile darin zeigt auf einen Code des Profils,
     * und jede Auftragsart trägt en/es/fr/it.
     */
    public function test_every_profile_ships_entry_type_translations(): void {
        $files = glob(database_path('data/branchprofiles/*.php')) ?: [];
        $this->assertNotEmpty($files);
        $violations = [];
        foreach ($files as $file) {
            $code = basename($file, '.php');
            $profile = require $file;
            $sidecarFile = database_path("data/branchprofiles/i18n/{$code}.php");
            if (! is_file($sidecarFile)) {
                $violations[] = "{$code}: Beilage i18n/{$code}.php fehlt";

                continue;
            }
            $sidecar = require $sidecarFile;
            $domains = (array) ($profile['classifications'] ?? []);
            foreach ($sidecar as $domain => $rows) {
                $codes = array_column((array) ($domains[$domain] ?? []), 'code');
                foreach ($rows as $c => $langs) {
                    if (! in_array($c, $codes, true)) {
                        $violations[] = "{$code}: {$domain}.{$c} gibt es im Profil nicht";
                    }
                    foreach (['en', 'es', 'fr', 'it'] as $lang) {
                        if (trim((string) ($langs[$lang] ?? '')) === '') {
                            $violations[] = "{$code}: {$domain}.{$c} ohne {$lang}";
                        }
                    }
                }
            }
            foreach (array_column((array) ($domains['entry_type'] ?? []), 'code') as $c) {
                if (! isset($sidecar['entry_type'][$c])) {
                    $violations[] = "{$code}: entry_type.{$c} ohne Übersetzung";
                }
            }
        }
        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** MVP-841: Installer schreibt label_i18n, die Anzeige folgt der Sprache, das Quell-Label bleibt. */
    public function test_install_writes_label_translations_and_display_label_follows_locale(): void {
        $this->installer->install($this->org, 'it', $this->actor);

        $advice = Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', 'entry_type')->where('code', 'advice')->firstOrFail();
        $this->assertSame('Beratung', $advice->label);
        $this->assertSame('Consulting', $advice->label_i18n['en'] ?? null);
        $this->assertSame('Conseil', $advice->label_i18n['fr'] ?? null);

        $previous = app()->getLocale();
        config()->set('app.fallback_locale', 'en');
        try {
            app()->setLocale('de');
            $this->assertSame('Beratung', $advice->display_label);
            app()->setLocale('fr');
            $this->assertSame('Conseil', $advice->displayLabel());
            app()->setLocale('pt');
            $this->assertSame('Consulting', $advice->displayLabel(), 'Fallback-Sprache en');
        } finally {
            app()->setLocale($previous);
        }

        // Ohne Übersetzungen bleibt das Label, auch in Fremdsprachen.
        $advice->forceFill(['label_i18n' => null])->save();
        $this->assertSame('Beratung', $advice->fresh()?->displayLabel('en'));

        // Erneute Installation ohne force trägt fehlende Übersetzungen nach, lässt Labels in Ruhe.
        $advice->forceFill(['label' => 'Beratung lokal'])->save();
        $this->installer->install($this->org, 'it', $this->actor);
        $advice->refresh();
        $this->assertSame('Beratung lokal', $advice->label);
        $this->assertSame('Consulting', $advice->label_i18n['en'] ?? null);
    }
}
