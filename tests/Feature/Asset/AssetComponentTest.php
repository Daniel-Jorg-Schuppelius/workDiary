<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Models\{Article, Asset, AssetComponent, Organization, User};
use App\Services\Asset\AssetComponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anlagen-Stückliste (Feature 118, MVP-607).
 *
 * Der Zweck ist die Historie: „Was war vorher drin" ist bei wiederkehrenden
 * Defekten die entscheidende Frage — ein Ersetzen darf sie deshalb nie
 * überschreiben.
 */
class AssetComponentTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id, 'name' => 'Lüftungsanlage 1']);
    }

    /** @param array<string, mixed> $attributes */
    private function part(array $attributes = []): AssetComponent {
        return AssetComponent::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'label' => 'Filter G4',
            'quantity' => '2.000',
            'unit' => 'Stk',
            'position' => 'Zuluft',
            'installed_on' => now()->subMonths(10)->toDateString(),
            'replace_interval_months' => 12,
        ], $attributes));
    }

    public function test_installed_components_are_listed(): void {
        $this->part();
        $this->part(['label' => 'Keilriemen', 'position' => 'Antrieb']);

        $this->assertCount(2, app(AssetComponentService::class)->installed($this->asset));
    }

    public function test_article_name_wins_over_free_text(): void {
        $article = Article::factory()->create(['organization_id' => $this->org->id, 'name' => 'Taschenfilter F7']);
        $component = $this->part(['article_id' => $article->id, 'label' => 'Irgendwas']);

        $this->assertSame('Taschenfilter F7', $component->refresh()->displayName());
    }

    public function test_due_date_follows_the_interval(): void {
        $component = $this->part([
            'installed_on' => '2026-01-15',
            'replace_interval_months' => 6,
        ]);

        $this->assertSame('2026-07-15', $component->dueOn()?->toDateString());
    }

    /** Ohne Intervall gibt es keine Fälligkeit — geraten wird nicht. */
    public function test_without_an_interval_there_is_no_due_date(): void {
        $component = $this->part(['replace_interval_months' => null]);

        $this->assertNull($component->dueOn());
        $this->assertFalse($component->isDue());
    }

    public function test_due_components_are_proposed(): void {
        $due = $this->part(['installed_on' => now()->subMonths(13)->toDateString(), 'replace_interval_months' => 12]);
        $this->part(['label' => 'Neu', 'installed_on' => now()->subMonth()->toDateString(), 'replace_interval_months' => 12]);

        $proposed = app(AssetComponentService::class)->dueComponents($this->asset);

        $this->assertSame([$due->id], $proposed->pluck('id')->all());
    }

    // ── Die Historie ────────────────────────────────────────────────────

    public function test_replacing_keeps_the_old_part_in_the_history(): void {
        $old = $this->part();

        $new = app(AssetComponentService::class)->replace($old, [
            'label' => 'Filter G4 (neu)',
            'quantity' => '2.000',
        ], $this->admin);

        $old->refresh();
        $this->assertSame(AssetComponent::STATUS_REPLACED, $old->status);
        $this->assertSame(now()->toDateString(), $old->removed_on?->toDateString());
        $this->assertSame($new->id, $old->replaced_by_id);
        // Die Liste zeigt nur das neue Teil, die Historie beide.
        $this->assertCount(1, app(AssetComponentService::class)->installed($this->asset));
        $this->assertCount(2, app(AssetComponentService::class)->history($this->asset));
    }

    public function test_a_replaced_part_cannot_be_replaced_again(): void {
        $old = $this->part();
        app(AssetComponentService::class)->replace($old, ['label' => 'Neu'], $this->admin);

        $this->expectException(\RuntimeException::class);
        app(AssetComponentService::class)->replace($old->refresh(), ['label' => 'Noch neuer'], $this->admin);
    }

    public function test_removing_takes_the_part_out_of_the_list(): void {
        $component = $this->part();

        $this->actingAs($this->admin)
            ->post(route('assets.components.remove', [$this->asset, $component]))
            ->assertRedirect();

        $this->assertSame(AssetComponent::STATUS_REMOVED, $component->refresh()->status);
        $this->assertCount(0, app(AssetComponentService::class)->installed($this->asset));
    }

    public function test_index_shows_installed_and_due_parts(): void {
        $this->part(['installed_on' => now()->subMonths(13)->toDateString()]);

        $response = $this->actingAs($this->admin)->get(route('assets.components.index', $this->asset));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('installed'));
        $this->assertCount(1, $response->viewData('due'));
    }

    public function test_endpoint_stores_a_component_with_an_article_sqid(): void {
        $article = Article::factory()->create(['organization_id' => $this->org->id, 'name' => 'Keilriemen XPZ']);

        $this->actingAs($this->admin)->post(route('assets.components.store', $this->asset), [
            'article_id' => $article->sqid,
            'quantity' => '1',
            'position' => 'Antrieb',
            'replace_interval_months' => 24,
        ])->assertRedirect();

        $component = AssetComponent::query()->sole();
        $this->assertSame((int) $article->id, (int) $component->article_id);
        $this->assertSame('Keilriemen XPZ', $component->displayName());
    }

    /** Ohne Artikel MUSS ein Freitext da sein — sonst hieße die Zeile „—". */
    public function test_a_component_needs_an_article_or_a_label(): void {
        $this->actingAs($this->admin)->post(route('assets.components.store', $this->asset), [
            'quantity' => '1',
        ])->assertSessionHasErrors('label');
    }

    public function test_cross_organization_component_is_not_reachable(): void {
        $foreignOrg = Organization::factory()->create();
        $foreignAsset = Asset::factory()->create(['organization_id' => $foreignOrg->id]);
        $component = $this->part();

        $this->actingAs($this->admin)
            ->post(route('assets.components.remove', [$foreignAsset, $component]))
            ->assertNotFound();
    }
}
