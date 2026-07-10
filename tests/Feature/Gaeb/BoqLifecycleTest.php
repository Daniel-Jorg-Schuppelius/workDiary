<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqLifecycleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqProgressSource, GaebPhase};
use App\Models\{BillOfQuantity, BoqItem, User};
use App\Services\Gaeb\{BoqCostingService, BoqExportService, BoqProgressService, BoqWorkflowException, BoqWorkflowService, GaebDaXmlParser, GaebImportService};
use Database\Seeders\{GaebDemoSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 049, MVP-083..086: Aufmaß/Fortschritt + Nachkalkulation, LV-Workflow
 * (Status/Nachtrag/Restleistung), GAEB-Export-Roundtrip und Demo-Daten.
 */
final class BoqLifecycleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function importedBoq(): BillOfQuantity {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x86.xml', $this->organization->id);

        return BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
    }

    public function test_progress_updates_executed_remaining_and_status(): void {
        $boq = $this->importedBoq();
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();

        app(BoqProgressService::class)->record($item, '60', ['source' => BoqProgressSource::Measurement]);

        $item->refresh();
        $this->assertSame(60.0, $item->executedQuantity());
        $this->assertSame(40.0, $item->remainingQuantity());
        $this->assertSame(BoqItemStatus::InProgress, $item->status);
    }

    public function test_costing_summarizes_planned_and_executed(): void {
        $boq = $this->importedBoq();
        // Abrechenbar mit Preis: 01.0010 (100×12.50) + 02.0010 (250×89.90).
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();
        app(BoqProgressService::class)->record($item, '100');

        $summary = app(BoqCostingService::class)->summarize($boq->fresh() ?? $boq);

        $this->assertEqualsWithDelta(1250.0 + 22475.0, $summary['planned'], 0.01);
        $this->assertEqualsWithDelta(1250.0, $summary['executed'], 0.01);
    }

    public function test_workflow_guards_invalid_transitions(): void {
        $boq = $this->importedBoq();
        $workflow = app(BoqWorkflowService::class);

        $workflow->transitionBill($boq, BoqItemStatus::Ordered); // imported → ordered erlaubt
        $this->assertSame(BoqItemStatus::Ordered, $boq->fresh()?->status);

        $this->expectException(BoqWorkflowException::class);
        $workflow->transitionBill($boq, BoqItemStatus::Quoted); // ordered → quoted verboten
    }

    public function test_addendum_is_created_as_own_item(): void {
        $boq = $this->importedBoq();
        $before = $boq->items()->count();

        $item = app(BoqWorkflowService::class)->createAddendum($boq, [
            'reference_no' => 'N01',
            'short_text' => 'Nachtrag',
            'quantity' => '5',
            'unit' => 'm',
            'unit_price' => '10',
        ]);

        $this->assertTrue($item->is_addendum);
        $this->assertSame(BoqItemStatus::Draft, $item->status);
        $this->assertSame($before + 1, $boq->items()->count());
    }

    public function test_remaining_items_excludes_completed(): void {
        $boq = $this->importedBoq();
        $workflow = app(BoqWorkflowService::class);

        // 02.0010 voll aufmessen und abschließen → fällt aus der Restleistung.
        $done = $boq->items()->where('reference_no', '02.0010')->firstOrFail();
        app(BoqProgressService::class)->record($done, '250');
        $done->forceFill(['status' => BoqItemStatus::Completed])->save();

        $remainingRefs = $workflow->remainingItems($boq)->pluck('reference_no')->all();

        $this->assertContains('01.0010', $remainingRefs);
        $this->assertNotContains('02.0010', $remainingRefs);
    }

    public function test_export_roundtrips_and_is_recorded(): void {
        $boq = $this->importedBoq();
        $service = app(BoqExportService::class);

        $result = $service->export($boq, GaebPhase::Award);

        // Protokolliert.
        $this->assertDatabaseHas('boq_exports', [
            'bill_of_quantity_id' => $boq->id,
            'phase' => GaebPhase::Award->value,
            'file_hash' => $result['export']->file_hash,
        ]);

        // Deterministisch: gleicher Inhalt → gleicher Hash.
        $this->assertSame($result['export']->file_hash, $service->contentHash($boq, GaebPhase::Award));

        // Roundtrip: Export erneut parsen → gleiche Ordnungszahlen.
        $parsed = (new GaebDaXmlParser)->parse($result['xml']);
        $refs = array_column($parsed->items, 'ref');
        $this->assertEqualsCanonicalizing(['01.0001', '01.0010', '01.0020', '02.0010'], $refs);
    }

    public function test_http_export_and_progress_for_manager(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $boq = $this->importedBoq();
        $item = $boq->items()->where('reference_no', '01.0010')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('bill-of-quantities.items.progress', $item), ['quantity' => '25'])
            ->assertRedirect();
        $this->assertSame(25.0, $item->fresh()?->executedQuantity());

        $this->actingAs($admin)
            ->get(route('bill-of-quantities.export', $boq))
            ->assertOk()
            ->assertHeader('content-type', 'application/xml');
    }

    public function test_demo_seeder_builds_full_flow(): void {
        (new GaebDemoSeeder)->run($this->organization);

        $boq = BillOfQuantity::query()->where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertTrue($boq->items()->where('is_addendum', true)->exists());
        $this->assertTrue(BoqItem::query()->where('bill_of_quantity_id', $boq->id)->whereHas('progress')->exists());
        $this->assertDatabaseHas('boq_exports', ['bill_of_quantity_id' => $boq->id]);
    }
}
