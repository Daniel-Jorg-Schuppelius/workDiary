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

use App\Models\{AuditLog, Classification, ClassificationRequirement, Organization, ProcedureStepDef, ProcedureTemplate, RoomRequirementTemplate, Software, Tag, User};
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
        $this->assertSame(1, $result['version']);

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
}
