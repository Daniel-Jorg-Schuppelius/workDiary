<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountInvoiceRunnerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, Invoice, Project, TimeEntry, User};
use App\Services\Billing\AccountInvoiceRunner;
use App\Services\Invoicing\InvoiceGenerator;
use App\Support\Tz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098 (Rechnungs-Modus): Monatslauf nutzt die unveränderte
 * InvoiceGenerator-Pipeline; die Sonderkonditions-Sätze stecken in den
 * rate-Snapshots. Idempotenz via exported; E5-Guard blockiert Zeitenläufe
 * für Konto-Modus-Kunden.
 */
class AccountInvoiceRunnerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CustomerBillingAgreement $agreement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->invoiceMode()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 16.50,
        ]);
    }

    private function makeEntry(string $day): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => $day . ' 10:00:00',
            'ended_at' => $day . ' 12:00:00',
        ]);
    }

    public function test_run_due_invoices_previous_month_with_agreement_rates(): void {
        $previous = Carbon::now(Tz::current())->startOfMonth()->subMonthNoOverflow();
        $entry = $this->makeEntry($previous->copy()->addDays(9)->toDateString());
        $this->assertSame('16.50', $entry->fresh()->hourly_rate?->getAmount());

        $result = app(AccountInvoiceRunner::class)->runDue();

        $this->assertSame(1, $result['created']);
        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($this->customer->id, $invoice->customer_id);
        $this->assertTrue(
            $invoice->items()->get()->contains(fn ($item): bool => $item->unit_price?->toFloat() === 16.50),
            'Rechnungsposition muss den Sonderkonditions-Satz tragen.'
        );
        $this->assertTrue($entry->fresh()->exported);

        // Zweiter Lauf: nichts mehr offen ⇒ skipped, kein Doppelbeleg.
        $second = app(AccountInvoiceRunner::class)->runDue();
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_account_mode_blocks_manual_time_invoice_run(): void {
        $this->agreement->update(['mode' => 'account']);
        $previous = Carbon::now(Tz::current())->startOfMonth()->subMonthNoOverflow();
        $this->makeEntry($previous->copy()->addDays(9)->toDateString());

        $this->expectException(ValidationException::class);
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, []);
    }

    public function test_run_due_ignores_account_mode_agreements(): void {
        $this->agreement->update(['mode' => 'account']);
        $previous = Carbon::now(Tz::current())->startOfMonth()->subMonthNoOverflow();
        $this->makeEntry($previous->copy()->addDays(9)->toDateString());

        $result = app(AccountInvoiceRunner::class)->runDue();

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, Invoice::query()->count());
    }
}
