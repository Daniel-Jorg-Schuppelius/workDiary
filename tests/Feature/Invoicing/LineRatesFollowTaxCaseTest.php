<?php
/*
 * Created on   : Mon Nov 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LineRatesFollowTaxCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceScheduleStatus, InvoiceStatus, TaxCaseAlignment};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem, InvoiceSchedule};
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueException, InvoiceIssueService, QuoteService, RecurringInvoiceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1718: Positionen ohne eigene Steuerkategorie folgen beim Abgleich dem
 * Steuerfall der Rechnung — bei einem Fall ohne Steuer 0 %, nach einem
 * Wechsel in die Steuerpflicht der Satz des Falls. Vorher behielt eine aus
 * Abrechnungsplan oder Pro-forma übernommene Position 19 %: Die Rechnung wies
 * Steuer auf eine nicht steuerbare Leistung aus, und die Prüfung vor dem
 * Versand wies sie ab. Ermäßigte Sätze, Positionen mit Kategorie und
 * ausgestellte Rechnungen bleiben unverändert.
 */
final class LineRatesFollowTaxCaseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
        ]]]);
        $this->admin = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->admin);
    }

    public function test_a_line_rate_against_an_untaxed_case_follows_it_and_passes_the_preflight(): void {
        $draft = app(InvoiceGenerator::class)->emptyDraft($this->businessAbroad());
        $this->assertTrue($draft->tax_rate?->isZero(), 'Unternehmen in der Schweiz: nicht steuerbar.');
        $line = $this->line($draft, 'Beratung', '19.00');
        $this->refreshTotals($draft);
        $this->assertSame('30.40', $draft->tax_amount?->getAmount(), 'Vorher: Steuer auf eine nicht steuerbare Leistung.');
        $lineError = (string) __('invoicing.einvoice.error.line_category_rate', ['line' => 'Beratung', 'category' => 'O', 'rate' => '19,00']);
        $this->assertContains($lineError, app(XRechnungGenerator::class)->preflight($this->asIssued($draft))['errors']);

        $draft->load(['items', 'customer', 'organization']);
        $this->assertSame(TaxCaseAlignment::LineRates, app(InvoiceIssueService::class)->alignTaxCase($draft));
        $draft->recalculate();
        $draft->save();

        $this->assertSame('0.00', $line->fresh()?->tax_rate?->getNumericValue());
        $this->assertSame('0.00', $draft->refresh()->tax_amount?->getAmount());
        $issued = app(InvoiceIssueService::class)->issue($draft)->refresh();
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertSame('O', data_get($issued->tax_context, 'category'));
        $this->assertNotContains($lineError, app(XRechnungGenerator::class)->preflight($issued)['errors']);
    }

    /** Das Ausstellen hält einmal an, damit die Person die geänderten Summen prüft. */
    public function test_issue_stops_once_for_a_line_rate_against_the_case(): void {
        $draft = app(InvoiceGenerator::class)->emptyDraft($this->businessAbroad());
        $this->line($draft, 'Beratung', '19.00');
        $this->refreshTotals($draft);

        try {
            app(InvoiceIssueService::class)->issue($draft);
            $this->fail('Ein Positionssatz gegen den Steuerfall muss das Ausstellen anhalten.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_TAX_CASE_CHANGED, $e->reason);
            $this->assertSame((string) __('invoicing.tax_case.line_rates_on_issue'), $e->getMessage());
        }
        $this->assertSame(InvoiceStatus::Draft, $draft->refresh()->status);
        $this->assertSame('0.00', $draft->tax_amount?->getAmount());

        $this->assertSame(InvoiceStatus::Issued, app(InvoiceIssueService::class)->issue($draft)->refresh()->status);
    }

    public function test_plan_and_proforma_lines_follow_the_case(): void {
        $customer = $this->businessAbroad();
        $this->schedule($customer, '19.00');

        app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        $plan = Invoice::query()->where('customer_id', $customer->id)->sole();
        $this->assertSame(['0.00'], $plan->items()->get()->map(static fn (InvoiceItem $item): ?string => $item->tax_rate?->getNumericValue())->all());
        $this->assertSame('0.00', $plan->tax_amount?->getAmount());
        $this->assertSame(InvoiceStatus::Issued, app(InvoiceIssueService::class)->issue($plan)->refresh()->status, 'Kein Anhalten beim ersten Ausstellen.');

        // Pro-forma für einen Kunden im Inland; er zieht vor der Umwandlung in die Schweiz.
        $moving = $this->customer(['country' => 'DE']);
        $proforma = app(InvoiceGenerator::class)->emptyProforma($moving);
        $this->line($proforma, 'Wartung', '19.00');
        $moving->update(['country' => 'CH']);

        $invoice = app(QuoteService::class)->proformaToInvoice($proforma->refresh(), $this->admin);

        $this->assertTrue($invoice->tax_rate?->isZero());
        $this->assertSame('0.00', $invoice->items()->sole()->tax_rate?->getNumericValue());
        $this->assertSame('0.00', $invoice->tax_amount?->getAmount());
    }

    /** Nach einem Wechsel in die Steuerpflicht gilt der Satz des Falls aus der Steuerregel, nicht fest 19 %. */
    public function test_after_a_switch_to_taxable_lines_take_the_rate_of_the_case(): void {
        $this->organization->update(['settings' => array_replace_recursive((array) $this->organization->settings, ['invoicing' => ['default_tax_rate' => '16.00']])]);
        $customer = $this->businessAbroad();
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $zero = $this->line($draft, 'Wartung', '0.00');
        $open = $this->line($draft, 'Anfahrt', null);
        $customer->update(['country' => 'DE']);
        $draft->load(['items', 'customer', 'organization']);

        $this->assertTrue(app(InvoiceIssueService::class)->alignTaxCase($draft)->changed(), 'Der Fall wechselt.');

        $this->assertSame('16.00', $draft->tax_rate?->getNumericValue());
        $this->assertSame('16.00', $zero->fresh()?->tax_rate?->getNumericValue());
        $this->assertNull($open->fresh()?->tax_rate, 'Ohne eigenen Satz folgt die Position ohnehin dem Kopf.');
    }

    /** Gegenprobe: Ein ermäßigter Satz bei Steuerpflicht und eine Position mit eigener Kategorie bleiben. */
    public function test_reduced_rate_and_explicit_category_keep_their_rate(): void {
        $customer = $this->customer(['country' => 'DE']);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $reduced = $this->line($draft, 'Fachbuch', '7.00');
        $draft->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Unchanged, app(InvoiceIssueService::class)->alignTaxCase($draft));
        $this->assertSame('7.00', $reduced->fresh()?->tax_rate?->getNumericValue());

        $explicit = $this->line($draft, 'Prüfgebühr', '19.00', 'S');
        $customer->update(['country' => 'CH']);
        $draft->refresh()->load(['items', 'customer', 'organization']);

        $this->assertTrue(app(InvoiceIssueService::class)->alignTaxCase($draft)->changed(), 'Der Fall wechselt.');
        $reduced->refresh();
        $this->assertSame('0.00', $reduced->tax_rate?->getNumericValue(), 'Ohne Steuer trägt auch der ermäßigte Satz 0 %.');
        $explicit->refresh();
        $this->assertSame('19.00', $explicit->tax_rate?->getNumericValue());
        $this->assertSame('S', $explicit->tax_category);
    }

    /** Gegenprobe: Eine ausgestellte Rechnung bleibt, wie sie ist. */
    public function test_issued_invoice_stays_unchanged(): void {
        $customer = $this->customer(['country' => 'DE']);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $line = $this->line($draft, 'Beratung', '19.00');
        $this->refreshTotals($draft);
        $issued = app(InvoiceIssueService::class)->issue($draft)->refresh();
        $customer->update(['country' => 'CH']);
        $issued->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Unchanged, app(InvoiceIssueService::class)->alignTaxCase($issued));
        $this->assertSame('19.00', $line->fresh()?->tax_rate?->getNumericValue());
        $this->assertSame('19.00', $issued->refresh()->tax_rate?->getNumericValue());
    }

    /**
     * Reverse Charge rechnet ohne Positionssteuer: Die Position folgt dem Fall
     * still, ohne Anhalten und ohne die Freigabe aufzuheben; der Aufriss nennt
     * keinen Satz von 19 % mehr.
     */
    public function test_reverse_charge_aligns_lines_without_stopping_or_revoking_the_approval(): void {
        $customer = $this->customer(['country' => 'DE', 'vat_id' => 'DE811569869', 'is_reverse_charge_recipient' => true]);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $this->assertTrue((bool) $draft->is_reverse_charge);
        $line = $this->line($draft, 'Trockenbau', '19.00');
        $this->refreshTotals($draft);
        $draft->update(['approved_at' => now(), 'approved_by' => $this->admin->id]);
        $draft->refresh()->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Unchanged, app(InvoiceIssueService::class)->alignTaxCase($draft));

        $this->assertSame('0.00', $line->fresh()?->tax_rate?->getNumericValue());
        $this->assertNotNull($draft->fresh()?->approved_at);
        $this->assertSame([0.0], array_map(static fn (array $row): float => (float) $row['rate'], (array) $draft->tax_breakdown));
        $this->assertSame(InvoiceStatus::Issued, app(InvoiceIssueService::class)->issue($draft)->refresh()->status);
    }

    /** Der Dialog meldet die Angleichung; die Position steht auf 0 %. */
    public function test_editing_a_line_reports_the_aligned_rate(): void {
        $draft = app(InvoiceGenerator::class)->emptyDraft($this->businessAbroad());

        $this->post(route('invoices.items.store', $draft), [
            'description' => 'Beratung',
            'quantity' => '1',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'tax_rate' => '19.00',
        ])->assertRedirect()->assertSessionHas('warning', (string) __('invoicing.tax_case.line_rates_aligned'));

        $this->assertSame('0.00', $draft->items()->sole()->tax_rate?->getNumericValue());
        $this->assertSame('0.00', $draft->refresh()->tax_amount?->getAmount());
    }

    private function line(Invoice $invoice, string $description, ?string $rate, ?string $category = null): InvoiceItem {
        return $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => $description,
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'tax_rate' => $rate,
            'tax_category' => $category,
            'service_date' => '2030-04-15',
            'position' => (int) $invoice->items()->max('position') + 1,
        ]);
    }

    /** Wie nach dem Speichern einer Position, aber ohne Abgleich: Summen der Positionen. */
    private function refreshTotals(Invoice $invoice): void {
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();
    }

    /** Der Entwurf als ausgestellt im Speicher, wie im Versandweg. */
    private function asIssued(Invoice $draft): Invoice {
        $probe = clone $draft->load(['items', 'customer']);
        $probe->status = InvoiceStatus::Issued;
        $probe->issued_on = now();
        // Ohne eingefrorenen Fall prüft der Generator den Fall, den das Ausstellen einfrieren wird (MVP-1720).

        return $probe;
    }

    private function businessAbroad(): Customer {
        return $this->customer(['country' => 'CH', 'is_consumer' => false]);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '8001',
            'address_city' => 'Kundenstadt',
            'buyer_reference' => '991-12345-73',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    private function schedule(Customer $customer, string $rate): InvoiceSchedule {
        $schedule = InvoiceSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'title' => 'Betreuung monatlich',
            'interval_unit' => InvoiceSchedule::UNIT_MONTH,
            'interval_count' => 1,
            'billing_period_mode' => InvoiceSchedule::MODE_PREVIOUS,
            'next_run_on' => '2030-06-01',
            'status' => InvoiceScheduleStatus::Active,
            'created_by' => $this->admin->id,
        ]);
        $schedule->items()->create([
            'organization_id' => $this->organization->id,
            'position' => 1,
            'description' => 'Betreuung',
            'quantity' => '1.000',
            'unit' => 'pausch.',
            'unit_price' => '250.0000',
            'tax_rate' => $rate,
        ]);

        return $schedule;
    }
}
