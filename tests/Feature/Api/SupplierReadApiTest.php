<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Organization, Supplier, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Lieferanten (ohne Bank-/Steuerdaten). */
final class SupplierReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['customers:read']);

        $this->getJson(route('api.suppliers.index'))->assertForbidden();
    }

    public function test_index_search_archived_and_pagination(): void {
        Supplier::factory()->count(2)->create(['organization_id' => $this->organization->id, 'name' => 'Stahl Nord']);
        Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Holz Süd']);
        Supplier::factory()->archived()->create(['organization_id' => $this->organization->id, 'name' => 'Alt GmbH']);
        Sanctum::actingAs($this->admin, ['suppliers:read']);

        $page = $this->getJson(route('api.suppliers.index', ['per_page' => 2]))->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(3, $page->json('meta.total'), 'Archivierte sind ohne archived=1 ausgeblendet.');

        $this->assertSame(4, $this->getJson(route('api.suppliers.index', ['archived' => 1]))->json('meta.total'));

        $search = $this->getJson(route('api.suppliers.index', ['search' => 'Stahl']))->assertOk();
        $this->assertCount(2, $search->json('data'));
    }

    public function test_show_hides_bank_and_tax_details(): void {
        $supplier = Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_iban' => 'DE02120300000000202051',
            'tax_number' => '12/345/67890',
        ]);
        Sanctum::actingAs($this->admin, ['suppliers:read']);

        $response = $this->getJson(route('api.suppliers.show', $supplier))->assertOk();
        $response->assertJsonPath('data.id', $supplier->sqid)
            ->assertJsonMissingPath('data.bank_iban')
            ->assertJsonMissingPath('data.bank_bic')
            ->assertJsonMissingPath('data.tax_number')
            ->assertJsonMissingPath('data.contact_persons');
        $this->assertStringNotContainsString('DE0212030000', (string) $response->getContent());
    }

    public function test_foreign_organization_supplier_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = Supplier::factory()->create(['organization_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['suppliers:read']);

        $this->getJson(route('api.suppliers.show', $foreign))->assertNotFound();
    }
}
