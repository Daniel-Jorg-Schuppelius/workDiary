<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillOfQuantityImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, GaebImportStatus};
use App\Models\{BillOfQuantity, BoqItem, User};
use App\Services\Gaeb\{BoqImportConflictException, GaebImportService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 049, MVP-081/082: GAEB-Import (Service + HTTP). Preflight blockiert
 * fehlerhafte Dateien, sauberer Import persistiert LV/Abschnitte/Positionen
 * inkl. Preis-Snapshots, und ein Reimport schützt Positionen mit
 * Ausführungsbezug.
 */
final class BillOfQuantityImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private GaebImportService $importer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->importer = app(GaebImportService::class);
    }

    private function fixture(): string {
        return (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
    }

    public function test_clean_import_persists_boq_with_items_and_snapshots(): void {
        $import = $this->importer->import($this->fixture(), 'sample_x86.xml', $this->organization->id);

        $this->assertSame(GaebImportStatus::Imported, $import->status);
        $this->assertSame(4, $import->item_count);

        $boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
        $this->assertSame('Neubau Lagerhalle Musterstadt', $boq->name);
        $this->assertSame(2, $boq->sections()->count());
        $this->assertSame(4, $boq->items()->count());

        // Positionen mit Preis erzeugen je einen Snapshot (3 von 4).
        $this->assertDatabaseCount('boq_item_price_snapshots', 3);

        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();
        $this->assertSame('Boden lösen', $item->short_text);
        $this->assertSame('m3', $item->unit);
        $this->assertSame(BoqItemStatus::Imported, $item->status);
    }

    public function test_preflight_failure_writes_no_boq(): void {
        $import = $this->importer->import('<root><nope/></root>', 'broken.xml', $this->organization->id);

        $this->assertSame(GaebImportStatus::PreflightFailed, $import->status);
        $this->assertNull($import->bill_of_quantity_id);
        $this->assertSame(0, BillOfQuantity::query()->count());
    }

    public function test_reimport_blocks_items_with_execution_reference(): void {
        $import = $this->importer->import($this->fixture(), 'sample_x86.xml', $this->organization->id);
        $boqId = (int) $import->bill_of_quantity_id;

        // Eine Position als beauftragt markieren → Ausführungsbezug.
        BoqItem::query()->where('bill_of_quantity_id', $boqId)
            ->where('reference_no', '01.0010')
            ->update(['status' => BoqItemStatus::Ordered->value]);

        $this->expectException(BoqImportConflictException::class);
        $this->importer->import($this->fixture(), 'sample_x86.xml', $this->organization->id, [
            'bill_of_quantity_id' => $boqId,
        ]);
    }

    public function test_reimport_without_execution_reference_replaces_structure(): void {
        $import = $this->importer->import($this->fixture(), 'sample_x86.xml', $this->organization->id);
        $boqId = (int) $import->bill_of_quantity_id;

        $reimport = $this->importer->import($this->fixture(), 'sample_x86.xml', $this->organization->id, [
            'bill_of_quantity_id' => $boqId,
        ]);

        $this->assertSame(GaebImportStatus::Imported, $reimport->status);
        $this->assertSame($boqId, (int) $reimport->bill_of_quantity_id);
        // Keine Dubletten: weiterhin genau 4 Positionen.
        $this->assertSame(4, BoqItem::query()->where('bill_of_quantity_id', $boqId)->count());
    }

    public function test_http_import_requires_permission_and_redirects(): void {
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->get(route('bill-of-quantities.index'))->assertOk();

        // Import erfordert project.import — Basis-Benutzer ohne diese Berechtigung.
        $strangerFile = new UploadedFile(base_path('tests/Fixtures/gaeb/sample_x86.xml'), 'sample_x86.xml', 'text/xml', null, true);
        $this->actingAs($stranger)
            ->post(route('bill-of-quantities.import'), ['file' => $strangerFile])
            ->assertForbidden();

        $file = new UploadedFile(base_path('tests/Fixtures/gaeb/sample_x86.xml'), 'sample_x86.xml', 'text/xml', null, true);
        $this->actingAs($admin)
            ->post(route('bill-of-quantities.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('bill_of_quantities', [
            'organization_id' => $this->organization->id,
            'name' => 'Neubau Lagerhalle Musterstadt',
        ]);
    }
}
