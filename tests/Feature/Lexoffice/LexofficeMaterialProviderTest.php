<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMaterialProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Lexoffice;

use App\Plugins\Lexoffice\Services\LexofficeMaterialProvider;
use App\Services\Material\MaterialProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class LexofficeMaterialProviderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
    }

    public function test_search_calls_lexoffice_and_upserts_local_materials(): void {
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/articles*' => FakePluginHttp::response([
                'content' => [
                    [
                        'id' => 'lex-1',
                        'title' => 'Switch 24-Port',
                        'unitName' => 'Stk',
                        'price' => ['netPrice' => 250.00],
                    ],
                ],
            ], 200),
        ]);

        $provider = app(MaterialProviderRegistry::class)->get('lexoffice');
        $this->assertInstanceOf(LexofficeMaterialProvider::class, $provider);
        $results = $provider->search('switch', 10);

        $this->assertNotEmpty($results);
        $this->assertDatabaseHas('materials', [
            'external_provider' => 'lexoffice',
            'external_id' => 'lex-1',
            'name' => 'Switch 24-Port',
            'default_unit_price' => '250.0000',
        ]);
        // ArticlesEndpoint::search() mit denselben Parametern wie zuvor.
        $fake->assertSentCount(1);
        $this->assertSame('GET https://api.lexware.io/v1/articles?query=switch&size=10', $fake->recorded()[0]['request']->getMethod() . ' ' . $fake->recorded()[0]['request']->getUri());
    }

    /** sync() blättert über `last`, ein Fehler beendet den Lauf ohne Ausnahme. */
    public function test_sync_pages_until_last_and_stops_on_error(): void {
        $page = static fn (string $id, bool $last): \GuzzleHttp\Psr7\Response => FakePluginHttp::response([
            'content' => [['id' => $id, 'title' => 'Artikel ' . $id, 'type' => 'PRODUCT', 'unitName' => 'Stk', 'price' => ['netPrice' => 10.0, 'taxRate' => 19.0]]],
            'last' => $last,
            'totalPages' => 2,
        ]);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/articles*' => [$page('lex-a', false), $page('lex-b', true)],
        ]);

        $provider = app(MaterialProviderRegistry::class)->get('lexoffice');
        $this->assertInstanceOf(LexofficeMaterialProvider::class, $provider);
        $this->assertSame(2, $provider->sync());
        $this->assertSame(
            ['https://api.lexware.io/v1/articles?page=0&size=100', 'https://api.lexware.io/v1/articles?page=1&size=100'],
            array_map(static fn (array $entry): string => (string) $entry['request']->getUri(), $fake->recorded()),
        );
        $this->assertDatabaseHas('materials', ['external_id' => 'lex-b', 'tax_rate' => '19.00']);

        FakePluginHttp::fake(['https://api.lexware.io/v1/articles*' => FakePluginHttp::response(['message' => 'forbidden'], 403)]);
        $this->assertSame(0, app(MaterialProviderRegistry::class)->get('lexoffice')?->sync());
    }

    public function test_lexoffice_source_needs_the_key_of_the_current_organization(): void {
        $this->assertSame(['local', 'lexoffice'], app(MaterialProviderRegistry::class)->names());

        $other = \App\Models\Platform\Organization::factory()->create();
        \App\Support\OrganizationContext::run($other, function (): void {
            $this->assertNull(app(MaterialProviderRegistry::class)->get('lexoffice'), 'ohne Schlüssel der Organisation keine Lexoffice-Quelle');
            $this->assertSame(['local'], app(MaterialProviderRegistry::class)->names());
        });
    }
}
