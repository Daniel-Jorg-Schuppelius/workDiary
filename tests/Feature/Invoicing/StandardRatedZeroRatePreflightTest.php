<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StandardRatedZeroRatePreflightTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueException, InvoiceIssueService};
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1732: Steuerpflichtig (`S`) verlangt nach EN 16931 einen Satz über 0 %
 * (BR-S-05). Ein Entwurf mit Fall `S` und 0 % im Kopf (etwa nachdem der Kunde
 * seine USt-IdNr. verlor) erschien in der E-Rechnungsprüfung als `S` 0 %, ohne
 * Meldung. Der Preflight meldet den Widerspruch jetzt: als Warnung, wo das
 * Ausstellen den Satz angleicht (Positionen ohne eigene Kategorie im Entwurf),
 * sonst als Fehler — die E-Rechnung trüge `S` 0 % und fiele bei jeder Prüfung
 * nach EN 16931 durch.
 */
final class StandardRatedZeroRatePreflightTest extends TestCase {
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

    /** Entwurf mit 0 % im Kopf und Fall S: das Ausstellen gleicht an, der Preflight warnt mit dem Satz des Falls. */
    public function test_draft_with_a_zero_header_rate_and_case_s_is_warned(): void {
        $draft = $this->draft($this->customer(), [['Beratung', null, null]]);
        $this->zeroHeader($draft);

        $result = app(XRechnungGenerator::class)->preflight($this->asSent($draft));

        $this->assertContains((string) __('invoicing.einvoice.warning.standard_zero_rate_draft', ['rate' => '19,00']), $result['warnings']);
        $this->assertNotContains($this->error('Beratung'), $result['errors']);
        $this->assertNotContains((string) __('invoicing.einvoice.error.totals_mismatch'), $result['errors'], 'Nur der Widerspruch, keine Folgefehler.');
    }

