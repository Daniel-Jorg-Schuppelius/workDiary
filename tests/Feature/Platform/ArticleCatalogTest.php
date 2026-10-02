<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCatalogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Platform;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Models\Article\Article;
use App\Models\Platform\{Organization, User};
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Platform\Catalog\ArticleCatalog;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Artikelkatalog mit Quellen (Phase 125, MVP-1025/1026): Schlüssel und
 * Formularschlüssel lösen nur innerhalb der Organisation auf; die
 * Standardleistung der Organisation speichert den Katalogschlüssel.
 */
class ArticleCatalogTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->organization = Organization::query()->findOrFail($this->admin->organization_id);
    }

    /** @param  array<string, mixed>  $attributes */
    private function lexArticle(Organization $organization, string $name, array $attributes = []): LexofficeArticle {
        return LexofficeArticle::create(array_merge([
            'organization_id' => $organization->id,
            'external_id' => 'lx-' . uniqid('', true),
            'name' => $name,
            'type' => 'PRODUCT',
            'unit_name' => 'Monat',
            'net_unit_price' => '20.60',
            'currency' => 'EUR',
            'vat_rate' => '19.00',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function submit(array $settings): TestResponse {
        return $this->actingAs($this->admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'plan' => $this->organization->plan,
            'locale' => $this->organization->locale ?? 'de',
            'timezone' => $this->organization->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            'settings' => $settings,
        ]);
    }

    public function test_keys_and_form_keys_resolve_only_within_the_organization(): void {
        $catalog = app(ArticleCatalog::class);
        $orgId = (int) $this->organization->id;
        $local = Article::factory()->create(['organization_id' => $orgId, 'name' => 'Wartung', 'type' => ArticleType::Service->value]);
        $lex = $this->lexArticle($this->organization, 'Microsoft 365 Business Premium');
        $foreignOrg = Organization::factory()->create();
        $foreign = Article::factory()->create(['organization_id' => $foreignOrg->id, 'name' => 'Fremd']);
        $foreignLex = $this->lexArticle($foreignOrg, 'Fremd-Lexoffice');

        $found = $catalog->findMany($orgId, ['art:' . $local->id, 'lex:' . $lex->id, 'art:' . $foreign->id, 'lex:' . $foreignLex->id, 'xyz:1', 'kaputt', null]);

        $this->assertEqualsCanonicalizing(['art:' . $local->id, 'lex:' . $lex->id], array_keys($found));
        $this->assertSame('service', $found['art:' . $local->id]->itemType);
        $this->assertSame('material', $found['lex:' . $lex->id]->itemType, 'Lexoffice-Typ PRODUCT = Material-Position');
        $this->assertSame(19.0, (float) $found['lex:' . $lex->id]->vatRate?->getNumericValue());
        $this->assertSame('lex:' . Sqid::encode(LexofficeArticle::class, (int) $lex->id), $found['lex:' . $lex->id]->formKey);

        foreach ($found as $key => $article) {
            $this->assertSame($key, $catalog->fromFormKey($orgId, $article->formKey)?->key, 'Formularschlüssel hin und zurück');
        }
        $this->assertNull($catalog->fromFormKey($orgId, 'art:' . $foreign->sqid), 'fremde Organisation');
        $this->assertNull($catalog->fromFormKey($orgId, 'lex:' . Sqid::encode(LexofficeArticle::class, (int) $foreignLex->id)));
        $this->assertNull($catalog->fromFormKey($orgId, 'art:unsinn'));
        $this->assertNull($catalog->fromFormKey($orgId, 'xyz:' . $local->sqid), 'unbekannte Quelle');
    }

    public function test_active_lists_sellable_articles_with_lexoffice_before_the_article_master(): void {
        $orgId = (int) $this->organization->id;
        $active = Article::factory()->create(['organization_id' => $orgId, 'name' => 'Aktiv']);
        Article::factory()->create(['organization_id' => $orgId, 'name' => 'Entwurf', 'status' => ArticleStatus::Draft->value]);
        Article::factory()->create(['organization_id' => $orgId, 'name' => 'Nur Einkauf', 'sellable' => false]);
        $lex = $this->lexArticle($this->organization, 'Lexoffice aktiv');
        $this->lexArticle($this->organization, 'Lexoffice archiviert', ['archived_at' => now()]);

        $keys = array_map(static fn ($article): string => $article->key, app(ArticleCatalog::class)->active($orgId));

        $this->assertSame(['lex:' . $lex->id, 'art:' . $active->id], $keys);
    }

    public function test_default_service_article_is_stored_as_catalog_key(): void {
        $lex = $this->lexArticle($this->organization, 'IT-Dienstleistung');
        $formKey = 'lex:' . Sqid::encode(LexofficeArticle::class, (int) $lex->id);

        $this->actingAs($this->admin)->get(route('admin.organizations.edit', $this->organization))
            ->assertOk()
            ->assertSee('value="' . $formKey . '"', false);

        $this->submit(['invoicing' => ['default_service_article' => $formKey]])->assertSessionHasNoErrors();
        $this->assertSame('lex:' . $lex->id, data_get($this->organization->fresh()?->settings, 'invoicing.default_service_article'));

        $foreign = $this->lexArticle(Organization::factory()->create(), 'Fremd');
        $this->submit(['invoicing' => ['default_service_article' => 'lex:' . Sqid::encode(LexofficeArticle::class, (int) $foreign->id)]])
            ->assertSessionHasErrors('settings.invoicing.default_service_article');
        $this->assertSame('lex:' . $lex->id, data_get($this->organization->fresh()?->settings, 'invoicing.default_service_article'));

        $this->submit(['invoicing' => ['default_service_article' => '']])->assertSessionHasNoErrors();
        $this->assertNull(data_get($this->organization->fresh()?->settings, 'invoicing.default_service_article'));
    }
}
