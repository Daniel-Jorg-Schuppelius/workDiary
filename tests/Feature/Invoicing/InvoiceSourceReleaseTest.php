<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSourceReleaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Invoice, MaterialUsage, Project, TimeEntry, Timesheet, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (G1/G2): Die lokale Fakturierung markiert abgerechnete
 * Zeiten (`exported`), bricht ohne offene Posten ab und gibt beim Löschen
 * eines Entwurfs ALLE Quellposten wieder frei (Zeiten/Material/Spesen) —
 * keine Doppelfakturierung, kein stiller Umsatzverlust.
 */
class InvoiceSourceReleaseTest extends TestCase {
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

    private function postAsAdmin(string $routeName, array $payload = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->post(route($routeName), $payload);
    }

    private function timeEntry(string $date, int $minutes = 60): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => $date,
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);
    }

    private function materialUsage(string $workDate): MaterialUsage {
        $sheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project->value,
            'work_date' => $workDate,
            'status' => \App\Enums\Timesheet\TimesheetStatus::Draft->value,
        ]);

        return MaterialUsage::create([
            'organization_id' => $this->organization->id,
            'timesheet_id' => $sheet->id,
            'description' => 'Kabel',
            'quantity' => '3.000',
            'unit' => 'Stk',
            'unit_price' => '10.0000',
        ]);
    }

    public function test_overlapping_time_invoice_runs_do_not_double_bill(): void {
        $april = $this->timeEntry('2030-04-10');
        $may = $this->timeEntry('2030-05-10');

        // Lauf 1: April — fakturiert nur den April-Eintrag und markiert ihn.
        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'from' => '2030-04-01',
            'to' => '2030-04-30',
        ])->assertRedirect();

        $this->assertTrue($april->fresh()->exported);
        $this->assertFalse($may->fresh()->exported);

        // Lauf 2 überlappt den April: der bereits fakturierte Eintrag darf
        // NICHT erneut auftauchen, nur der Mai-Eintrag.
        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'from' => '2030-04-05',
            'to' => '2030-05-31',
        ])->assertRedirect();

        $this->assertSame(2, Invoice::query()->count());
        $second = Invoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame(1, $second->items()->count());
        $this->assertSame('90.00', $second->subtotal);
        $this->assertTrue($may->fresh()->exported);
    }

    public function test_time_invoice_run_without_open_entries_aborts(): void {
        $this->timeEntry('2030-04-10');

        $this->postAsAdmin('invoices.store', ['customer_id' => $this->customer->id])->assertRedirect();

        // Zweiter Lauf: nichts Offenes mehr → Abbruch, keine leere Rechnung,
        // keine verbrauchte Nummer.
        $this->postAsAdmin('invoices.store', ['customer_id' => $this->customer->id])
            ->assertSessionHasErrors('customer_id');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_deleting_draft_releases_time_entries_for_rebilling(): void {
        $entry = $this->timeEntry('2030-04-10');

        $this->postAsAdmin('invoices.store', ['customer_id' => $this->customer->id])->assertRedirect();
        $invoice = Invoice::query()->firstOrFail();
        $this->assertTrue($entry->fresh()->exported);

        $this->actingAs($this->admin)->delete(route('invoices.destroy', $invoice))->assertRedirect();

        $this->assertSame(0, Invoice::query()->count());
        $this->assertFalse($entry->fresh()->exported, 'Zeiteintrag muss nach Entwurfs-Löschung wieder abrechenbar sein.');

        // Neuer Lauf fakturiert den Eintrag erneut.
        $this->postAsAdmin('invoices.store', ['customer_id' => $this->customer->id])->assertRedirect();
        $this->assertSame(1, Invoice::query()->firstOrFail()->items()->count());
    }

    public function test_deleting_material_draft_releases_material_usages(): void {
        $usage = $this->materialUsage('2030-05-10');

        $this->postAsAdmin('invoices.store', [
            'customer_id' => $this->customer->id,
            'content' => 'material',
        ])->assertRedirect();
        $this->assertTrue($usage->fresh()->billed);

        $invoice = Invoice::query()->firstOrFail();
        $this->actingAs($this->admin)->delete(route('invoices.destroy', $invoice))->assertRedirect();

        $this->assertFalse($usage->fresh()->billed, 'Materialposten darf nach Entwurfs-Löschung nicht "abgerechnet" bleiben.');
        // Die Positionen sind per Eloquent (nicht DB-Cascade) mitgelöscht.
        $this->assertSame(0, DB::table('invoice_items')->count());
    }
}
