<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Models\Article;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\ArticleSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ArticleSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_normalize_coerces_enum_case_and_booleans(): void {
        $spec = new ArticleSpec();

        $row = $spec->normalize([
            'name' => '  Schraube  ',
            'type' => 'MERCHANDISE',
            'status' => 'Active',
            'stockable' => 'ja',
            'currency' => 'eur',
        ]);

        $this->assertSame('Schraube', $row['name']);
        $this->assertSame('merchandise', $row['type']);
        $this->assertSame('active', $row['status']);
        $this->assertTrue($row['stockable']);
        $this->assertSame('EUR', $row['currency']);
    }

    public function test_validate_rejects_unknown_type(): void {
        $spec = new ArticleSpec();
        $issues = $spec->validateRow($spec->normalize(['name' => 'X', 'type' => 'gibtsnicht']), $this->organization);

        $this->assertNotEmpty($issues);
    }

    public function test_upsert_creates_then_updates_by_number(): void {
        $spec = new ArticleSpec();

        [$o1] = $spec->upsert($spec->normalize(['name' => 'Mutter M6', 'number' => 'A-100']), $this->organization);
        $this->assertSame(ImportOutcome::Created, $o1);

        [$o2] = $spec->upsert($spec->normalize(['name' => 'Mutter M6 verzinkt', 'number' => 'A-100']), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $o2);

        $this->assertSame(1, Article::query()->where('organization_id', $this->organization->id)->where('number', 'A-100')->count());
        $this->assertSame('Mutter M6 verzinkt', Article::query()->where('number', 'A-100')->value('name'));
    }

    public function test_upsert_deduplicates_by_gtin_when_number_differs(): void {
        $spec = new ArticleSpec();

        Article::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bohrer 5mm',
            'number' => 'A-1',
            'gtin' => '4006381333931',
        ]);

        [$outcome] = $spec->upsert($spec->normalize([
            'name' => 'Bohrer 5 mm HSS',
            'number' => 'A-2',
            'gtin' => '4006381333931',
        ]), $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $this->assertSame(1, Article::query()
            ->where('organization_id', $this->organization->id)
            ->where('gtin', '4006381333931')->count(), 'Keine Dublette angelegt');
        $this->assertSame('A-1', Article::query()->where('gtin', '4006381333931')->value('number'));
    }
}
