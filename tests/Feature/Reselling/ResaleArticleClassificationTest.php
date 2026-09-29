<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleArticleClassificationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\ResaleArticleRole;
use App\Models\Article\Article;
use App\Models\Reselling\{ResaleArticleClassification, ResaleLicenseProduct, ResaleSubscription};
use App\Services\Reselling\Register\LicenseArticleClassifier;
use App\Services\Stammdaten\ArticleMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abo-Einstufung am Katalogschlüssel (MVP-1025): der scoped Klassifizierer
 * sieht Änderungen sofort, und eine Artikel-Zusammenführung zieht Abos,
 * Lizenzprodukte und Einstufungen auf den Zielartikel um.
 */
class ResaleArticleClassificationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_scoped_classifier_sees_saved_and_deleted_classifications(): void {
        $orgId = (int) $this->organization->id;
        $article = Article::factory()->create(['organization_id' => $orgId, 'name' => 'Cloud-Arbeitsplatz Premium']);
        $key = 'art:' . $article->id;
        $classifier = app(LicenseArticleClassifier::class);
        $this->assertFalse($classifier->isLicense($orgId, $key, $article->name), 'Name verrät kein Produkt');

        $classification = ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => $key, 'role' => ResaleArticleRole::License]);
        $this->assertSame($classifier, app(LicenseArticleClassifier::class), 'eine Instanz je Request');
        $this->assertTrue($classifier->isLicense($orgId, $key, $article->name));

        $classification->delete();
        $this->assertFalse($classifier->isLicense($orgId, $key, $article->name));
        $this->assertFalse($classifier->isLicense($orgId, null, 'Microsoft 365 Business Premium'), 'ohne Artikel nie ein Abo-Produkt');
    }

    public function test_article_merge_repoints_subscriptions_licence_products_and_classifications(): void {
        $orgId = (int) $this->organization->id;
        $source = Article::factory()->create(['organization_id' => $orgId, 'name' => 'Exchange Online Plan 1']);
        $target = Article::factory()->create(['organization_id' => $orgId, 'name' => 'Exchange Online (Plan 1)']);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $orgId, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)', 'article_ref' => 'art:' . $source->id,
            'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ]);
        $product = ResaleLicenseProduct::query()->create([
            'organization_id' => $orgId, 'name' => 'Office 2024', 'key_roles' => [['code' => 'key_1', 'label' => 'Produktschlüssel']], 'article_ref' => 'art:' . $source->id,
        ]);
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => 'art:' . $source->id, 'role' => ResaleArticleRole::License]);
        ResaleArticleClassification::query()->create(['organization_id' => $orgId, 'article_ref' => 'art:' . $target->id, 'role' => ResaleArticleRole::Excluded]);

        app(ArticleMergeService::class)->merge($source, $target);

        $this->assertSame('art:' . $target->id, $subscription->fresh()?->article_ref);
        $this->assertSame('art:' . $target->id, $product->fresh()?->article_ref);
        $this->assertSame(
            ['art:' . $target->id => ResaleArticleRole::Excluded->value],
            ResaleArticleClassification::query()->pluck('role', 'article_ref')->map(static fn (ResaleArticleRole $role): string => $role->value)->all(),
            'die Einstufung des Ziels gilt',
        );
        $this->assertFalse(app(LicenseArticleClassifier::class)->isLicense($orgId, 'art:' . $target->id, 'Exchange Online (Plan 1)'), 'Cache nach dem Umzug geleert');
    }
}
