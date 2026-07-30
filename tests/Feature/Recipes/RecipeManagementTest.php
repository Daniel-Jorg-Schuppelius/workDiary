<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeManagementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Recipes;

use App\Enums\Classification\ClassificationDomain;
use App\Models\{Article, Classification, ProcedureMaterialRequirement, ProcedureTemplate, ProcedureTemplateVersion, User};
use App\Models\Recipes\RecipeProfile;
use App\Services\Recipes\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-455 — Rezeptpflege: Positionen nur am Draft (Versions-Snapshot),
 * dezimalgenaue Skalierung (fest/je Portion/Verhältnis + Verschnitt),
 * Plankosten je Portion, Allergenvererbung mit Freigabe-Blockade und
 * Partyservice-Kontext-Gates (technische Rezepturen bleiben unberührt).
 */
class RecipeManagementTest extends TestCase {
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

    private function allergen(string $code, string $label = ''): Classification {
        return Classification::query()->firstOrCreate(
            ['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::Allergen->value, 'code' => $code],
            ['label' => $label !== '' ? $label : ucfirst($code), 'sort_order' => 10, 'active' => true],
        );
    }

    /** @return array{0: ProcedureTemplate, 1: ProcedureTemplateVersion} */
    private function templateWithDraft(): array {
        $template = ProcedureTemplate::factory()->create(['organization_id' => $this->organization->id]);
        $version = ProcedureTemplateVersion::factory()->create(['procedure_template_id' => $template->id]);

        return [$template, $version];
    }

    private function ingredient(ProcedureTemplateVersion $version, Article $article, array $overrides = []): ProcedureMaterialRequirement {
        static $position = 0;
        $position += 10;

        return ProcedureMaterialRequirement::query()->create(array_merge([
            'procedure_template_version_id' => $version->id,
            'position_code' => 'P' . str_pad((string) $position, 4, '0', STR_PAD_LEFT),
            'article_id' => $article->id,
            'quantity_kind' => 'per_unit',
            'quantity' => '0.2500',
            'unit' => 'kg',
            'is_tool' => false,
            'position' => $position,
            'active' => true,
        ], $overrides));
    }

    public function test_material_positions_are_editable_on_draft_only(): void {
        [$template, $draft] = $this->templateWithDraft();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'ZUT-1', 'base_unit' => 'kg']);

        $this->post(route('procedures.materials.store', [$template, $draft]), [
            'article' => $article->sqid,
            'quantity_kind' => 'per_unit',
            'quantity' => '0.25',
            'unit' => 'kg',
            'waste_surcharge' => '10',
        ])->assertRedirect();

        $requirement = ProcedureMaterialRequirement::query()->where('procedure_template_version_id', $draft->id)->firstOrFail();
        $this->assertSame($article->id, $requirement->article_id);
        $this->assertSame('10.000', (string) $requirement->waste_surcharge);

