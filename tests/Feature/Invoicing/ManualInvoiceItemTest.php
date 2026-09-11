<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualInvoiceItemTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, Invoice, Project, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ManualInvoiceItemTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_add_item_to_draft_updates_totals(): void {
        $invoice = $this->makeDraft();

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), [
                'description' => 'Beratung',
                'quantity' => '3',
                'unit' => 'Std.',
                'unit_price' => '100.00',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('300.00', $invoice->subtotal?->getAmount());
        $this->assertSame('57.00', $invoice->tax_amount?->getAmount());
        $this->assertSame('357.00', $invoice->total?->getAmount());
    }

    public function test_update_item_recalculates_totals(): void {
        $invoice = $this->makeDraft();
        $item = $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Foo',
            'quantity' => '1',
            'unit' => 'Std.',
            'unit_price' => '50.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $this->actingAs($this->admin)
            ->put(route('invoices.items.update', [$invoice, $item]), [
                'description' => 'Foo angepasst',
                'quantity' => '2',
                'unit' => 'Std.',
                'unit_price' => '75.00',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('150.00', $invoice->subtotal?->getAmount());
        $this->assertSame('Foo angepasst', $invoice->items()->first()?->description);
    }

    public function test_delete_item_recalculates_totals(): void {
        $invoice = $this->makeDraft();
        $item = $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'X',
            'quantity' => '4',
            'unit' => 'Std.',
            'unit_price' => '50.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();
        $this->assertSame('200.00', $invoice->subtotal?->getAmount());

        $this->actingAs($this->admin)
            ->delete(route('invoices.items.destroy', [$invoice, $item]))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(0, $invoice->items()->count());
        $this->assertSame('0.00', $invoice->subtotal?->getAmount());
        $this->assertSame('0.00', $invoice->total?->getAmount());
    }

    public function test_service_period_is_saved_validated_and_shown_per_item(): void {
        $invoice = $this->makeDraft();
        $payload = ['description' => 'Microsoft 365 Business Premium', 'quantity' => '2', 'unit' => 'St.', 'unit_price' => '247.20'];

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), $payload + ['service_from' => '2025-08-05', 'service_to' => '2026-08-04'])
            ->assertRedirect();

        $item = $invoice->items()->firstOrFail();
        $this->assertSame('2025-08-05', $item->service_from?->toDateString());
        $this->assertSame('2026-08-04', $item->service_to?->toDateString());
        $this->assertNull($item->service_date, 'Leistungsdatum bleibt eigenes Feld');
        $this->assertSame('05.08.2025 – 04.08.2026', $item->servicePeriodLabel());

        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('invoicing.item.service_period') . ': 05.08.2025 – 04.08.2026');

        // Ende vor Beginn bzw. Ende ohne Beginn sind Eingabefehler.
        $this->actingAs($this->admin)
            ->put(route('invoices.items.update', [$invoice, $item]), $payload + ['service_from' => '2026-08-04', 'service_to' => '2025-08-05'])
            ->assertSessionHasErrors('service_to');
        $this->actingAs($this->admin)
            ->put(route('invoices.items.update', [$invoice, $item]), $payload + ['service_to' => '2026-08-04'])
            ->assertSessionHasErrors('service_from');

        // Nur Beginn: Label zeigt den Beginn; ohne Zeitraum fällt es aufs Leistungsdatum zurück.
        $this->actingAs($this->admin)
            ->put(route('invoices.items.update', [$invoice, $item]), $payload + ['service_from' => '2025-08-05'])
            ->assertRedirect();
        $item->refresh();
        $this->assertNull($item->service_to);
        $this->assertSame('05.08.2025', $item->servicePeriodLabel());

        $this->actingAs($this->admin)
            ->put(route('invoices.items.update', [$invoice, $item]), $payload + ['service_date' => '2026-09-11'])
            ->assertRedirect();
        $item->refresh();
        $this->assertNull($item->service_from);
        $this->assertSame('11.09.2026', $item->servicePeriodLabel());
    }

    public function test_cannot_add_item_to_issued_invoice(): void {
        $invoice = $this->makeDraft();
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);

        $this->actingAs($this->admin)
            ->post(route('invoices.items.store', $invoice), [
                'description' => 'Late',
                'quantity' => '1',
                'unit_price' => '10',
            ])
            ->assertForbidden();
    }

    public function test_non_billing_user_forbidden(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $invoice = $this->makeDraft();

        $this->actingAs($user)
            ->post(route('invoices.items.store', $invoice), [
                'description' => 'X',
                'quantity' => '1',
                'unit_price' => '10',
            ])
            ->assertForbidden();
    }

    public function test_item_from_other_invoice_returns_404(): void {
        $a = $this->makeDraft();
        $b = $this->makeDraft();
        $item = $a->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'X',
            'quantity' => '1',
            'unit' => 'Std.',
            'unit_price' => '10',
            'position' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('invoices.items.destroy', [$b, $item]))
            ->assertNotFound();
    }

    private function makeDraft(): Invoice {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
            'number' => 'R2030-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
    }
}
