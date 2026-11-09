<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfilePageContentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementSeverity};
use App\Models\Classification\{Classification, ClassificationRequirement};
use App\Models\Contract\{Contract, ContractTemplate};
use App\Models\Platform\{Organization, User};
use App\Models\Procedure\ProcedureTemplate;
use App\Models\Protocol\ProtocolTemplate;
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Contract\{ContractService, ContractTemplateService};
use App\Services\Procedure\Install\ProcedureTemplateInstallStep;
use App\Services\Protocol\Install\ProtocolTemplateInstallStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profile nach den Profilseiten (MVP-1509): Pflege v6 und Partyservice v3
 * liefern die beschriebenen Vorlagen und Domänen; eine installierte ältere
 * Version wird überführt, ohne Codes umzubenennen.
 */
class BranchProfilePageContentTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create(['organization_id' => $this->org->id]);
    }

    public function test_pflege_installs_the_templates_and_domains_of_the_profile_page(): void {
        $result = (new BranchProfileInstaller)->install($this->org, 'pflege', $this->actor);

        $this->assertSame(6, $result['version']);
        $procedures = ['AP_TOUR_EINSATZ', 'AP_MEDIKAMENTENGABE', 'AP_BTM_GABE', 'AP_WUNDVERSORGUNG', 'AP_VITALWERTE', 'AP_STURZ_PROTOKOLL', 'AP_DEKUBITUS_RISIKO', 'AP_PFLEGEVISITE', 'AP_BERATUNGSBESUCH_37_3', 'AP_NEUAUFNAHME_KLIENT', 'AP_UEBERGABE_SCHICHT', 'AP_HYGIENE_BEGEHUNG', 'AP_QM_REVIEW'];
        $this->assertEqualsCanonicalizing($procedures, ProcedureTemplate::query()->where('organization_id', $this->org->id)->pluck('code')->all());
        $this->assertEqualsCanonicalizing(
            ['Aufnahmegespräch', 'Leistungsnachweis', 'Pflegebericht', 'Wundbericht', 'Sturzbericht', 'BtM-Buch-Eintrag', 'Pflegevisitenprotokoll', 'Beratungsbesuch nach § 37 Abs. 3 SGB XI', 'Übernahme ärztliche Anordnung', 'Vorfallmeldung Pflege'],
            ProtocolTemplate::query()->where('organization_id', $this->org->id)->pluck('name')->all(),
        );

        $this->assertSame(['pg1', 'pg2', 'pg3', 'pg4', 'pg5'], $this->codes('pflegegrad'));
        $this->assertContains('sgbXIGrundpflege', $this->codes('leistungsart'));
        $this->assertContains('telefonHausarzt', $this->codes('kommunikation'));
        $this->assertSame('Telefonat Hausärztin/Hausarzt', Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', 'kommunikation')->where('code', 'telefonHausarzt')->value('label'));
        $this->assertTrue(ClassificationRequirement::query()->where('organization_id', $this->org->id)
            ->where('entry_type_code', 'tourEinsatz')->where('required_domain', 'leistungsart')->where('enforce_phase', 'onCreate')->exists());
        $this->assertSame(ClassificationRequirementSeverity::Soft, ClassificationRequirement::query()->where('organization_id', $this->org->id)
            ->where('entry_type_code', 'vorfall')->where('required_domain', 'kommunikation')->firstOrFail()->severity);
        $this->assertNotNull($this->category('btmSchrank'));
    }

    public function test_pflege_contract_template_keeps_the_quarterly_counselling_grid_per_client(): void {
        (new BranchProfileInstaller)->install($this->org, 'pflege', $this->actor);
        $template = ContractTemplate::query()->where('organization_id', $this->org->id)
            ->where('name', 'Pflegevertrag mit Beratungsbesuch (§ 37 Abs. 3 SGB XI)')->firstOrFail();
        $contract = Contract::factory()->create(['organization_id' => $this->org->id, 'starts_on' => '2027-01-01', 'ends_on' => null]);

        app(ContractTemplateService::class)->applyObligations($template, $contract);
        $visit = $contract->obligations()->where('title', 'Beratungsbesuch nach § 37 Abs. 3 SGB XI')->firstOrFail();
        $this->assertSame('2027-04-01', $visit->due_on->toDateString());

        app(ContractService::class)->completeObligation($visit, $this->actor);
        $next = $contract->obligations()->where('title', 'Beratungsbesuch nach § 37 Abs. 3 SGB XI')->where('id', '!=', $visit->id)->firstOrFail();
        $this->assertSame('2027-07-01', $next->due_on->toDateString());
        $this->assertTrue($next->recurring);
    }

    public function test_partyservice_installs_the_templates_and_event_types_of_the_profile_page(): void {
        $result = (new BranchProfileInstaller)->install($this->org, 'partyservice', $this->actor);

        $this->assertSame(3, $result['version']);
        $this->assertEqualsCanonicalizing(
            ['PS_ANFRAGE_AUFNAHME', 'PS_ANGEBOT_ERSTELLEN', 'PS_EVENT_PLANUNG', 'PS_PRODUKTION', 'PS_LIEFERUNG', 'PS_AUFBAU_BUFFET', 'PS_BETREUUNG_EVENT', 'PS_RUECKNAHME', 'PS_REKLAMATION'],
            ProcedureTemplate::query()->where('organization_id', $this->org->id)->pluck('code')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Eventbriefing', 'Produktionsplan', 'Temperaturprotokoll Catering', 'Lieferschein Catering', 'Aufbauabnahme Catering', 'Rücknahmeprotokoll Catering', 'Reklamationsbericht Catering'],
            ProtocolTemplate::query()->where('organization_id', $this->org->id)->pluck('name')->all(),
        );
        $this->assertSame(['hochzeit', 'geburtstag', 'firmenevent', 'trauerfeier', 'vereinsfest', 'messe', 'schulung', 'privateFeier'], $this->codes('event_type'));
        $this->assertTrue(ClassificationRequirement::query()->where('organization_id', $this->org->id)
            ->where('entry_type_code', 'cateringAnfrage')->where('required_domain', 'event_type')->exists());
        $this->assertNotNull($this->category('buffettisch'));
    }

    public function test_single_codes_of_the_profile_pages_are_installed(): void {
        $installer = new BranchProfileInstaller;
        foreach (['shk', 'gebaeudereinigung', 'veranstalter', 'spedition', 'steuerberater'] as $code) {
            $installer->install($this->org, $code, $this->actor);
        }
        $protocols = ProtocolTemplate::query()->where('organization_id', $this->org->id)->pluck('name')->all();

        foreach (['Anlagenakte Heizung / Sanitär', 'Objektplan Reinigung', 'Dienstleisterliste', 'Veranstaltungskonzept'] as $name) {
            $this->assertContains($name, $protocols);
        }
        $this->assertSame(['euroPalette', 'einwegPalette', 'gitterbox', 'rollcontainer', 'behaelter', 'wechselbruecke'], $this->codes('lademittel_type'));
        $this->assertContains('mandantenportal', $this->codes('kommunikation'));
        $this->assertContains('eur', $this->codes('product_group'));
        $this->assertTrue(ClassificationRequirement::query()->where('organization_id', $this->org->id)
            ->where('entry_type_code', 'kommunikation')->where('required_domain', 'kommunikation')->exists());
    }

    public function test_update_of_an_installed_v5_pflege_profile_retires_predecessors_without_renaming(): void {
        $this->installPflegeV5State();

        $result = (new BranchProfileInstaller)->install($this->org->refresh(), 'pflege', $this->actor);

        $this->assertSame(6, data_get($this->org->refresh()->settings, 'branch_profile_versions.pflege'));
        $old = ProcedureTemplate::query()->where('organization_id', $this->org->id)->where('code', 'PF_MEDIKAMENTENGABE')->firstOrFail();
        $this->assertFalse($old->active);
        $this->assertTrue(ProcedureTemplate::query()->where('organization_id', $this->org->id)->where('code', 'AP_MEDIKAMENTENGABE')->value('active'));

        $vitalzeichen = Classification::query()->where('organization_id', $this->org->id)->where('domain', 'activity')->where('code', 'vitalzeichen')->firstOrFail();
        $this->assertFalse($vitalzeichen->active);
        $this->assertNotNull($vitalzeichen->deprecated_at);
        $this->assertTrue((bool) Classification::query()->where('organization_id', $this->org->id)->where('domain', 'activity')->where('code', 'vitalwerte')->value('active'));
        $this->assertGreaterThanOrEqual(1, $result['updated']['classifications']);

        $this->assertFalse((bool) ProtocolTemplate::query()->where('organization_id', $this->org->id)->where('name', 'Wunddokumentation')->value('is_active'));
        $this->assertTrue((bool) ProtocolTemplate::query()->where('organization_id', $this->org->id)->where('name', 'Wundbericht')->value('is_active'));
        $this->assertSame(1, ProtocolTemplate::query()->where('organization_id', $this->org->id)->where('name', 'Pflegebericht')->count(), 'gleichnamiger Nachfolger bleibt der vorhandene');
    }

    public function test_reactivated_predecessor_stays_active_when_the_profile_is_applied_again(): void {
        $this->installPflegeV5State();
        $installer = new BranchProfileInstaller;
        $installer->install($this->org->refresh(), 'pflege', $this->actor);

        ProcedureTemplate::query()->where('organization_id', $this->org->id)->where('code', 'PF_MEDIKAMENTENGABE')->firstOrFail()->update(['active' => true]);
        Classification::query()->where('organization_id', $this->org->id)->where('code', 'vitalzeichen')->firstOrFail()->update(['active' => true, 'deprecated_at' => null]);

        $installer->install($this->org->refresh(), 'pflege', $this->actor, force: true);

        $this->assertTrue((bool) ProcedureTemplate::query()->where('organization_id', $this->org->id)->where('code', 'PF_MEDIKAMENTENGABE')->value('active'));
        $this->assertTrue((bool) Classification::query()->where('organization_id', $this->org->id)->where('code', 'vitalzeichen')->value('active'));
    }

    /** Stand einer Organisation mit installiertem Pflegeprofil v5 (vor MVP-1509). */
    private function installPflegeV5State(): void {
        app(ProcedureTemplateInstallStep::class)->install($this->org, [[
            'code' => 'PF_MEDIKAMENTENGABE',
            'name' => 'Medikamentengabe (5-R-Regel)',
            'domain' => 'pflege',
            'steps' => [['code' => 'klient', 'step_type' => 'confirm', 'label' => 'Richtigen Klienten identifizieren']],
        ]], $this->actor);
        app(ProtocolTemplateInstallStep::class)->install($this->org, [['code' => 'PF_WUNDDOKUMENTATION'], ['code' => 'PF_PFLEGEBERICHT']], $this->actor);
        Classification::query()->create([
            'organization_id' => $this->org->id,
            'domain' => ClassificationDomain::Activity->value,
            'code' => 'vitalzeichen',
            'label' => 'Vitalzeichenkontrolle',
            'active' => true,
        ]);
        $this->org->forceFill(['settings' => ['branch_profile_code' => 'pflege', 'branch_profile_versions' => ['pflege' => 5]]])->save();
    }

    /** @return list<string> */
    private function codes(string $domain): array {
        return array_values(array_map('strval', Classification::query()->where('organization_id', $this->org->id)->where('domain', $domain)
            ->orderBy('sort_order')->pluck('code')->all()));
    }

    private function category(string $code): ?Classification {
        return Classification::query()->where('organization_id', $this->org->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)->where('code', $code)->first();
    }
}