        // Veröffentlichte Version: unveränderlicher Snapshot.
        $draft->forceFill(['published_at' => now()])->save();
        $this->post(route('procedures.materials.store', [$template, $draft]), [
            'article' => $article->sqid,
            'quantity_kind' => 'fixed',
            'quantity' => '1',
            'unit' => 'Stk',
        ])->assertStatus(422);
        $this->delete(route('procedures.materials.destroy', [$template, $draft, $requirement]))->assertStatus(422);
        $this->assertSame(1, ProcedureMaterialRequirement::query()->where('procedure_template_version_id', $draft->id)->count());
    }

    public function test_foreign_tenant_template_is_not_reachable(): void {
        [$template, $draft] = $this->templateWithDraft();
        $foreignAdmin = User::factory()->admin()->create();

        $this->actingAs($foreignAdmin)
            ->post(route('procedures.materials.store', [$template, $draft]), [
                'article' => 'egal',
                'quantity_kind' => 'fixed',
                'quantity' => '1',
                'unit' => 'Stk',
            ])->assertNotFound();
    }

    public function test_scaling_is_decimal_precise_for_all_quantity_kinds(): void {
        [, $version] = $this->templateWithDraft();
        $flour = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $water = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $paper = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk']);

        // je Portion 0.25 kg mit 10 % Verschnitt; Verhältnis 3:1; fest 2 Stk je Ansatz.
        $this->ingredient($version, $flour, ['quantity_kind' => 'ratio', 'quantity' => '0', 'ratio_part' => '3', 'unit' => 'kg']);
        $this->ingredient($version, $water, ['quantity_kind' => 'ratio', 'quantity' => '0', 'ratio_part' => '1', 'unit' => 'kg']);
        $this->ingredient($version, $paper, ['quantity_kind' => 'fixed', 'quantity' => '2.0000', 'unit' => 'Stk']);
        $butter = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $this->ingredient($version, $butter, ['quantity' => '0.2500', 'waste_surcharge' => '10']);

        $demand = collect(app(RecipeService::class)->demandForPortions($version, '25'))
            ->mapWithKeys(fn(array $row): array => [$row['requirement']->article_id => $row['demand']]);

        $this->assertSame('18.7500', $demand[$flour->id]);  // 25 × 3/4
        $this->assertSame('6.2500', $demand[$water->id]);   // 25 × 1/4
        $this->assertSame('2.0000', $demand[$paper->id]);   // fest je Ansatz
        $this->assertSame('6.8750', $demand[$butter->id]);  // 25 × 0.25 × 1.10
    }

    public function test_plan_costs_per_portion_from_article_costs(): void {
        $this->activateParty();
        [, $version] = $this->templateWithDraft();
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'base_unit' => 'kg',
            'default_purchase_price' => '2.0000',
            'currency' => 'EUR',
        ]);
        $this->ingredient($version, $article);

        $plan = app(RecipeService::class)->planCosts($version, '10');

        $this->assertSame('5.0000', $plan['total']?->getAmount());      // 10 × 0.25 kg × 2 €
        $this->assertSame('0.5000', $plan['per_portion']?->getAmount());
        $this->assertSame([], $plan['incomplete']);
    }

    public function test_allergens_derive_from_ingredients_and_block_publishing_when_unresolved(): void {
        $this->activateParty();
        $gluten = $this->allergen('gluten', 'Glutenhaltiges Getreide');
        $this->allergen('keine', 'Keine Allergene');

        [$template, $draft] = $this->templateWithDraft();
        $flour = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'MEHL', 'base_unit' => 'kg']);
        $this->ingredient($draft, $flour);

        // Partyservice-Profil anlegen (Grundausbeute) — auditierter Aufsatz.
        $this->post(route('procedures.recipe-profile.save', [$template, $draft]), [
            'base_portions' => '10',
        ])->assertRedirect();
        $this->assertNotNull(RecipeProfile::query()->where('procedure_template_version_id', $draft->id)->first());

        // Ungeklärte Zutat blockiert die Freigabe.
        $this->post(route('procedures.versions.publish', [$template, $draft]))
            ->assertSessionHasErrors('allergens');
        $this->assertNull($draft->refresh()->published_at);

        // Zutat klären (gluten) → Vererbung aufs Gericht, Freigabe möglich.
        $this->post(route('procedures.ingredient-allergens.save', [$template, $draft, $flour]), [
            'allergens' => ['gluten'],
        ])->assertRedirect();

        $set = app(RecipeService::class)->allergens($draft->refresh());
        $this->assertSame(['gluten'], $set['effective']);
        $this->assertSame([], $set['unresolved']);

        $this->post(route('procedures.versions.publish', [$template, $draft]))->assertSessionHasNoErrors();
        $this->assertNotNull($draft->refresh()->published_at);
    }

    public function test_manual_allergen_override_requires_reason_and_unblocks_publishing(): void {
        $this->activateParty();
        $this->allergen('sellerie', 'Sellerie');

        [$template, $draft] = $this->templateWithDraft();
        $stock = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'l']);
        $this->ingredient($draft, $stock, ['unit' => 'l']);

        // Abweichung ohne Begründung wird abgewiesen.
        $this->post(route('procedures.recipe-profile.save', [$template, $draft]), [
            'base_portions' => '10',
            'allergen_added' => ['sellerie'],
        ])->assertSessionHasErrors('override_reason');

        $this->post(route('procedures.recipe-profile.save', [$template, $draft]), [
            'base_portions' => '10',
            'allergen_added' => ['sellerie'],
            'override_reason' => 'Fond enthält Sellerie, Zutat noch ohne Pflege.',
        ])->assertRedirect();

        // Begründete Abweichung: sichtbar in effective + Freigabe frei.
        $set = app(RecipeService::class)->allergens($draft->refresh());
        $this->assertContains('sellerie', $set['effective']);
        $this->assertNotSame([], $set['unresolved']);

        $this->post(route('procedures.versions.publish', [$template, $draft]))->assertSessionHasNoErrors();
        $this->assertNotNull($draft->refresh()->published_at);
    }

    public function test_technical_recipes_without_party_profile_stay_untouched(): void {
        // KEIN Partyservice-Profil: ungeklärte Zutaten blockieren nichts,
        // Partyservice-Endpunkte sind nicht erreichbar (404).
        [$template, $draft] = $this->templateWithDraft();
        $resin = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $this->ingredient($draft, $resin, ['quantity_kind' => 'ratio', 'quantity' => '0', 'ratio_part' => '2']);

        $this->post(route('procedures.recipe-profile.save', [$template, $draft]), ['base_portions' => '1'])
            ->assertNotFound();

        $this->post(route('procedures.versions.publish', [$template, $draft]))->assertSessionHasNoErrors();
        $this->assertNotNull($draft->refresh()->published_at);
    }

    public function test_new_version_carries_over_materials_and_profile(): void {
        $this->activateParty();
        [$template, $draft] = $this->templateWithDraft();
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'kg']);
        $this->ingredient($draft, $article);
        RecipeProfile::query()->create([
            'organization_id' => $this->organization->id,
            'procedure_template_version_id' => $draft->id,
            'base_portions' => '12',
        ]);
        $draft->forceFill(['published_at' => now()])->save();

        $this->post(route('procedures.versions.store', $template))->assertRedirect();

        $new = $template->versions()->whereNull('published_at')->orderByDesc('version')->firstOrFail();
        $this->assertSame(1, $new->materialRequirements()->count());
        $this->assertSame('12.00', (string) RecipeProfile::query()
            ->where('procedure_template_version_id', $new->id)->firstOrFail()->base_portions);
    }
}
