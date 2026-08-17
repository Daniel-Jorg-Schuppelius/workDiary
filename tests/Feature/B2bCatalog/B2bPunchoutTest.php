<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bPunchoutTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\B2bCatalog;

use App\Models\Article;
use App\Models\B2b\{B2bCatalogAccess, B2bCatalogItem};
use App\Models\{Customer, Organization};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-457 — OCI-Punchout-Katalog (Ausgang): sessionloser Login mit gehashten
 * Zugangsdaten, HTTPS-HOOK_URL-Pflicht, Tenant-/Freigabe-Scope im Browse,
 * OCI-4.0-Warenkorb-Rückgabe, Modul-Gate (404) und Rate-Limit.
 */
class B2bPunchoutTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const HOOK_URL = 'https://procurement.example.test/oci/hook';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(FeatureFlagResolver::class)->flush();
    }

    /** @return array{access: B2bCatalogAccess, secret: string, customer: Customer} */
    private function issueAccess(?Organization $organization = null, string $username = 'einkauf-acme'): array {
        $organization ??= $this->organization;
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        [$access, $secret] = B2bCatalogAccess::issue((int) $organization->id, (int) $customer->id, 'ACME Einkauf', $username);

        return ['access' => $access, 'secret' => $secret, 'customer' => $customer];
    }

    private function release(B2bCatalogAccess $access, Article $article, ?string $price = null): B2bCatalogItem {
        return B2bCatalogItem::query()->create([
            'organization_id' => $access->organization_id,
            'access_id' => $access->id,
            'article_id' => $article->id,
            'custom_price' => $price,
        ]);
    }

    private function punchout(array $overrides = [], ?Organization $organization = null): \Illuminate\Testing\TestResponse {
        $organization ??= $this->organization;

        return $this->post('/b2b-katalog/' . $organization->slug . '/punchout', array_merge([
            'USERNAME' => 'einkauf-acme',
            'PASSWORD' => 'falsch',
            'HOOK_URL' => self::HOOK_URL,
            'OCI_VERSION' => '4.0',
        ], $overrides));
    }

    public function test_unknown_org_and_inactive_module_return_404(): void {
        // Kontrolle: Modul aktiv → Anfrage erreicht den Controller (403 = Credentials).
        $this->punchout()->assertForbidden();

        config(['license.feature_overrides' => ['module.b2b_katalog' => false]]);
        app(FeatureFlagResolver::class)->flush();
        $this->punchout()->assertNotFound();

        $this->post('/b2b-katalog/gibt-es-nicht/punchout')->assertNotFound();
    }

    public function test_punchout_requires_https_hook_url(): void {
        $data = $this->issueAccess();

        $this->punchout(['PASSWORD' => $data['secret'], 'HOOK_URL' => 'http://procurement.example.test/hook'])
            ->assertStatus(422);
    }

    public function test_punchout_rejects_wrong_credentials_and_revoked_access(): void {
        $data = $this->issueAccess();

        $this->punchout(['PASSWORD' => 'komplett-falsch'])->assertForbidden();

        $data['access']->forceFill(['revoked_at' => now()])->save();
        $this->punchout(['PASSWORD' => $data['secret']])->assertForbidden();
    }

    public function test_login_is_rate_limited(): void {
        $this->issueAccess();

        for ($i = 0; $i < 5; $i++) {
            $this->punchout(['PASSWORD' => 'falsch'])->assertForbidden();
        }
        $this->punchout(['PASSWORD' => 'falsch'])->assertStatus(429);
    }

    public function test_browse_shows_only_released_articles_of_own_tenant(): void {
        $data = $this->issueAccess();
        $released = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'WD-1001', 'name' => 'Freigegebener Artikel']);
        $unreleased = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'WD-2002', 'name' => 'Nicht freigegeben']);
        $this->release($data['access'], $released);

        // Fremder Mandant mit eigenem Zugang + Artikel — darf NIE erscheinen.
        $foreignOrg = Organization::factory()->create();
        $foreign = $this->issueAccess($foreignOrg, 'einkauf-fremd');
        $foreignArticle = Article::factory()->create(['organization_id' => $foreignOrg->id, 'number' => 'FREMD-9', 'name' => 'Fremdartikel']);
        app()->instance('currentOrganization', $this->organization);
        $this->release($foreign['access'], $foreignArticle);

        $redirect = $this->punchout(['PASSWORD' => $data['secret']]);
        $redirect->assertRedirect();

        $this->get($redirect->headers->get('Location'))
            ->assertOk()
            ->assertSee('WD-1001')
            ->assertSee('Freigegebener Artikel')
            ->assertDontSee('WD-2002')
            ->assertDontSee('FREMD-9');
    }

    public function test_cart_transfer_posts_oci_fields_with_article_number_and_custom_price(): void {
        $data = $this->issueAccess();
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'WD-1001',
            'name' => 'Katalogartikel',
            'default_sale_price' => '19.9000',
            'gtin' => '4012345678901',
        ]);
        $item = $this->release($data['access'], $article, '17.5000');

        $redirect = $this->punchout(['PASSWORD' => $data['secret']]);
        $location = (string) $redirect->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $token = (string) $query['t'];

        $response = $this->post('/b2b-katalog/' . $this->organization->slug . '/warenkorb', [
            't' => $token,
            'qty' => [$item->sqid => '3'],
        ]);

        $response->assertOk()
            ->assertSee('action="' . self::HOOK_URL . '"', false)
            ->assertSee('name="NEW_ITEM-MATNR[1]" value="WD-1001"', false)
            ->assertSee('name="NEW_ITEM-VENDORMAT[1]" value="WD-1001"', false)
            ->assertSee('name="NEW_ITEM-QUANTITY[1]" value="3"', false)
            ->assertSee('name="NEW_ITEM-PRICE[1]" value="17.5000"', false)
            ->assertSee('name="NEW_ITEM-CURRENCY[1]" value="EUR"', false)
            ->assertSee('name="NEW_ITEM-PRICEUNIT[1]" value="1"', false)
            ->assertSee('name="NEW_ITEM-UNIT[1]" value="C62"', false)
            ->assertSee('name="NEW_ITEM-EXT_PRODUCT_ID[1]" value="4012345678901"', false);

        // CSP erlaubt die HOOK_URL-Origin als form-action.
        $this->assertStringContainsString(
            'form-action \'self\' https://procurement.example.test',
            (string) $response->headers->get('Content-Security-Policy'),
        );
    }

    public function test_cart_adds_copper_surcharge_as_own_position(): void {
        // MVP-603: Artikel mit Kupferdaten + gepflegte DEL-Notierung →
        // eigene Warenkorbzeile; (2,00 − 1,50) × 0,043 kg = 0,0215 €/Einheit.
        $data = $this->issueAccess();
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'NYM-CU',
            'name' => 'Kabel NYM-J 3x1,5',
            'default_sale_price' => '1.9000',
            'copper_weight' => '0.0430',
            'copper_base_price' => '150.0000',
        ]);
        \App\Models\MetalQuotation::query()->create([
            'organization_id' => $this->organization->id,
            'metal' => 'CU', 'price_per_kg' => '2', 'quoted_at' => now()->toDateString(),
        ]);
        $item = $this->release($data['access'], $article, null);

        $redirect = $this->punchout(['PASSWORD' => $data['secret']]);
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->post('/b2b-katalog/' . $this->organization->slug . '/warenkorb', [
            't' => (string) $query['t'],
            'qty' => [$item->sqid => '10'],
        ])->assertOk()
            ->assertSee('name="NEW_ITEM-MATNR[1]" value="NYM-CU"', false)
            ->assertSee('name="NEW_ITEM-MATNR[2]" value="NYM-CU-CU"', false)
            ->assertSee('name="NEW_ITEM-PRICE[2]" value="0.0215"', false)
            ->assertSee('name="NEW_ITEM-QUANTITY[2]" value="10"', false);
    }

    public function test_tampered_or_missing_token_yields_410(): void {
        $this->get('/b2b-katalog/' . $this->organization->slug . '/katalog?t=manipuliert')
            ->assertStatus(410);
    }
}
