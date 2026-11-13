<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceOfSupplyMaintenanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Invoicing\PlaceOfSupplyKind;
use App\Enums\Project\ProjectStatus;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Article\Import\ArticleSpec;
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Import\ImportOutcome;
use App\Services\Invoicing\{PlaceOfSupplyResolver, ServiceDefaultResolver};
use App\Settings\{SettingScope, SettingsRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * MVP-1688: Die Leistungsort-Art steht dort, wo die Leistung definiert ist —
 * Artikel, Abrechnungsregel des Projekts, Vorgabe der Organisation — und
 * erbt in dieser Reihenfolge; Branchenprofile setzen die Vorgabe, ohne eine
 * eigene Entscheidung zu überschreiben.
 */
final class PlaceOfSupplyMaintenanceTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->organization = Organization::query()->findOrFail($this->admin->organization_id);
        app()->instance('currentOrganization', $this->organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_article_dialog_stores_and_shows_the_place_of_supply(): void {
        $this->actingAs($this->admin)->post(route('articles.store'), [
            'name' => 'Datenschutzberatung', 'type' => ArticleType::Service->value, 'base_unit' => 'Std.',
            'status' => 'active', 'currency' => 'EUR', 'place_of_supply_kind' => 'catalog',
        ])->assertSessionHasNoErrors();
        $article = Article::query()->where('name', 'Datenschutzberatung')->firstOrFail();
        $this->assertSame(PlaceOfSupplyKind::Catalog, $article->place_of_supply_kind);

        $this->get(route('articles.edit', $article) . '?dialog=1')->assertOk()->assertSee('name="place_of_supply_kind"', false);
        $this->get(route('articles.show', $article))->assertOk()->assertSee(PlaceOfSupplyKind::Catalog->label());

        $this->put(route('articles.update', $article), [
            'name' => 'Datenschutzberatung', 'type' => ArticleType::Service->value, 'base_unit' => 'Std.',
            'status' => 'active', 'currency' => 'EUR', 'place_of_supply_kind' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($article->refresh()->place_of_supply_kind);

        $this->post(route('articles.store'), ['name' => 'Falsch', 'type' => 'service', 'base_unit' => 'Std.', 'status' => 'active', 'place_of_supply_kind' => 'mond'])
            ->assertSessionHasErrors('place_of_supply_kind');
    }

    public function test_article_import_takes_the_place_of_supply(): void {
        $spec = new ArticleSpec();
        $row = $spec->normalize(['name' => 'Rasenpflege', 'type' => 'service', 'base_unit' => 'Std.', 'place_of_supply_kind' => 'Property']);
        $this->assertSame([], $spec->validateRow($row, $this->organization));

        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertSame(PlaceOfSupplyKind::Property, Article::query()->where('name', 'Rasenpflege')->firstOrFail()->place_of_supply_kind);
        $this->assertNotSame([], $spec->validateRow($spec->normalize(['name' => 'Falsch', 'place_of_supply_kind' => 'mond']), $this->organization));
    }

    /** Regel → Artikel der Regel → Vorgabe der Organisation. */
    public function test_billing_rule_inherits_from_its_article_and_the_organization(): void {
        $customer = Customer::query()->create(['organization_id' => $this->organization->id, 'name' => 'Kunde', 'currency' => 'EUR']);
        $project = Project::create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Bau', 'status' => ProjectStatus::Active->value]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Montage', 'type' => ArticleType::Service->value, 'sellable' => true, 'place_of_supply_kind' => 'property']);

        $this->actingAs($this->admin)
            ->post(route('projects.billing-rules.store', $project), ['article' => 'art:' . $article->sqid, 'item_type' => 'service'])
            ->assertRedirect();
        $rule = $project->billingRules()->sole();
        $this->assertNull($rule->place_of_supply_kind);
        $places = app(PlaceOfSupplyResolver::class);
        $this->assertSame(PlaceOfSupplyKind::Property, $places->forProject($this->organization, $project)->kind, 'Leer erbt die Regel vom Artikel.');

        $this->put(route('projects.billing-rules.update', [$project, $rule]), ['article' => 'art:' . $article->sqid, 'item_type' => 'service', 'place_of_supply_kind' => 'catalog'])
            ->assertRedirect();
        $this->assertSame(PlaceOfSupplyKind::Catalog, $rule->refresh()->place_of_supply_kind);
        app(ServiceDefaultResolver::class)->flush();
        $this->assertSame(PlaceOfSupplyKind::Catalog, $places->forProject($this->organization, $project->refresh())->kind);
        $this->get(route('projects.show', $project))->assertOk()->assertSee(PlaceOfSupplyKind::Catalog->label());
        $this->get(route('projects.billing-rules.edit', [$project, $rule]) . '?dialog=1')->assertOk()->assertSee('name="place_of_supply_kind"', false);

        // Ohne Regel und Artikel: Vorgabe der Organisation, ab Werk allgemein.
        $bare = Project::create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Ohne Regel', 'status' => ProjectStatus::Active->value]);
        $this->assertSame(PlaceOfSupplyKind::General, $places->forProject($this->organization, $bare)->kind);
    }

    public function test_organization_settings_store_the_default(): void {
        $this->actingAs($this->admin)->get(route('admin.organizations.edit', $this->organization))
            ->assertOk()->assertSee('name="settings[invoicing][place_of_supply_kind]"', false);

        $this->submit(['invoicing' => ['place_of_supply_kind' => 'property']])->assertSessionHasNoErrors();
        $this->assertSame(PlaceOfSupplyKind::Property, app(PlaceOfSupplyResolver::class)->organizationDefault($this->organization->refresh()));

        $this->submit(['invoicing' => ['place_of_supply_kind' => 'mond']])->assertSessionHasErrors('settings.invoicing.place_of_supply_kind');

        $this->submit(['invoicing' => ['place_of_supply_kind' => '']])->assertSessionHasNoErrors();
        $this->assertSame(PlaceOfSupplyKind::General, app(PlaceOfSupplyResolver::class)->organizationDefault($this->organization->refresh()));
    }

    public function test_branch_profiles_set_the_default_without_overriding_an_own_choice(): void {
        $it = Organization::factory()->create();
        app(BranchProfileInstaller::class)->install($it, 'it', $this->admin);
        $this->assertSame(PlaceOfSupplyKind::Catalog, app(PlaceOfSupplyResolver::class)->organizationDefault($it->refresh()));

        $construction = Organization::factory()->create();
        app(SettingsRegistry::class)->set('invoicing.place_of_supply_kind', 'general', SettingScope::Organization, $construction, $this->admin->id);
        app(BranchProfileInstaller::class)->install($construction->refresh(), 'bau-ausbau', $this->admin);
        $this->assertSame(PlaceOfSupplyKind::General, app(PlaceOfSupplyResolver::class)->organizationDefault($construction->refresh()));

        $cleaning = Organization::factory()->create();
        app(BranchProfileInstaller::class)->install($cleaning, 'gebaeudereinigung', $this->admin);
        $this->assertSame(PlaceOfSupplyKind::Property, app(PlaceOfSupplyResolver::class)->organizationDefault($cleaning->refresh()));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return \Illuminate\Testing\TestResponse<\Illuminate\Http\Response>
     */
    private function submit(array $settings): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'plan' => $this->organization->plan,
            'locale' => $this->organization->locale ?? 'de',
            'timezone' => $this->organization->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            'settings' => $settings,
        ]);
    }
}
