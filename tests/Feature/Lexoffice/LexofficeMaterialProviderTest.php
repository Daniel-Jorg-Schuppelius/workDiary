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
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/articles*' => FakePluginHttp::response([
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
        ]);
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
