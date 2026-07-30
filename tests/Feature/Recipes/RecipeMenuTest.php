<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeMenuTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Recipes;

use App\Enums\Classification\ClassificationDomain;
use App\Models\{Article, Classification, ProcedureMaterialRequirement, ProcedureTemplate, ProcedureTemplateVersion, User};
use App\Models\Recipes\{RecipeMenu, RecipeMenuItem, RecipeProfile};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Recipes\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-455 — Menü-/Buffetplanung: nur im Partyservice-Kontext (404 sonst),
 * module.lager-Gate, Aggregation nach Gästezahl über die veröffentlichten
 * Rezeptstände ohne Positionsduplikate, Menü-Allergene als Vereinigung.
 */
class RecipeMenuTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    private function activateParty(): void {
        $settings = is_array($this->organization->settings) ? $this->organization->settings : [];
        $settings['branch_profile_code'] = RecipeService::PROFILE_CODE;
        $settings['branch_profile_versions'] = [RecipeService::PROFILE_CODE => 2];
        $this->organization->forceFill(['settings' => $settings])->save();
        // Test-Artefakt: die org-Relation des Actors hängt sonst auf dem
        // Stand VOR der Aktivierung (Instanz lebt über Requests hinweg).
        $this->admin->unsetRelation('organization');
    }

    /** Veröffentlichtes Gericht mit Rezeptprofil + einer Zutat je Portion. */
    private function dish(string $name, Article $article, string $perPortion, ?string $allergen = null): ProcedureTemplate {
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        $version = ProcedureTemplateVersion::factory()->create([
            'procedure_template_id' => $template->id,
            'published_at' => now(),
        ]);
        ProcedureMaterialRequirement::query()->create([
            'procedure_template_version_id' => $version->id,
            'position_code' => 'P0010',
            'article_id' => $article->id,
            'quantity_kind' => 'per_unit',
            'quantity' => $perPortion,
            'unit' => (string) $article->base_unit,
            'is_tool' => false,
            'position' => 10,
            'active' => true,
        ]);
        RecipeProfile::query()->create([
            'organization_id' => $this->organization->id,
            'procedure_template_version_id' => $version->id,
            'base_portions' => '10',
        ]);
        if ($allergen !== null) {
            $classification = Classification::query()->firstOrCreate(
                ['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::Allergen->value, 'code' => $allergen],
                ['label' => ucfirst($allergen), 'sort_order' => 10, 'active' => true],
            );
            $article->classifications()->syncWithoutDetaching([$classification->id]);
        }

        return $template;
    }

    public function test_menus_require_party_profile_and_module(): void {
        $this->get(route('recipe-menus.index'))->assertNotFound();

        $this->activateParty();
        $this->get(route('recipe-menus.index'))->assertOk();

        config(['license.feature_overrides' => ['module.lager' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->get(route('recipe-menus.index'))->assertStatus(423);
    }

    public function test_menu_aggregates_demands_by_guest_count_without_duplicating_positions(): void {
        $this->activateParty();
        $rice = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'REIS', 'name' => 'Reis', 'base_unit' => 'kg']);
        $curry = $this->dish('Curry', $rice, '0.1500', 'senf');
        $bowl = $this->dish('Bowl', $rice, '0.1000', 'sesam');

        $menu = RecipeMenu::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Sommerfest',
            'guest_count' => 20,
            'created_by' => $this->admin->id,
        ]);
        $this->post(route('recipe-menus.items.store', $menu), ['dish' => $curry->sqid, 'portions_per_guest' => '1'])->assertRedirect();
        $this->post(route('recipe-menus.items.store', $menu), ['dish' => $bowl->sqid, 'portions_per_guest' => '0.5'])->assertRedirect();

        $aggregate = app(RecipeService::class)->aggregateMenu($menu->refresh()->load('items.template'), 20);

        // Ein Artikel über zwei Gerichte → EINE aggregierte Zeile, Summen korrekt:
        // Curry 20 × 1 × 0.15 = 3.0 kg; Bowl 20 × 0.5 × 0.10 = 1.0 kg.
        $this->assertCount(1, $aggregate['materials']);
        $this->assertSame('4.0000', $aggregate['materials'][0]['demand']);
        $this->assertSame([], $aggregate['missing_published']);

        // Menü-Allergene = Vereinigung der Gerichte.
        $allergens = app(RecipeService::class)->allergensForMenu($menu);
        $this->assertSame(['senf', 'sesam'], $allergens['effective']);

        // Ansicht rendert Aggregation + Allergene.
        $this->get(route('recipe-menus.show', ['menu' => $menu, 'guests' => 20]))
            ->assertOk()
            ->assertSee('REIS')
            ->assertSee('4.0000')
            ->assertSee('senf')
            ->assertSee('sesam');
    }

    public function test_dishes_without_published_version_are_reported_not_silently_dropped(): void {
        $this->activateParty();
        $rice = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $dish = $this->dish('Suppe', $rice, '0.2000');
        // Veröffentlichung zurückziehen → Gericht darf nicht still verschwinden.
        ProcedureTemplateVersion::query()->where('procedure_template_id', $dish->id)->update(['published_at' => null]);

        $menu = RecipeMenu::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Test',
            'created_by' => $this->admin->id,
        ]);
        RecipeMenuItem::query()->create([
            'organization_id' => $this->organization->id,
            'recipe_menu_id' => $menu->id,
            'procedure_template_id' => $dish->id,
            'portions_per_guest' => '1',
        ]);

        $aggregate = app(RecipeService::class)->aggregateMenu($menu->load('items.template'), 10);
        $this->assertSame([], $aggregate['materials']);
        $this->assertSame(['Suppe'], $aggregate['missing_published']);
    }

    public function test_foreign_menu_is_not_reachable(): void {
        $this->activateParty();
        $menu = RecipeMenu::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Intern',
            'created_by' => $this->admin->id,
        ]);

        $foreignAdmin = User::factory()->admin()->create();
        $this->actingAs($foreignAdmin)->get(route('recipe-menus.show', $menu))->assertNotFound();
    }
}
