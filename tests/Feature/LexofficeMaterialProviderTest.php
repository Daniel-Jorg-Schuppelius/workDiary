<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMaterialProviderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Services\Material\MaterialProviderRegistry;
use App\Services\Material\Provider\LexofficeMaterialProvider;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LexofficeMaterialProviderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        Config::set('timesheet.providers.lexoffice.api_key', 'test-key');
    }

    public function test_search_calls_lexoffice_and_upserts_local_materials(): void {
        Http::fake([
            'https://api.lexoffice.io/v1/articles*' => Http::response([
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

        $provider = (new MaterialProviderRegistry)->get('lexoffice');
        $this->assertInstanceOf(LexofficeMaterialProvider::class, $provider);
        $results = $provider->search('switch', 10);

        $this->assertNotEmpty($results);
        $this->assertDatabaseHas('materials', [
            'external_provider' => 'lexoffice',
            'external_id' => 'lex-1',
            'name' => 'Switch 24-Port',
        ]);
    }
}
