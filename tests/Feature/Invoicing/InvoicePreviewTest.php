<?php
/*
 * Created on   : Fri Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePreviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Invoice, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rechnungs-Vorschau (MVP-462): read-only, Ausschluss-Checkboxen wirken im
 * Store, Quell-Zeiten auf der Rechnungsansicht.
 */
class InvoicePreviewTest extends TestCase {
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

    private function entry(string $date, int $minutes, string $description): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => $date,
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
            'description' => $description,
        ]);
    }

    public function test_preview_shows_totals_and_consumes_nothing(): void {
        $entry = $this->entry('2030-04-01', 90, 'Vorschau-Eintrag');

        $response = $this->actingAs($this->admin)->post(route('invoices.preview'), [
            'customer_id' => $this->customer->sqid,
        ]);

        $response->assertOk();
        $response->assertSee('Vorschau-Eintrag');
        // 90 min → "1:30 h (1,50 h)".
        $response->assertSee('1:30');
        $response->assertSee('1,50');

        // Read-only: nichts verbraucht, keine Rechnung, keine Nummer.
        $this->assertFalse($entry->refresh()->exported);
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_preview_validates_customer(): void {
        $this->actingAs($this->admin)
            ->post(route('invoices.preview'), ['customer_id' => 'unbekannt'])
            ->assertSessionHasErrors('customer_id');
    }

    public function test_excluded_entries_stay_open_in_store(): void {
        // Getrennte Tage → getrennte Blöcke, damit der Ausschluss einen ganzen
        // Block entfernt und nicht nur ein Blockmitglied.
        $kept = $this->entry('2030-04-01', 60, 'Bleibt drin');
        $excluded = $this->entry('2030-04-03', 120, 'Wird ausgeschlossen');

        $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->sqid,
            'project_id' => $this->project->sqid,
            'excluded_time_entry_ids' => [$excluded->sqid],
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertTrue($kept->refresh()->exported);
        $this->assertFalse($excluded->refresh()->exported, 'Ausgeschlossener Eintrag bleibt offen.');
        // Nur die Stunde des verbliebenen Eintrags (1 h × 90 €).
        $this->assertSame('90.00', $invoice->subtotal?->getAmount());
    }

    public function test_invoice_show_lists_source_time_entries(): void {
        $this->entry('2030-04-01', 60, 'Quelleintrag Serverpflege');

        $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->sqid,
        ])->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Quelleintrag Serverpflege');
    }
}
