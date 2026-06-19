<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingPlanningControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Article\ArticleType;
use App\Models\{Article, ArticleVariant, ProcedureMaterialRequirement, ProcedureTemplateVersion, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fertigungsplanungs-UI (Feature 047/048, E7): MRP-Auflösung über HTTP.
 */
final class ManufacturingPlanningControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Article $finished;
    private Article $semi;
    private Article $raw;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $vF = ProcedureTemplateVersion::factory()->create();
        $vS = ProcedureTemplateVersion::factory()->create();
        $this->raw = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Rohstoff X', 'type' => ArticleType::Raw->value, 'manufacturable' => false]);
        $this->semi = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Halbzeug Y', 'type' => ArticleType::SemiFinished->value, 'manufacturable' => true, 'default_procedure_template_version_id' => $vS->id]);
        $this->finished = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Erzeugnis Z', 'type' => ArticleType::Finished->value, 'manufacturable' => true, 'default_procedure_template_version_id' => $vF->id]);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->semi->id, 'is_default' => true, 'option_signature' => 's']);

        ProcedureMaterialRequirement::factory()->perUnit('2')->create(['procedure_template_version_id' => $vF->id, 'article_id' => $this->semi->id]);
        ProcedureMaterialRequirement::factory()->perUnit('3')->create(['procedure_template_version_id' => $vS->id, 'article_id' => $this->raw->id]);
    }

    public function test_planning_page_explodes_bom(): void {
        $this->actingAs($this->admin)
            ->get(route('manufacturing-planning.index', ['article' => $this->finished->sqid, 'qty' => '5']))
            ->assertOk()
            ->assertSee('Halbzeug Y')
            ->assertSee('Rohstoff X');
    }

    public function test_requires_view_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('manufacturing-planning.index'))->assertForbidden();
    }
}