    /** Nach dem Angleich beim Stellen ist der Satz gesetzt: keine Meldung mehr. */
    public function test_issue_aligns_the_rate_and_the_finding_disappears(): void {
        $draft = $this->draft($this->customer(), [['Beratung', null, null]]);
        $this->zeroHeader($draft);

        try {
            app(InvoiceIssueService::class)->issue($draft);
            $this->fail('Der angeglichene Satz hätte das Stellen einmal anhalten müssen.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_TAX_CASE_CHANGED, $e->reason);
        }
        $issued = app(InvoiceIssueService::class)->issue($draft->refresh())->refresh();

        $this->assertSame('19.00', $issued->tax_rate?->getNumericValue());
        $result = app(XRechnungGenerator::class)->preflight($issued);
        $this->assertNotContains($this->error('Beratung'), $result['errors']);
        $this->assertNotContains((string) __('invoicing.einvoice.warning.standard_zero_rate_draft', ['rate' => '19,00']), $result['warnings']);
    }

    /** Was das Ausstellen nicht angleicht, ist ein Fehler: Kategorie S mit 0 % und eine eigene 0 %-Position neben 19 % im Kopf. */
    public function test_lines_the_issue_does_not_align_are_errors(): void {
        $draft = $this->draft($this->customer(), [['Beratung', null, null], ['Wartung', 'S', '0.00'], ['Prüfung', null, '0.00']]);

        $errors = app(XRechnungGenerator::class)->preflight($this->asSent($draft))['errors'];

        $this->assertContains($this->error('Wartung'), $errors);
        $this->assertContains($this->error('Prüfung'), $errors);
        $this->assertNotContains($this->error('Beratung'), $errors);
    }

    /** Ausgestellt mit eingefrorenem S: Eine Position mit 0 % ist ein Fehler, die E-Rechnung entsteht nicht. */
    public function test_issued_invoice_with_a_zero_rated_s_line_is_an_error(): void {
        $issued = app(InvoiceIssueService::class)->issue($this->draft($this->customer(), [['Beratung', null, null], ['Wartung', null, null]]))->refresh();
        $this->assertSame('S', data_get($issued->tax_context, 'category'));
        // Altbestand, am Sperr-Guard ausgestellter Belege vorbei geschrieben.
        InvoiceItem::query()->where('invoice_id', $issued->id)->where('description', 'Wartung')->update(['tax_rate' => '0.00']);
        $issued = Invoice::query()->with(['items', 'customer'])->findOrFail($issued->id);

        $result = app(XRechnungGenerator::class)->preflight($issued);

        $this->assertContains($this->error('Wartung'), $result['errors']);
        $this->assertNotContains($this->error('Beratung'), $result['errors']);
        $this->assertSame([], array_values(array_filter($result['warnings'], static fn (string $warning): bool => str_contains($warning, 'BR-S-05'))));
    }

    /** Gegenprobe: 19 %, Nullsatz-Kategorie Z und ein Fall ohne Steuer (AE) melden nichts. */
    public function test_valid_rates_and_untaxed_categories_are_not_reported(): void {
        $generator = app(XRechnungGenerator::class);

        $standard = $this->draft($this->customer(), [['Beratung', null, null], ['Fachbuch', 'Z', '0.00']]);
        $result = $generator->preflight($this->asSent($standard));
        $this->assertNotContains($this->error('Beratung'), $result['errors']);
        $this->assertNotContains($this->error('Fachbuch'), $result['errors']);
        $this->assertNotContains((string) __('invoicing.einvoice.warning.standard_zero_rate_draft', ['rate' => '19,00']), $result['warnings']);

        $reverseCharge = $this->draft($this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627', 'address_zip' => '4020', 'address_city' => 'Linz']), [['Beratung', null, null]]);
        $this->assertTrue($reverseCharge->tax_rate?->isZero());
        $result = $generator->preflight($this->asSent($reverseCharge));
        $this->assertNotContains($this->error('Beratung'), $result['errors']);
        $this->assertSame([], array_values(array_filter($result['warnings'], static fn (string $warning): bool => str_contains($warning, 'BR-S-05'))));
    }

    private function error(string $line): string {
        return (string) __('invoicing.einvoice.error.standard_zero_rate', ['line' => $line]);
    }

    /** Kopf auf 0 % wie nach einem Wechsel aus einem Fall ohne Steuer; die Summen sind konsistent gerechnet. */
    private function zeroHeader(Invoice $draft): void {
        $draft->tax_rate = Percentage::of('0.00', 2);
        $draft->load('items');
        $draft->recalculate();
        $draft->saveQuietly();
        $draft->refresh();
        $this->assertTrue($draft->tax_rate?->isZero());
    }

    /** @param list<array{0: string, 1: ?string, 2: ?string}> $lines Beschreibung, Steuerkategorie, Steuersatz */
    private function draft(Customer $customer, array $lines): Invoice {
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        foreach ($lines as $i => [$description, $category, $rate]) {
            $draft->items()->create([
                'organization_id' => $this->organization->id,
                'description' => $description,
                'quantity' => '2',
                'unit' => 'Std.',
                'unit_price' => '80.00',
                'tax_category' => $category,
                'tax_rate' => $rate,
                'service_date' => '2030-04-15',
                'position' => $i + 1,
            ]);
        }
        $draft->load('items');
        $draft->recalculate();
        $draft->save();

        return $draft->refresh();
    }

    /** Der Entwurf als ausgestellt im Speicher, wie im Versandweg — ohne eingefrorenen Fall. */
    private function asSent(Invoice $draft): Invoice {
        $probe = clone $draft->load(['items', 'customer']);
        $probe->status = InvoiceStatus::Issued;
        $probe->issued_on = now();

        return $probe;
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Kundenstadt',
            'country' => 'DE',
            'is_consumer' => false,
            'buyer_reference' => '991-12345-73',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }
}
