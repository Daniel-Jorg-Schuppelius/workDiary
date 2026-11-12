<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqProgressReferenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\{BoqItemStatus, BoqProgressSource, GaebImportStatus};
use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Customer\Customer;
use App\Models\Gaeb\{BillOfQuantity, BoqItem, BoqItemProgress, GaebImport};
use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Protocol\Protocol;
use App\Models\Time\Timesheet;
use App\Services\Gaeb\{BoqImportConflictException, GaebImportService};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1582: LV-Fortschritt mit Bezug auf Materialverbrauch und Protokoll,
 * Konflikteintrag des abgewiesenen Reimports, Befund ohne Katalogausgabe.
 */
final class BoqProgressReferenceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Project $project;
    private BillOfQuantity $boq;
    private BoqItem $item;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);

        $import = app(GaebImportService::class)->import($this->fixture(), 'sample_x86.xml', $this->organization->id, ['project_id' => $this->project->id]);
        $this->boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
        $this->item = $this->boq->items()->where('reference_no', '01.0010')->firstOrFail();
    }

    private function fixture(): string {
        return (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
    }

    private function usage(Project $project): MaterialUsage {
        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id, 'project_id' => $project->id, 'user_id' => $this->admin->id,
            'work_date' => '2026-06-12', 'status' => TimesheetStatus::Draft->value,
        ]);

        return MaterialUsage::create([
            'organization_id' => $this->organization->id, 'timesheet_id' => $timesheet->id,
            'description' => 'Kalksandstein', 'quantity' => 2, 'unit' => 'Pal', 'unit_price' => 25, 'billed' => false,
        ]);
    }

    public function test_material_usage_is_linked_once_and_sets_the_source(): void {
        $usage = $this->usage($this->project);

        $this->actingAs($this->admin)->get(route('bill-of-quantities.items.progress.create', $this->item))
            ->assertOk()->assertSee('Kalksandstein');

        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $this->item), [
            'quantity' => '0', 'material_usage' => $usage->sqid,
        ])->assertSessionHasNoErrors();

        $progress = BoqItemProgress::query()->sole();
        $this->assertSame(BoqProgressSource::Material, $progress->source);
        $this->assertSame($usage->id, $progress->material_usage_id);

        // Ein Verbrauch hat genau ein Ziel: die zweite Zuordnung wird abgewiesen.
        $other = $this->boq->items()->where('reference_no', '!=', '01.0010')->firstOrFail();
        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $other), [
            'quantity' => '0', 'material_usage' => $usage->sqid,
        ])->assertSessionHasErrors('material_usage');

        // Verbrauch eines fremden Projekts ebenso.
        $foreign = $this->usage(Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->project->customer_id]));
        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $this->item), [
            'quantity' => '1', 'material_usage' => $foreign->sqid,
        ])->assertSessionHasErrors('material_usage');
        $this->assertSame(1, BoqItemProgress::query()->count());
    }

    public function test_protocol_reference_is_stored_and_shown(): void {
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id, 'subject_type' => MorphMap::alias(Project::class),
            'subject_id' => $this->project->id, 'title' => 'Abnahme Rohbau', 'created_by_user_id' => $this->admin->id,
        ]);
        $foreign = Protocol::factory()->create([
            'organization_id' => $this->organization->id, 'subject_type' => MorphMap::alias(Project::class),
            'subject_id' => Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->project->customer_id])->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $this->item), [
            'quantity' => '12.5', 'protocol' => $foreign->sqid,
        ])->assertSessionHasErrors('protocol');

        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $this->item), [
            'quantity' => '12.5', 'protocol' => $protocol->sqid, 'note' => 'laut Abnahme',
        ])->assertSessionHasNoErrors();

        $progress = BoqItemProgress::query()->sole();
        $this->assertSame(BoqProgressSource::Protocol, $progress->source);
        $this->assertSame($protocol->id, $progress->protocol_id);
        $this->assertSame(12.5, $this->item->fresh()?->executedQuantity());

        $this->actingAs($this->admin)->get(route('bill-of-quantities.items.progress.create', $this->item))
            ->assertOk()->assertSee('Abnahme Rohbau')->assertSee(route('protocols.show', $protocol), false);

        // Die Quelle „Protokoll“ ohne Protokoll gibt es nicht mehr.
        $this->actingAs($this->admin)->post(route('bill-of-quantities.items.progress', $this->item), [
            'quantity' => '1', 'source' => 'protocol',
        ])->assertSessionHasErrors('source');
    }

    public function test_rejected_reimport_leaves_a_conflict_entry_with_comparison(): void {
        BoqItem::query()->whereKey($this->item->id)->update(['status' => BoqItemStatus::Ordered->value]);

        try {
            app(GaebImportService::class)->import($this->fixture(), 'sample_x86.xml', $this->organization->id, [
                'bill_of_quantity_id' => $this->boq->id,
            ]);
            $this->fail('Reimport wurde nicht abgewiesen.');
        } catch (BoqImportConflictException $e) {
            $this->assertSame(['01.0010'], $e->conflictingRefs);
            $this->assertInstanceOf(GaebImport::class, $e->import);
        }

        $entry = GaebImport::query()->where('status', GaebImportStatus::Conflict->value)->sole();
        $this->assertSame($this->boq->id, $entry->bill_of_quantity_id);
        $this->assertFalse($entry->preflight['ok']);
        $conflict = $entry->preflight['conflicts'][0];
        $this->assertSame('01.0010', $conflict['ref']);
        $this->assertSame('ordered', $conflict['status']);
        $this->assertArrayHasKey('incoming', $conflict['quantity']);
        // Der Bestand bleibt unberührt.
        $this->assertSame(4, BoqItem::query()->where('bill_of_quantity_id', $this->boq->id)->count());

        $this->actingAs($this->admin)->get(route('bill-of-quantities.show', $this->boq))
            ->assertOk()->assertSee(GaebImportStatus::Conflict->label());
    }

    public function test_cost_group_catalog_without_edition_is_a_visible_finding(): void {
        $xml = str_replace('cost group DIN 276 2018-12', 'cost group DIN 276', $this->fixture());

        $import = app(GaebImportService::class)->import($xml, 'ohne_ausgabe.xml', $this->organization->id);

        $this->assertSame(GaebImportStatus::Imported, $import->status);
        $finding = __('gaeb.preflight.catalog_without_edition', ['catalog' => 'DIN 276-18']);
        $this->assertContains($finding, $import->preflight['warnings']);

        $this->actingAs($this->admin)->get(route('bill-of-quantities.show', $import->billOfQuantity))
            ->assertOk()->assertSee($finding);

        // Mit Ausgabe kein Befund.
        $clean = app(GaebImportService::class)->import($this->fixture(), 'mit_ausgabe.xml', $this->organization->id);
        $this->assertNotContains($finding, $clean->preflight['warnings']);
    }

    public function test_progress_can_be_reported_from_the_protocol_page(): void {
        $protocol = Protocol::factory()->create([
            'organization_id' => $this->organization->id, 'subject_type' => MorphMap::alias(Project::class),
            'subject_id' => $this->project->id, 'title' => 'Bautagesbericht', 'created_by_user_id' => $this->admin->id,
        ]);
        $withoutBoq = Protocol::factory()->create([
            'organization_id' => $this->organization->id, 'subject_type' => MorphMap::alias(Project::class),
            'subject_id' => Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->project->customer_id])->id,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->get(route('protocols.show', $protocol))->assertOk()->assertSee(__('protocol.boq_progress.action'));
        $this->actingAs($this->admin)->get(route('protocols.show', $withoutBoq))->assertOk()->assertDontSee(__('protocol.boq_progress.action'));
        $this->actingAs($this->admin)->get(route('protocols.boq-progress.create', $withoutBoq))->assertNotFound();
        $this->actingAs($this->admin)->get(route('protocols.boq-progress.create', $protocol))->assertOk()->assertSee('01.0010');

        $this->actingAs($this->admin)->post(route('protocols.boq-progress.store', $protocol), [
            'position' => $this->item->sqid, 'quantity' => '7.5', 'note' => 'laut Bericht',
        ])->assertRedirect(route('protocols.show', $protocol))->assertSessionHasNoErrors();

        $progress = BoqItemProgress::query()->sole();
        $this->assertSame(BoqProgressSource::Protocol, $progress->source);
        $this->assertSame($protocol->id, $progress->protocol_id);
        $this->assertSame($this->item->id, $progress->boq_item_id);

        // Position eines fremden Projekts wird abgewiesen.
        $this->actingAs($this->admin)->post(route('protocols.boq-progress.store', $withoutBoq), [
            'position' => $this->item->sqid, 'quantity' => '1',
        ])->assertSessionHasErrors('position');
    }
}
