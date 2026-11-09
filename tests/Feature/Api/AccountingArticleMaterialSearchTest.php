<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingArticleMaterialSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Finance\AccountingArticle;
use App\Models\Material\Material;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Material\MaterialProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Materialsuche der API über den Artikelspiegel der Buchhaltungsprogramme
 * (MVP-1500): `provider=accounting` und `provider=*` finden die Artikel
 * eingerichteter Programme und liefern buchbare Materialsätze.
 */
final class AccountingArticleMaterialSearchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function article(string $pluginId, string $externalId, string $name, array $attributes = []): AccountingArticle {
        return AccountingArticle::query()->withoutGlobalScopes()->create($attributes + [
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_id' => $externalId,
            'name' => $name, 'unit_name' => 'Stk', 'net_unit_price' => '4.2000', 'currency' => 'EUR', 'vat_rate' => '19.00', 'kind' => 'material', 'synced_at' => now(),
        ]);
    }

    public function test_accounting_provider_finds_articles_of_configured_programs_and_materialises_them(): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'sd']]);
        Material::query()->create(['organization_id' => $this->organization->id, 'sku' => 'KB-10', 'name' => 'Kabelbinder lokal', 'unit' => 'Stk']);
        $this->article(SevDeskPlugin::ID, 'part-1', 'Kabelbinder 200 mm', ['number' => 'KB-10']);
        $this->article(SevDeskPlugin::ID, 'part-2', 'Schrumpfschlauch', ['number' => 'SS-4']);
        $this->article(SevDeskPlugin::ID, 'part-3', 'Kabelbinder alt', ['archived_at' => now()]);
        // easybill ist nicht eingerichtet, eine fremde Organisation nie sichtbar.
        $this->article(EasybillPlugin::ID, 'pos-1', 'Kabelbinder easybill');
        $this->article(SevDeskPlugin::ID, 'part-9', 'Kabelbinder fremd', ['organization_id' => Organization::factory()->create()->id]);
        Sanctum::actingAs($this->admin, ['materials:read']);

        $response = $this->getJson(route('api.materials.index', ['provider' => 'accounting', 'q' => 'Kabelbinder']))->assertOk();
        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kabelbinder 200 mm')
            ->assertJsonPath('data.0.external_provider', SevDeskPlugin::ID)
            ->assertJsonPath('data.0.external_id', 'part-1')
            ->assertJsonPath('data.0.sku', null)
            ->assertJsonPath('data.0.default_unit_price', ['amount' => '4.2000', 'currency' => 'EUR'])
            ->assertJsonPath('data.0.tax_rate', ['value' => '19.00', 'scale' => 2]);
        $material = Material::query()->where('external_provider', SevDeskPlugin::ID)->firstOrFail();
        $this->assertSame((string) $material->sqid, $response->json('data.0.id'), 'buchbarer Materialsatz');

        // Erneute Suche legt nichts doppelt an; `*` vereint lokal und Spiegel ohne Dublette.
        $this->getJson(route('api.materials.index', ['provider' => '*', 'q' => 'Schrumpf']))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.sku', 'SS-4');
        $this->getJson(route('api.materials.index', ['provider' => '*', 'q' => 'Kabelbinder']))->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(2, Material::query()->where('external_provider', SevDeskPlugin::ID)->count());
    }

    public function test_accounting_provider_is_absent_without_a_configured_program(): void {
        $this->actingAs($this->admin);
        $this->assertNotContains('accounting', app(MaterialProviderRegistry::class)->names());

        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'eb']]);
        $this->assertContains('accounting', app(MaterialProviderRegistry::class)->names());
    }
}
