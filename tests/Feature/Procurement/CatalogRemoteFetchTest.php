<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogRemoteFetchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogItem, SupplierCatalogSource, User};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 050 (Remote-Quellen): HTTP-Abruf einer Katalogdatei, verschlüsselte
 * Zugangsdaten und SFTP-Sperre.
 */
final class CatalogRemoteFetchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attrs */
    private function source(array $attrs): SupplierCatalogSource {
        return SupplierCatalogSource::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'Remote', 'format' => 'datanorm', 'delimiter' => ';',
            'decimal_separator' => ',', 'encoding' => 'UTF-8', 'has_header' => true,
        ], $attrs));
    }

    public function test_http_fetch_imports_datanorm(): void {
        $source = $this->source(['source_type' => 'http', 'remote_url' => 'https://feed.example.com/cat.001']);
        Http::fake([
            'https://feed.example.com/*' => Http::response("A;N;900001;0;Pumpe;;0;1;Stk;4500;01;200;\nA;N;900002;0;Ventil;;0;1;Stk;1900;01;200;", 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.fetch', $source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $source->id)->count());
        $this->assertSame('45.0000', SupplierCatalogItem::query()->where('external_no', '900001')->firstOrFail()->purchase_price);
        Http::assertSent(fn ($request) => $request->url() === 'https://feed.example.com/cat.001');
    }

    public function test_http_fetch_csv_uses_persisted_mapping(): void {
        $source = $this->source([
            'format' => 'csv', 'source_type' => 'http', 'remote_url' => 'https://feed.example.com/list.csv',
            'mapping' => ['external_no' => 'ArtNr', 'name' => 'Bez', 'purchase_price' => 'EK'],
        ]);
        Http::fake(['https://feed.example.com/*' => Http::response("ArtNr;Bez;EK\nC-1;Klemme;2,40", 200)]);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.fetch', $source))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('2.4000', SupplierCatalogItem::query()->where('external_no', 'C-1')->firstOrFail()->purchase_price);
    }

    public function test_remote_password_is_stored_encrypted(): void {
        $source = $this->source(['source_type' => 'ftp', 'remote_host' => 'ftp.example.com', 'remote_password' => 'geheim123']);

        $this->assertSame('geheim123', $source->fresh()->remote_password); // entschlüsselt über Cast
        $raw = (string) DB::table('supplier_catalog_sources')->where('id', $source->id)->value('remote_password');
        $this->assertNotSame('geheim123', $raw); // roh verschlüsselt
        $this->assertNotEmpty($raw);
    }

    public function test_sftp_unreachable_host_flashes_error(): void {
        // SFTP wird über flysystem-sftp-v3 versucht; ein nicht erreichbarer Host
        // führt zu einem sauberen Fehler-Flash (Port 1 → sofort abgelehnt).
        $source = $this->source([
            'source_type' => 'sftp', 'remote_host' => '127.0.0.1', 'remote_port' => 1, 'remote_path' => '/catalog.csv',
        ]);

        $this->actingAs($this->admin)
            ->post(route('supplier-catalogs.fetch', $source))
            ->assertRedirect()->assertSessionHas('error');
    }

    public function test_fetch_requires_permission(): void {
        $source = $this->source(['source_type' => 'http', 'remote_url' => 'https://feed.example.com/cat.001']);
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->post(route('supplier-catalogs.fetch', $source))->assertForbidden();
    }
}
