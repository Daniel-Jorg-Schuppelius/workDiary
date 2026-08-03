<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogScheduledFetchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Supplier, SupplierCatalogImport, SupplierCatalogItem, SupplierCatalogSource};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 050, MVP-091: geplanter Remote-Abruf (catalog:fetch-due) mit
 * Lauf-Protokoll und next_fetch_at-Fortschreibung.
 */
final class CatalogScheduledFetchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attrs */
    private function source(array $attrs = []): SupplierCatalogSource {
        return SupplierCatalogSource::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id,
            'name' => 'Feed', 'format' => 'datanorm', 'delimiter' => ';', 'decimal_separator' => ',',
            'encoding' => 'UTF-8', 'has_header' => true,
            'source_type' => 'http', 'remote_url' => 'https://feed.example.com/cat.001',
            'fetch_interval_minutes' => 60,
        ], $attrs));
    }

    private function fakeDatanorm(): FakePluginHttp {
        return FakePluginHttp::fake(['https://feed.example.com/*' => FakePluginHttp::response('A;N;700001;0;Rohr;;0;1;m;1500;01;100;', 200)]);
    }

    public function test_command_fetches_due_source_and_logs(): void {
        $source = $this->source(['next_fetch_at' => null]); // null = fällig
        $this->fakeDatanorm();

        $this->artisan('catalog:fetch-due')->assertExitCode(0);

        $this->assertSame(1, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $source->id)->count());
        $this->assertDatabaseHas('supplier_catalog_imports', [
            'supplier_catalog_source_id' => $source->id,
            'trigger' => SupplierCatalogImport::TRIGGER_SCHEDULED,
            'status' => SupplierCatalogImport::STATUS_SUCCESS,
            'created' => 1,
        ]);
        $this->assertTrue($source->fresh()->next_fetch_at->isFuture());
    }

    public function test_command_skips_source_not_yet_due(): void {
        $source = $this->source(['next_fetch_at' => Carbon::now()->addHour()]);
        $fake = $this->fakeDatanorm();

        $this->artisan('catalog:fetch-due')->assertExitCode(0);

        $this->assertSame(0, SupplierCatalogItem::query()->where('supplier_catalog_source_id', $source->id)->count());
        $fake->assertNothingSent();
    }

    public function test_command_logs_fetch_failure(): void {
        $source = $this->source(['next_fetch_at' => null]);
        FakePluginHttp::fake(['https://feed.example.com/*' => FakePluginHttp::response('nope', 500)]);

        $this->artisan('catalog:fetch-due')->assertExitCode(0);

        $this->assertDatabaseHas('supplier_catalog_imports', [
            'supplier_catalog_source_id' => $source->id,
            'trigger' => SupplierCatalogImport::TRIGGER_SCHEDULED,
            'status' => SupplierCatalogImport::STATUS_ERROR,
        ]);
        $this->assertTrue($source->fresh()->next_fetch_at->isFuture()); // trotzdem neu terminiert
    }

    public function test_command_ignores_upload_and_inactive_sources(): void {
        $this->source(['source_type' => 'upload', 'next_fetch_at' => null]);
        $this->source(['active' => false, 'next_fetch_at' => null]);
        $this->source(['fetch_interval_minutes' => 0, 'next_fetch_at' => null]);
        $fake = $this->fakeDatanorm();

        $this->artisan('catalog:fetch-due')->assertExitCode(0);

        $fake->assertNothingSent();
        $this->assertSame(0, SupplierCatalogImport::query()->count());
    }
}
