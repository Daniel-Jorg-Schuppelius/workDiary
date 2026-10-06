<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BoqCallOffTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Gaeb;

use App\Enums\Gaeb\BoqCallOffStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Gaeb\{BillOfQuantity, BoqCallOff, BoqItem};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Gaeb\GaebImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-931: Rahmen-LV mit Abrufen, Restmengen und Abrechnung je Abruf. */
final class BoqCallOffTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private BillOfQuantity $boq;

    private BoqItem $item;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $xml = (string) file_get_contents(base_path('tests/Fixtures/gaeb/sample_x86.xml'));
        $import = app(GaebImportService::class)->import($xml, 'sample_x86.xml', $this->organization->id);
        $this->boq = BillOfQuantity::query()->findOrFail($import->bill_of_quantity_id);
        $this->boq->forceFill(['project_id' => $project->id])->save();
        $this->item = $this->boq->items()->where('reference_no', '01.0010')->firstOrFail(); // 100 × 12,50
    }

    private function callOff(string $quantity): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.store', $this->boq), [
            'title' => 'Abruf Treppenhaus', 'ordered_on' => '2026-09-01', 'due_on' => '2026-09-15',
            'quantities' => [$this->item->sqid => $quantity],
        ]);
    }

    public function test_call_offs_need_a_framework_and_respect_remaining_quantities(): void {
        $this->callOff('10')->assertSessionHasErrors('title');

        $this->actingAs($this->admin)->patch(route('bill-of-quantities.framework', $this->boq), ['is_framework' => 1])->assertSessionHas('success');
        $this->callOff('60')->assertSessionHas('success');
        $this->callOff('50')->assertSessionHasErrors('quantities');
        $this->callOff('40')->assertSessionHas('success');
        $this->callOff('0')->assertSessionHasErrors('quantities');

        $this->assertSame([1, 2], BoqCallOff::query()->orderBy('number')->pluck('number')->all());
        $html = (string) $this->actingAs($this->admin)->get(route('bill-of-quantities.call-offs.index', $this->boq))->assertOk()->getContent();
        $this->assertStringContainsString('Abruf Treppenhaus', $html);

        // Storno gibt die Menge wieder frei.
        $first = BoqCallOff::query()->where('number', 1)->sole();
        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.transition', $first), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->callOff('60')->assertSessionHas('success');
    }

    /** Konsolidierungs-Audit 2026-10 (k4-14): die Abrufe blättern, die Restmengen rechnen weiter über alle. */
    public function test_call_offs_page_while_remaining_quantities_count_all(): void {
        $this->boq->forceFill(['is_framework' => true])->save();
        foreach (range(1, 26) as $i) {
            $this->callOff('1')->assertSessionHas('success');
        }

        $first = $this->actingAs($this->admin)->get(route('bill-of-quantities.call-offs.index', $this->boq))->assertOk();
        $this->assertSame(26, $first->viewData('callOffs')->total());
        $this->assertSame(26, $first->viewData('callOffs')->items()[0]->number);

        $second = $this->get(route('bill-of-quantities.call-offs.index', [$this->boq, 'page' => 2]))->assertOk();
        $this->assertSame([1], array_map(static fn (BoqCallOff $c): int => $c->number, $second->viewData('callOffs')->items()));
        $row = collect($second->viewData('remaining'))->first(fn (array $r): bool => $r['item']->is($this->item));
        $this->assertSame(26.0, (float) $row['called']);
        $this->assertSame(74.0, (float) $row['remaining']);
    }

    public function test_call_off_is_invoiced_once_with_boq_prices(): void {
        $this->boq->forceFill(['is_framework' => true])->save();
        $this->callOff('8')->assertSessionHas('success');
        $callOff = BoqCallOff::query()->sole();

        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.invoice', $callOff))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.transition', $callOff), ['status' => 'ordered']);
        // Sicherheitsaudit 2026-10-04, li-7: ein zweiter Aufruf mit veraltetem Stand (zwei Tabs, Doppelklick) rechnet nicht noch einmal ab.
        $stale = BoqCallOff::query()->sole();
        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.invoice', $callOff))->assertRedirect();
        try {
            app(\App\Services\Gaeb\BoqCallOffService::class)->invoice($stale, $this->admin);
            $this->fail('Der zweite Aufruf hätte abgelehnt werden müssen.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $invoice = Invoice::query()->sole();
        $this->assertSame($this->boq->id, $invoice->bill_of_quantity_id);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('100.00', $invoice->subtotal?->getAmount());
        $this->assertSame($invoice->id, $callOff->fresh()?->invoice_id);

        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.invoice', $callOff))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.transition', $callOff), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame(BoqCallOffStatus::Ordered, $callOff->fresh()?->status);
    }

    public function test_foreign_items_and_rights(): void {
        $this->boq->forceFill(['is_framework' => true])->save();
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->post(route('bill-of-quantities.call-offs.store', $this->boq), ['title' => 'x', 'quantities' => [$this->item->sqid => '1']])->assertForbidden();

        $other = BillOfQuantity::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = BoqItem::factory()->create(['organization_id' => $this->organization->id, 'bill_of_quantity_id' => $other->id]);
        $this->actingAs($this->admin)->post(route('bill-of-quantities.call-offs.store', $this->boq), ['title' => 'x', 'quantities' => [$foreign->sqid => '1']])->assertSessionHasErrors('quantities');
    }
}
