<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceCancellationReleaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Diary\Tour;
use App\Models\Invoicing\Invoice;
use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\{TimeEntry, Timesheet};
use App\Models\Travel\Expense;
use App\Services\Expense\ExpenseInvoicingService;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1521: Der Storno einer lokalen Rechnung — direkt am Entwurf oder per
 * Stornobeleg — gibt alle Quellen frei wie der Storno einer Übergabe. Die
 * Folgerechnung enthält die Stunden und die Auffüllung auf das Soll wieder.
 */
final class InvoiceCancellationReleaseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-03-01', 'to' => '2030-03-31'];

    private User $accountant;

    private Customer $customer;

    private Project $project;

    private TimeEntry $entry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-03-20');
        $this->accountant = $this->orgAdmin();
        $this->actingAs($this->accountant);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Berta Muster']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stadtwerke Beispielstadt', 'billing_mode' => BillingMode::Workdiary, 'hourly_rate' => '80.00']);
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Leitstelle', 'bills_target_time' => true, 'billable' => true]);
        $this->project->members()->attach([$worker->id]);
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 1, 'target_minutes' => 480]);
        $this->entry = TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $worker->id, 'date' => '2030-03-11', 'minutes' => 390, 'billable' => true]);
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $worker->id, 'date' => '2030-03-11', 'target_minutes' => 480, 'booked_minutes' => 390, 'shortfall_minutes' => 90]);
    }

    public function test_cancelling_a_draft_brings_hours_and_top_up_back_into_the_next_invoice(): void {
        $draft = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);
        $this->assertTrue((bool) $this->entry->fresh()?->exported);

        $this->post(route('invoices.cancel', $draft), ['reason' => 'Falscher Zeitraum'])->assertRedirect(route('invoices.show', $draft));

        $this->assertSame(InvoiceStatus::Cancelled, $draft->refresh()->status);
        $this->assertSame(2, $draft->items()->count(), 'Die Positionen bleiben am stornierten Beleg.');
        $this->assertReleasedAndRebilled();
    }

    public function test_cancellation_document_brings_hours_and_top_up_back_into_the_next_invoice(): void {
        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);
        app(InvoiceIssueService::class)->issue($invoice->refresh());
        $quantities = $invoice->items()->orderBy('position')->pluck('quantity')->all();

        $this->post(route('invoices.cancel', $invoice->refresh()))->assertRedirect();

        $cancellation = Invoice::query()->where('type', Invoice::TYPE_CANCELLATION)->sole();
        $this->assertSame($invoice->id, (int) $cancellation->parent_invoice_id);
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
        $this->assertSame($quantities, $invoice->items()->orderBy('position')->pluck('quantity')->all(), 'Positionen ausgestellter Rechnungen bleiben unverändert.');
        $this->assertReleasedAndRebilled();
    }

    public function test_cancellation_releases_material_travel_and_expenses(): void {
        $sheet = Timesheet::create([
            'organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->accountant->id,
            'kind' => \App\Enums\Timesheet\TimesheetKind::Project->value, 'work_date' => '2030-03-12', 'status' => \App\Enums\Timesheet\TimesheetStatus::Draft->value,
        ]);
        $usage = MaterialUsage::create(['organization_id' => $this->organization->id, 'timesheet_id' => $sheet->id, 'description' => 'Kabelbinder', 'quantity' => '2.000', 'unit' => 'Stk', 'unit_price' => '4.5000']);
        $tour = Tour::create([
            'organization_id' => $this->organization->id, 'user_id' => $this->accountant->id, 'tour_date' => '2030-03-12',
            'status' => \App\Enums\Tour\TourStatus::Completed->value, 'planned_distance_km' => '0', 'planned_duration_minutes' => 0,
        ]);
        $expense = Expense::factory()->approved()->billable()->create(['organization_id' => $this->organization->id, 'user_id' => $this->accountant->id, 'customer_id' => $this->customer->id, 'date' => '2030-03-12']);

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);
        $invoice->items()->create(['material_usage_id' => $usage->id, 'description' => 'Kabelbinder', 'quantity' => '2', 'unit' => 'Stk', 'unit_price' => '4.50', 'position' => 3]);
        $invoice->items()->create(['tour_id' => $tour->id, 'description' => 'Anfahrt', 'quantity' => '1', 'unit' => 'Psch.', 'unit_price' => '20.00', 'position' => 4]);
        $usage->forceFill(['billed' => true])->save();
        $tour->forceFill(['travel_billed' => true])->save();
        app(ExpenseInvoicingService::class)->addToInvoice($invoice->refresh(), Expense::query()->whereKey($expense->id)->get());
        $this->assertSame(ExpenseStatus::Invoiced, $expense->refresh()->status);
        app(InvoiceIssueService::class)->issue($invoice->refresh());

        app(InvoiceGenerator::class)->cancellationFor($invoice->refresh(), 'Test', $this->accountant->id);

        $this->assertFalse((bool) $usage->fresh()?->billed);
        $this->assertFalse((bool) $tour->fresh()?->travel_billed);
        $this->assertSame(ExpenseStatus::Approved, $expense->refresh()->status);

        // Die Position der stornierten Rechnung hält die Auslage nicht mehr fest.
        $next = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);
        app(ExpenseInvoicingService::class)->addToInvoice($next->refresh(), Expense::query()->whereKey($expense->id)->get());
        $this->assertSame(1, $next->items()->where('expense_id', $expense->id)->count());
        $this->assertSame(ExpenseStatus::Invoiced, $expense->refresh()->status);
    }

    private function assertReleasedAndRebilled(): void {
        $this->assertFalse((bool) $this->entry->fresh()?->exported, 'Die gebuchten Stunden sind wieder abrechenbar.');
        $shortfall = ProjectTimeShortfall::query()->sole();
        $this->assertNull($shortfall->billed_minutes);
        $this->assertNull($shortfall->invoice_item_id);

        $next = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, self::PERIOD);
        $items = $next->items()->orderBy('position')->get();
        $this->assertCount(2, $items, 'Folgerechnung mit Stunden und Auffüllung, nicht nur der Differenz');
        $this->assertSame($this->entry->id, (int) $items->first()?->time_entry_id);
        $this->assertStringContainsString('Vereinbarte Einsatzzeit — Berta Muster, 11.03.2030', (string) $items->last()?->description);
        $this->assertSame($items->last()?->id, (int) $shortfall->refresh()->invoice_item_id);
        $this->assertTrue((bool) $this->entry->fresh()?->exported);
    }
}
