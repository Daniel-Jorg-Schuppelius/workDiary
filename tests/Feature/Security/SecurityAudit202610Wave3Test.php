<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAudit202610Wave3Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\User\Permission;
use App\Models\Classification\Tag;
use App\Models\Finance\LiquidityScenario;
use App\Models\Platform\{Organization, User};
use App\Services\Procurement\OpenMasterdata\{OpenMasterdataProduct, OpenMasterdataProductMapper};
use App\Support\CsvExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Regressionstests zur Welle 3 des Sicherheitsaudits 2026-10-04
 * (WorkDiary-Architecture/security/sicherheitsaudit-2026-10-04-behebung.md).
 */
final class SecurityAudit202610Wave3Test extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** authz-a-7: Szenarien gelten für die ganze Organisation — Leserecht ändert sie nicht. */
    public function test_liquidity_scenarios_need_the_prepare_right_to_change(): void {
        $reader = $this->actorIn($this->organization, [Permission::AccountingLedgerView->value]);
        $scenario = LiquidityScenario::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Basis', 'created_by' => $reader->id,
        ]);

        $this->actingAs($reader)->get(route('reports.accounting.liquidity-scenarios.index'))
            ->assertOk()
            ->assertDontSee(__('accounting.reports.scenario.new'))
            ->assertDontSee('value="DELETE"', false);
        $this->actingAs($reader)->post(route('reports.accounting.liquidity-scenarios.store'), ['name' => 'Neu'])->assertForbidden();
        $this->actingAs($reader)->put(route('reports.accounting.liquidity-scenarios.update', $scenario), ['name' => 'Anders'])->assertForbidden();
        $this->actingAs($reader)->post(route('reports.accounting.liquidity-scenarios.items.store', $scenario), [
            'label' => 'Posten', 'direction' => 'out', 'amount' => '10', 'expected_on' => now()->addWeek()->toDateString(),
        ])->assertForbidden();
        $this->actingAs($reader)->delete(route('reports.accounting.liquidity-scenarios.destroy', $scenario))->assertForbidden();
        $this->assertSame('Basis', $scenario->fresh()?->name);

        $editor = $this->actorIn($this->organization, [Permission::AccountingLedgerView->value, Permission::AccountingLedgerPrepare->value]);
        $this->actingAs($editor)->put(route('reports.accounting.liquidity-scenarios.update', $scenario), ['name' => 'Anders'])->assertRedirect();
        $this->assertSame('Anders', $scenario->fresh()?->name);
    }

    /** authz-b-7: Installationslizenz und Mandantenzahl sieht nur der Betreiber. */
    public function test_license_page_shows_installation_data_only_to_the_operator(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('admin.license.index'))
            ->assertOk()
            ->assertDontSee(__('Lizenz-Karte'))
            ->assertDontSee(__('Feature-Flags'))
            ->assertDontSee(__('Organisationen'))
            ->assertSee(__('Limits'));

        $operator = User::factory()->admin()->platformAdmin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($operator)->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee(__('Lizenz-Karte'))
            ->assertSee(__('Feature-Flags'));
    }

    /** authz-b-7: der Supportbericht beschreibt die Installation. */
    public function test_support_diagnosis_is_reserved_for_the_operator(): void {
        $admin = $this->orgAdmin();
        $admin->givePermissionTo([Permission::PlatformSupportExport->value]);

        $this->actingAs($admin)->post(route('ai.assist.support-diagnose'))->assertForbidden();
    }

    /** xi-1: ein fremder Host mit dem eigenen als Präfix ist kein Rücksprungziel. */
    public function test_season_dialog_does_not_redirect_to_a_foreign_host(): void {
        $admin = $this->orgAdmin();
        $payload = ['name' => 'Saison 2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31'];

        foreach ([url('/') . '.evil.tld/x', str_replace('://', '://', url('/')) . '@evil.tld/x', '//evil.tld/x'] as $i => $target) {
            $this->actingAs($admin)
                ->post(route('club.seasons.store'), ['name' => 'Saison ' . $i] + $payload + ['return_to' => $target])
                ->assertRedirect(route('club.groups.index'));
        }

        $this->actingAs($admin)
            ->post(route('club.seasons.store'), ['name' => 'Saison ok'] + $payload + ['return_to' => url('/verein/gruppen?tab=teams')])
            ->assertRedirect(url('/verein/gruppen?tab=teams'));
    }

    /** xi-3: auch die Kopfzeile läuft durch den Formel-Guard. */
    public function test_csv_header_cells_are_guarded_against_formulas(): void {
        $response = CsvExport::streamFromRows('felder.csv', ['Name', '=HYPERLINK("http://evil.tld";"Klick")'], [['Alice', 'x']]);

        ob_start();
        $response->sendContent();
        $body = (string) ob_get_clean();

        $this->assertStringNotContainsString(';=HYPERLINK', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
    }

    /** xi-4: der Deep-Link des Lieferanten ist nur mit http(s) ein Link. */
    public function test_open_masterdata_deep_link_is_filtered_like_the_other_urls(): void {
        $mapper = app(OpenMasterdataProductMapper::class);

        $unsafe = $mapper->toRecord(new OpenMasterdataProduct(['supplierPid' => 'S1', 'additional' => ['deepLink' => 'javascript:alert(1)']]));
        $safe = $mapper->toRecord(new OpenMasterdataProduct(['supplierPid' => 'S1', 'additional' => ['deepLink' => 'https://shop.example/p/1']]));

        $this->assertNull($unsafe['product_url'] ?? null);
        $this->assertSame('https://shop.example/p/1', $safe['product_url']);
    }

    /** xi-7: ein Name, den ein anderer Mandant führt, ist frei — und im eigenen weiter eindeutig. */
    public function test_tag_names_are_unique_per_organization(): void {
        $other = Organization::factory()->create();
        Tag::query()->withoutGlobalScopes()->create(['organization_id' => $other->id, 'name' => 'Alpha', 'slug' => 'alpha']);
        $admin = $this->orgAdmin();

        $this->actingAs($admin)->post(route('tags.store'), ['name' => 'Alpha'])->assertSessionHasNoErrors();
        $this->assertSame(1, Tag::query()->where('organization_id', $this->organization->id)->where('name', 'Alpha')->count());

        $this->actingAs($admin)->post(route('tags.store'), ['name' => 'Alpha'])->assertSessionHasErrors('name');
    }
}
