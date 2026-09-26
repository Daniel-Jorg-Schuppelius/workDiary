<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialArticleLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Material;

use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Models\Article\{Article, ArticleSupply};
use App\Models\Material\{Material, MaterialUsage};
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Supplier\Supplier;
use App\Models\Time\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/** MVP-904: Material ↔ Artikel und Materialverbrauch je Lieferant. */
final class MaterialArticleLinkTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    private function material(string $name, ?string $sku, ?Article $article = null): Material {
        return Material::query()->create(['organization_id' => $this->organization->id, 'name' => $name, 'sku' => $sku, 'unit' => 'm', 'is_active' => true, 'article_id' => $article?->id]);
    }

    public function test_form_saves_article_and_sku_linking_is_unambiguous(): void {
        $cable = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'NYM-J 3x1,5', 'number' => 'KAB-315']);
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Dübel A', 'number' => 'DUE']);
        Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Dübel B', 'number' => 'DUE-B']);
        $matching = $this->material('Kabel', 'KAB-315');
        $unknown = $this->material('Klemmen', 'KL-9');

        $this->actingAs($this->admin)->get(route('materials.create'))->assertOk()->assertSee('NYM-J 3x1,5');
        $this->actingAs($this->admin)->post(route('materials.store'), ['name' => 'Rohr', 'unit' => 'm', 'article_id' => $cable->sqid])->assertSessionHasNoErrors();
        $this->assertSame($cable->id, (int) Material::query()->where('name', 'Rohr')->value('article_id'));

        $this->actingAs($this->admin)->post(route('materials.link-articles'))->assertSessionHas('success', __('material.article.linked', ['count' => 1]));
        $this->assertSame($cable->id, (int) $matching->fresh()->article_id);
        $this->assertNull($unknown->fresh()->article_id);
        $this->actingAs($this->admin)->get(route('materials.index'))->assertOk()->assertSee(__('material.article.label') . ': NYM-J 3x1,5');
    }

    public function test_supplier_report_shows_material_consumption_via_preferred_supply(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kupferrohr 15']);
        $preferred = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Rohr-Großhandel']);
        $other = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Zweitlieferant']);
        ArticleSupply::query()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'supplier_id' => $other->id, 'is_preferred' => false]);
        ArticleSupply::query()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'supplier_id' => $preferred->id, 'is_preferred' => true]);
        $linked = $this->material('Kupferrohr', 'CU-15', $article);
        $loose = $this->material('Hanf', null);

        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id,
            'project_id' => Project::factory()->create(['organization_id' => $this->organization->id])->id,
            'work_date' => '2026-06-10', 'kind' => TimesheetKind::Project, 'status' => TimesheetStatus::Draft,
        ]);
        foreach ([[$linked, '4.000', '12.5000'], [$linked, '2.000', '12.5000'], [$loose, '1.000', '3.0000']] as [$material, $qty, $price]) {
            MaterialUsage::create(['organization_id' => $this->organization->id, 'timesheet_id' => $timesheet->id, 'material_id' => $material->id, 'description' => $material->name, 'quantity' => $qty, 'unit' => 'm', 'unit_price' => $price, 'billed' => false]);
        }

        $this->actingAs($this->admin)->withSession($this->dateRangeMonth(2026, 6))->get(route('reports.suppliers'))
            ->assertOk()
            ->assertSeeText(__('reporting.supplier_material.title'))
            ->assertSeeText('Rohr-Großhandel')
            ->assertSeeText('75,00 €')
            ->assertSeeText('6 m')
            ->assertSeeText('3,00 €');
    }
}
