<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoIndustriesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\{Asset, Classification, Customer, DiaryEntry, Organization, ProcedureRun, ProcedureTemplate, Tag, User};
use App\Services\Demo\{DemoBlueprintProvider, DemoSeederService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Musterbranchen 5–8 (MVP-710, Vollscan G5): Sicherheitsdienst, Bau-Ausbau,
 * Spedition, Partyservice — je Branche vollständiger Seed auf dem eigenen
 * Branchenprofil, unterscheidbare Inhalte, Determinismus, Reset-Schutz.
 */
final class DemoIndustriesTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Erwartete Profil-Spuren je neuer Branche: Auftragstyp-Klassifikation,
     * Prozedurvorlage (= Blueprint `procedure_code`), Tag, Titel-/Asset-Marker.
     *
     * @return array<string, array{DemoIndustry, string, string, string, string, string, string}>
     */
    public static function newIndustries(): array {
        return [
            'sicherheitsdienst' => [DemoIndustry::Sicherheitsdienst, 'sicherheitsdienst', 'wachbuch', 'SD_REVIERFAHRT', '#wachbuch', 'Objektschutz', 'Schließanlage'],
            'bau-ausbau' => [DemoIndustry::BauAusbau, 'bau-ausbau', 'aufmass', 'BAU_TAGESBERICHT', '#aufmass', 'Trockenbau', 'Fassadengerüst'],
            'spedition' => [DemoIndustry::Spedition, 'spedition', 'transportauftrag', 'SP_LADUNGSSICHERUNG', '#kuehlgut', 'Tour', 'Sattelzug'],
            'partyservice' => [DemoIndustry::Partyservice, 'partyservice', 'menueplanung', 'PS_HACCP_KUEHLKETTE', '#haccp', 'Buffet', 'Kühlfahrzeug'],
        ];
    }

    public function test_enum_exposes_the_four_new_industries_with_matching_profiles(): void {
        $values = array_map(static fn(DemoIndustry $i): string => $i->value, DemoIndustry::all());
        foreach (['sicherheitsdienst', 'bau-ausbau', 'spedition', 'partyservice'] as $key) {
            $this->assertContains($key, $values);
            $industry = DemoIndustry::fromKey($key);
            $this->assertSame($key, $industry->value);
            $this->assertSame($key, $industry->branchProfileCode());
            $this->assertFileExists(database_path('data/branchprofiles/' . $industry->branchProfileCode() . '.php'));
            $this->assertNotSame('', $industry->label());
            $this->assertStringContainsString('Muster', $industry->companyName());
        }
        $this->assertCount(8, DemoIndustry::all());
    }

    public function test_every_industry_blueprint_has_the_same_key_set(): void {
        $provider = new DemoBlueprintProvider();
        $reference = $this->keyShape($provider->blueprint(DemoIndustry::default()));

        foreach (DemoIndustry::all() as $industry) {
            $this->assertSame($reference, $this->keyShape($provider->blueprint($industry)), 'Blueprint-Schlüssel weichen ab: ' . $industry->value);

            $blueprint = $provider->blueprint($industry);
            $this->assertCount(3, $blueprint['customers']);
            $this->assertCount(3, $blueprint['materials']);
            $this->assertCount(3, $blueprint['main_case']['protocol_items']);
            $this->assertNotSame('', (string) $blueprint['procedure_code']);
        }
    }

    #[DataProvider('newIndustries')]
    public function test_industry_seeds_with_profile_and_is_deterministic(
        DemoIndustry $industry,
        string $profile,
        string $entryType,
        string $procedureCode,
        string $tag,
        string $titleMarker,
        string $assetMarker,
    ): void {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $service = app(DemoSeederService::class);
        $first = $service->seed($organization, $admin, $industry);

        $this->assertSame($industry->value, $first['industry']);
        $this->assertSame($profile, $first['branch_profile']);
        foreach (['customers', 'projects', 'users', 'main_diary_entries', 'background_diary_entries', 'time_entries', 'open_issues', 'materials', 'material_usages', 'assets', 'protocols', 'communication_notes', 'attachments', 'procedure_runs'] as $key) {
            $this->assertGreaterThan(0, (int) $first[$key], 'Count leer: ' . $key);
        }

        $org = $organization->refresh();
        $this->assertTrue((bool) $org->is_demo);
        $this->assertSame($industry->value, $org->settings['demo_industry'] ?? null);

        // Branchenprofil installiert: Auftragstyp, Prozedurvorlage, Tag.
        $this->assertTrue(Classification::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('domain', 'entry_type')->where('code', $entryType)->exists());
        $template = ProcedureTemplate::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('code', $procedureCode)->first();
        $this->assertNotNull($template);
        $this->assertTrue(Tag::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('name', $tag)->exists());

        // Demo-Durchlauf läuft auf der Vorlage der Branche und ist abgeschlossen.
        $run = ProcedureRun::query()->withoutGlobalScopes()->where('organization_id', $org->id)->firstOrFail();
        $this->assertSame(ProcedureRunStatus::Completed, $run->status);
        $this->assertSame($template?->id, $run->templateVersion?->template?->id);

        // Branchen-Inhalte sichtbar.
        $title = $this->mainCaseTitle($org);
        $this->assertStringContainsString($titleMarker, $title);
        $this->assertTrue(Asset::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('name', 'like', '%' . $assetMarker . '%')->exists());

        // Determinismus: Reset liefert dieselben Zahlen und Inhalte.
        $second = $service->reset($org, $admin);
        foreach (['customers', 'projects', 'background_diary_entries', 'materials', 'assets', 'protocols', 'attachments', 'procedure_runs'] as $key) {
            $this->assertSame($first[$key], $second[$key], 'Nicht deterministisch: ' . $key);
        }
        $this->assertSame($title, $this->mainCaseTitle($org->refresh()));
        $this->assertSame(1, ProcedureRun::query()->withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }

    public function test_new_industries_produce_distinct_content(): void {
        $titles = [];
        $customers = [];
        foreach (self::newIndustries() as [$industry]) {
            $organization = Organization::factory()->create();
            $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
            app(DemoSeederService::class)->seed($organization, $admin, $industry);

            $titles[] = $this->mainCaseTitle($organization);
            $customers[] = Customer::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)->orderBy('id')->pluck('name')->implode('|');
        }

        $this->assertCount(4, array_unique($titles));
        $this->assertCount(4, array_unique($customers));
    }

    public function test_partyservice_installs_allergen_catalog_from_profile(): void {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::Partyservice);

        // 14 LMIV-Hauptallergene + „keine“ kommen aus dem Profil, nicht aus dem Seeder.
        $allergens = Classification::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->where('domain', 'allergen')->pluck('code');
        $this->assertGreaterThanOrEqual(15, $allergens->count());
        $this->assertContains('gluten', $allergens->all());
        $this->assertContains('keine', $allergens->all());
        $this->assertStringContainsString('LMIV', (string) DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)->where('title', 'like', '%Beispielauftrag%')->value('content'));
    }

    public function test_reset_of_new_industry_leaves_real_org_untouched(): void {
        $demoOrg = Organization::factory()->create();
        $demoAdmin = User::factory()->admin()->create(['organization_id' => $demoOrg->id]);
        app(DemoSeederService::class)->seed($demoOrg, $demoAdmin, DemoIndustry::Spedition);

        $realOrg = Organization::factory()->create(['is_demo' => false]);
        $realAdmin = User::factory()->admin()->create(['organization_id' => $realOrg->id]);
        $realCustomer = Customer::factory()->create([
            'organization_id' => $realOrg->id,
            'created_by' => $realAdmin->id,
            'name' => 'Echtkunde Logistik AG',
        ]);

        // Reset mit Branchenwechsel — nur die Demo-Org wird angefasst.
        $counts = app(DemoSeederService::class)->reset($demoOrg, $demoAdmin, DemoIndustry::Partyservice);

        $this->assertSame('partyservice', $counts['industry']);
        $this->assertFalse(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $demoOrg->id)->where('name', 'Möbelwerk Muster GmbH')->exists());
        $this->assertDatabaseHas('customers', ['id' => $realCustomer->id, 'organization_id' => $realOrg->id, 'name' => 'Echtkunde Logistik AG']);
        $this->assertSame(1, Customer::query()->withoutGlobalScopes()->where('organization_id', $realOrg->id)->count());
        $this->assertSame(1, User::query()->withoutGlobalScopes()->where('organization_id', $realOrg->id)->count());
        $this->assertFalse((bool) $realOrg->refresh()->is_demo);
    }

    public function test_seed_command_lists_and_accepts_new_industries(): void {
        $this->artisan('demo:seed', ['--list' => true])
            ->expectsOutputToContain('sicherheitsdienst')
            ->expectsOutputToContain('partyservice')
            ->assertSuccessful();

        $org = Organization::factory()->create(['is_demo' => false]);
        User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->artisan('demo:seed', ['org' => $org->id, '--industry' => 'bau-ausbau'])->assertSuccessful();

        $this->assertSame('bau-ausbau', $org->refresh()->settings['demo_industry'] ?? null);
        $this->assertTrue(Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $org->id)->where('name', 'Bauträger Muster GmbH')->exists());
    }

    private function mainCaseTitle(Organization $organization): string {
        return (string) DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('title', 'like', '%Beispielauftrag%')
            ->orderBy('id')
            ->value('title');
    }

    /**
     * Schlüsselstruktur eines Blueprints (Listen über ihr erstes Element),
     * damit fehlende Keys je Branche sofort auffallen.
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function keyShape(array $value): array {
        if (array_is_list($value)) {
            return $value === [] ? [] : ['[]' => is_array($value[0]) ? $this->keyShape($value[0]) : '*'];
        }

        $shape = [];
        foreach ($value as $key => $item) {
            $shape[$key] = is_array($item) ? $this->keyShape($item) : '*';
        }
        ksort($shape);

        return $shape;
    }
}
