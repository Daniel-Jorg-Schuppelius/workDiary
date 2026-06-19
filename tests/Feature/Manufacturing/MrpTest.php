<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MrpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Article\ArticleType;
use App\Models\{Article, ArticleVariant, ProcedureMaterialRequirement, ProcedureTemplateVersion, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\MrpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Mehrstufige Materialbedarfsplanung (Feature 047/048, E7): Auflösung über
 * Halbfabrikate (make) bis zu Zukaufteilen (buy) und Nettobedarf gegen Bestand.
 */
final class MrpTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $finished;
    private Article $semi;
    private ArticleVariant $semiVariant;
    private Article $raw;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $versionFinished = ProcedureTemplateVersion::factory()->create();
        $versionSemi = ProcedureTemplateVersion::factory()->create();

        $this->raw = Article::factory()->create(['organization_id' => $this->organization->id, 'type' => ArticleType::Raw->value, 'manufacturable' => false]);
        ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->raw->id, 'is_default' => true, 'option_signature' => 'r']);

        $this->semi = Article::factory()->create([
            'organization_id' => $this->organization->id, 'type' => ArticleType::SemiFinished->value,
            'manufacturable' => true, 'default_procedure_template_version_id' => $versionSemi->id,
        ]);
        $this->semiVariant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $this->semi->id, 'is_default' => true, 'option_signature' => 's']);

        $this->finished = Article::factory()->create([
            'organization_id' => $this->organization->id, 'type' => ArticleType::Finished->value,
            'manufacturable' => true, 'default_procedure_template_version_id' => $versionFinished->id,
        ]);

        // Stückliste: Fertig braucht 2× Halbfabrikat; Halbfabrikat braucht 3× Rohstoff.
        ProcedureMaterialRequirement::factory()->perUnit('2')->create(['procedure_template_version_id' => $versionFinished->id, 'article_id' => $this->semi->id]);
        ProcedureMaterialRequirement::factory()->perUnit('3')->create(['procedure_template_version_id' => $versionSemi->id, 'article_id' => $this->raw->id]);
    }

    public function test_explodes_multilevel_make_and_buy(): void {
        $lines = app(MrpService::class)->explode($this->finished, null, '5');

        $semiLine = $this->lineFor($lines, $this->semi->id);
        $this->assertSame('make', $semiLine['source']);
        $this->assertSame(1, $semiLine['level']);
        $this->assertSame('10.0000', $semiLine['gross']); // 2 × 5

        $rawLine = $this->lineFor($lines, $this->raw->id);
        $this->assertSame('buy', $rawLine['source']);
        $this->assertSame(2, $rawLine['level']);
        $this->assertSame('30.0000', $rawLine['gross']); // 3 × (2 × 5)
    }

    public function test_nets_secondary_demand_against_stock(): void {
        app(InventoryLedger::class)->receipt($this->semiVariant, $this->warehouse, '4');

        $lines = app(MrpService::class)->explode($this->finished, null, '5', $this->warehouse);

        $semiLine = $this->lineFor($lines, $this->semi->id);
        $this->assertSame('4.0000', $semiLine['available']);
        $this->assertSame('6.0000', $semiLine['net']); // 10 − 4

        $rawLine = $this->lineFor($lines, $this->raw->id);
        $this->assertSame('18.0000', $rawLine['gross']); // 3 × 6 (Nettomenge)
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function lineFor(array $lines, int $articleId): array {
        foreach ($lines as $line) {
            if ($line['article_id'] === $articleId) {
                return $line;
            }
        }
        $this->fail('Keine MRP-Zeile für Artikel ' . $articleId);
    }
}
