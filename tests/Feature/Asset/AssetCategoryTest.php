<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCategoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\{AssetClass, AssetStatus};
use App\Enums\Classification\ClassificationDomain;
use App\Enums\User\UserRole;
use App\Exceptions\ClassificationValidationException;
use App\Models\Asset\Asset;
use App\Models\Classification\Classification;
use App\Services\Asset\AssetCategoryCatalog;
use App\Services\Classification\{BranchProfileInstaller, ClassificationManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Branchen-Asset-Kategorien je Organisation (MVP-1508): das Branchenprofil legt
 * seine Kategorien als Klassifikationen `asset_category` an; Formular und
 * Validierung lesen sie zusätzlich zum globalen Pool.
 */
class AssetCategoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_profile_install_creates_translated_org_categories_but_not_pool_codes(): void {
        $result = (new BranchProfileInstaller)->install($this->organization, 'shk');

        $this->assertSame(8, $result['created']['asset_categories']);
        $category = $this->category('pressmaschine');
        $this->assertSame('Pressmaschine', $category->label);
        $this->assertSame('Press tool', $category->label_i18n['en'] ?? null);
        $this->assertTrue($category->active);

        $it = (new BranchProfileInstaller)->install($this->organization, 'it');
        $this->assertSame(0, $it['created']['asset_categories'], 'Pool-Codes wie „server“ gibt es schon');
        $this->assertNull(Classification::query()->where('organization_id', $this->organization->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)->where('code', 'server')->first());
    }

    public function test_reinstall_never_overwrites_an_own_category(): void {
        Classification::query()->create([
            'organization_id' => $this->organization->id,
            'domain' => ClassificationDomain::AssetCategory->value,
            'code' => 'leiter',
            'label' => 'Alu-Leiter Werkstatt',
            'sort_order' => 5,
            'active' => true,
        ]);

        $first = (new BranchProfileInstaller)->install($this->organization, 'shk');
        $second = (new BranchProfileInstaller)->install($this->organization, 'shk', force: true);

        $this->assertSame(7, $first['created']['asset_categories']);
        $this->assertSame(0, $second['created']['asset_categories']);
        $this->assertSame('Alu-Leiter Werkstatt', $this->category('leiter')->label);
        $this->assertSame(1, Classification::query()->where('organization_id', $this->organization->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)->where('code', 'leiter')->count());
    }

    public function test_form_offers_branch_category_and_validation_accepts_it(): void {
        (new BranchProfileInstaller)->install($this->organization, 'shk');
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($user)->get(route('assets.create'))
            ->assertOk()
            ->assertSee('<option value="pressmaschine"', false)
            ->assertSee('Pressmaschine')
            ->assertSee('<option value="server"', false)
            ->assertSee('Branchenkategorien bringt das Branchenprofil mit');

        $this->actingAs($user)->post(route('assets.store'), $this->payload('pressmaschine'))
            ->assertSessionHasNoErrors();
        $this->assertSame('pressmaschine', Asset::query()->where('name', 'Pressmaschine Werkstatt')->value('category_code'));

        $this->actingAs($user)->post(route('assets.store'), $this->payload('erfundeneKategorie'))
            ->assertSessionHasErrors('category_code');
    }

    public function test_deactivated_category_disappears_but_stays_on_the_asset_using_it(): void {
        (new BranchProfileInstaller)->install($this->organization, 'shk');
        $user = $this->userWithRole(UserRole::Teamleitung->value);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Machine->value,
            'status' => AssetStatus::Active->value,
            'category_code' => 'pressmaschine',
        ]);
        $this->category('pressmaschine')->update(['active' => false]);

        $this->actingAs($user)->get(route('assets.create'))
            ->assertOk()
            ->assertDontSee('<option value="pressmaschine"', false);
        $this->actingAs($user)->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('<option value="pressmaschine" selected', false);

        $this->actingAs($user)->put(route('assets.update', $asset), $this->payload('pressmaschine'))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('assets.store'), $this->payload('pressmaschine'))
            ->assertSessionHasErrors('category_code');
    }

    public function test_org_row_renames_or_hides_a_pool_category(): void {
        foreach ([['server', 'Rechner im Keller', true], ['vm', 'Virtuelle Maschine', false]] as [$code, $label, $active]) {
            Classification::query()->create([
                'organization_id' => $this->organization->id,
                'domain' => ClassificationDomain::AssetCategory->value,
                'code' => $code,
                'label' => $label,
                'active' => $active,
            ]);
        }

        $options = app(AssetCategoryCatalog::class)->options($this->organization->id);

        $this->assertSame('Rechner im Keller', $options['server']);
        $this->assertArrayNotHasKey('vm', $options);
        $this->assertSame('Notebook', $options['notebook']);
    }

    public function test_camel_case_pool_code_can_be_overridden_by_hand(): void {
        app(ClassificationManager::class)->createForOrganization($this->organization->id, ClassificationDomain::AssetCategory, [
            'code' => 'accessPoint',
            'label' => 'WLAN-Sender',
        ]);

        $this->assertSame('WLAN-Sender', app(AssetCategoryCatalog::class)->options($this->organization->id)['accessPoint']);

        $this->expectException(ClassificationValidationException::class);
        app(ClassificationManager::class)->createForOrganization($this->organization->id, ClassificationDomain::AssetCategory, [
            'code' => 'AccessPoint',
            'label' => 'Großbuchstabe am Anfang',
        ]);
    }

    public function test_uninstall_removes_unused_categories_and_keeps_used_own_and_shared_ones(): void {
        $installer = new BranchProfileInstaller;
        $installer->install($this->organization, 'shk');
        $installer->install($this->organization, 'elektro');
        Asset::factory()->create(['organization_id' => $this->organization->id, 'category_code' => 'rohrkamera']);
        $this->category('lecksuchgeraet')->update(['label' => 'Lecksuche Gas (eigen)']);

        $result = $installer->uninstall($this->organization->refresh(), 'shk');

        $this->assertSame(4, $result['removed']['asset_categories']);
        $this->assertContains('asset_categories_kept', $result['kept']);
        foreach (['pressmaschine', 'druckpruefpumpe', 'messgeraetAbgas', 'werkzeugkoffer'] as $unused) {
            $this->assertNull($this->findCategory($unused), $unused);
        }
        $this->assertNotNull($this->findCategory('rohrkamera'), 'benutzt');
        $this->assertNotNull($this->findCategory('lecksuchgeraet'), 'eigene Bezeichnung');
        $this->assertNotNull($this->findCategory('servicefahrzeug'), 'Elektro bringt sie weiter mit');
        $this->assertNotNull($this->findCategory('leiter'), 'Elektro bringt sie weiter mit');
    }

    public function test_own_category_from_the_classification_dialog_is_offered_in_the_asset_form(): void {
        $user = $this->userWithRole(UserRole::Teamleitung->value);

        $this->actingAs($user)->get(route('admin.classifications.create', ['domain' => ClassificationDomain::AssetCategory->value]))
            ->assertOk()
            ->assertSee('Asset-Kategorien');
        $this->actingAs($user)->post(route('admin.classifications.store'), [
            'domain' => ClassificationDomain::AssetCategory->value,
            'code' => 'hebebuehne_mobil',
            'label' => 'Mobile Hebebühne',
            'active' => '1',
        ])->assertRedirect(route('admin.classifications.index'));

        $this->actingAs($user)->get(route('assets.create'))
            ->assertOk()
            ->assertSee('<option value="hebebuehne_mobil"', false)
            ->assertSee('Mobile Hebebühne');
    }

    public function test_used_category_cannot_be_deleted(): void {
        (new BranchProfileInstaller)->install($this->organization, 'shk');
        Asset::factory()->create(['organization_id' => $this->organization->id, 'category_code' => 'rohrkamera']);

        $this->expectException(ClassificationValidationException::class);
        app(ClassificationManager::class)->delete($this->category('rohrkamera'));
    }

    public function test_catalog_labels_every_profile_category_in_all_languages(): void {
        $catalog = require database_path('data/asset_categories.php');
        $pool = (array) config('asset_categories');
        $violations = [];
        foreach (glob(database_path('data/branchprofiles/*.php')) ?: [] as $file) {
            $profile = require $file;
            foreach ((array) ($profile['asset_categories'] ?? []) as $code) {
                if (isset($pool[$code])) {
                    continue;
                }
                $entry = $catalog[$code] ?? null;
                if (! is_array($entry) || trim((string) ($entry['label'] ?? '')) === '') {
                    $violations[] = basename($file, '.php') . ": {$code} fehlt im Katalog";

                    continue;
                }
                foreach (['en', 'es', 'fr', 'it'] as $lang) {
                    if (trim((string) ($entry['label_i18n'][$lang] ?? '')) === '') {
                        $violations[] = "{$code} ohne {$lang}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** MVP-1527: Auswertung, Drilldown und Prüfrunden-Dialog zeigen die Bezeichnung statt des Profilcodes. */
    public function test_report_drilldown_and_inspection_round_label_profile_codes(): void {
        (new BranchProfileInstaller)->install($this->organization, 'shk');
        $this->category('druckpruefpumpe')->update(['active' => false]);
        $admin = $this->orgAdmin();
        foreach (['pressmaschine', 'druckpruefpumpe', 'unbekannterCode'] as $code) {
            Asset::factory()->create([
                'organization_id' => $this->organization->id,
                'asset_class' => AssetClass::Machine->value,
                'status' => AssetStatus::Active->value,
                'category_code' => $code,
            ]);
        }

        $report = $this->actingAs($admin)->get(route('reports.assets', ['group_by' => 'group']))->assertOk();
        $report->assertSee('<option value="pressmaschine" >Pressmaschine</option>', false)
            ->assertSee('<option value="druckpruefpumpe" >Druckprüfpumpe</option>', false)
            ->assertSee('<option value="unbekannterCode" >unbekannterCode</option>', false);
        $labels = array_column($report->viewData('rows'), 'label');
        sort($labels);
        $this->assertSame(['Druckprüfpumpe', 'Pressmaschine', 'unbekannterCode'], $labels, 'inaktive Kategorie behält ihre Bezeichnung');

        $this->actingAs($admin)->get(route('reports.assets.drilldown.open-issues', ['category_code' => 'pressmaschine']))->assertOk()
            ->assertViewHas('scopeLabel', 'Pressmaschine');

        $this->actingAs($admin)->get(route('asset-compliance.rounds.create'))->assertOk()
            ->assertSee('<option value="pressmaschine" >Pressmaschine</option>', false)
            ->assertDontSee('>pressmaschine<', false);
    }

    /** @return array<string, mixed> */
    private function payload(string $categoryCode): array {
        return [
            'asset_class' => AssetClass::Machine->value,
            'status' => AssetStatus::Active->value,
            'name' => 'Pressmaschine Werkstatt',
            'category_code' => $categoryCode,
        ];
    }

    private function category(string $code): Classification {
        return $this->findCategory($code) ?? $this->fail("Kategorie {$code} fehlt");
    }

    private function findCategory(string $code): ?Classification {
        return Classification::query()
            ->where('organization_id', $this->organization->id)
            ->where('domain', ClassificationDomain::AssetCategory->value)
            ->where('code', $code)
            ->first();
    }
}
