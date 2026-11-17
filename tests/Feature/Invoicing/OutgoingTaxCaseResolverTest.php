<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OutgoingTaxCaseResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceStatus, PlaceOfSupplyKind, SalesTaxCase};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Invoicing\Dto\OutgoingTaxCase;
use App\Services\Invoicing\{OutgoingTaxCaseResolver, PlaceOfSupply};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1712: Der Steuerfall für Faktura- und Buchhaltungsprogramme entsteht
 * aus der Kategorie des TaxResolvers bzw. dem eingefrorenen Steuerkontext und
 * trennt, was die Kategorie offen lässt (§ 13b Inland gegen EU-Leistung,
 * innergemeinschaftliche Lieferung, § 19 gegen § 4, Gebiet bei „nicht steuerbar“).
 */
final class OutgoingTaxCaseResolverTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE']]]);
        $this->admin = $this->orgAdmin();
        $this->actingAs($this->admin);
    }

    /**
     * Empfänger × Leistungsort bzw. Ware → Fall und Gebiet.
     *
     * @return iterable<string, array{0: array<string, mixed>, 1: ?PlaceOfSupply, 2: bool, 3: SalesTaxCase, 4: ?string}>
     */
    public static function cases(): iterable {
        $general = new PlaceOfSupply(PlaceOfSupplyKind::General);
        $catalog = new PlaceOfSupply(PlaceOfSupplyKind::Catalog);

        yield 'S Inland' => [['country' => 'DE'], $general, false, SalesTaxCase::Standard, OutgoingTaxCase::REGION_DOMESTIC];
        yield 'AE EU-Unternehmen Leistung' => [['country' => 'AT', 'vat_id' => 'ATU13585627'], $general, false, SalesTaxCase::EuReverseCharge, OutgoingTaxCase::REGION_EU];
        yield '§ 13b Inland' => [['country' => 'DE', 'is_reverse_charge_recipient' => true], $general, false, SalesTaxCase::DomesticReverseCharge, OutgoingTaxCase::REGION_DOMESTIC];
        yield '§ 13b Grundstück Inland, Kunde im Ausland' => [['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_reverse_charge_recipient' => true], new PlaceOfSupply(PlaceOfSupplyKind::Property), false, SalesTaxCase::DomesticReverseCharge, OutgoingTaxCase::REGION_EU];
        yield 'K Ware an EU-Unternehmen' => [['country' => 'AT', 'vat_id' => 'ATU13585627'], null, true, SalesTaxCase::IntraCommunitySupply, OutgoingTaxCase::REGION_EU];
        yield 'G Ware ins Drittland' => [['country' => 'CH'], null, true, SalesTaxCase::Export, OutgoingTaxCase::REGION_THIRD_COUNTRY];
        yield 'O Leistung an Drittland-Unternehmen' => [['country' => 'CH'], $general, false, SalesTaxCase::NotTaxable, OutgoingTaxCase::REGION_THIRD_COUNTRY];
        yield 'O Katalogleistung an Verbraucher im Drittland' => [['country' => 'CH', 'is_consumer' => true], $catalog, false, SalesTaxCase::NotTaxable, OutgoingTaxCase::REGION_THIRD_COUNTRY];
        yield 'O Grundstück in der EU, Kunde im Inland' => [['country' => 'DE'], new PlaceOfSupply(PlaceOfSupplyKind::Property, 'AT'), false, SalesTaxCase::NotTaxable, OutgoingTaxCase::REGION_EU];
        yield 'O Grundstück im Drittland, Kunde in der EU' => [['country' => 'AT', 'vat_id' => 'ATU13585627'], new PlaceOfSupply(PlaceOfSupplyKind::Property, 'CH'), false, SalesTaxCase::NotTaxable, OutgoingTaxCase::REGION_THIRD_COUNTRY];
        // Gegenproben: Verbraucher in der EU und allgemeine Leistung an Verbraucher im Drittland tragen deutsche Steuer.
        yield 'S Verbraucher EU' => [['country' => 'AT', 'is_consumer' => true], $general, false, SalesTaxCase::Standard, OutgoingTaxCase::REGION_EU];
        yield 'S allgemeine Leistung an Verbraucher im Drittland' => [['country' => 'CH', 'is_consumer' => true], $general, false, SalesTaxCase::Standard, OutgoingTaxCase::REGION_THIRD_COUNTRY];
    }

    /** @param array<string, mixed> $customer */
    #[DataProvider('cases')]
    public function test_case_follows_recipient_and_place_of_supply(array $customer, ?PlaceOfSupply $place, bool $goods, SalesTaxCase $expected, ?string $region): void {
        $cases = app(OutgoingTaxCaseResolver::class)->forPlaces($this->organization, $this->customer($customer), CarbonImmutable::parse('2030-04-15'), $goods, $place !== null ? [$place] : []);

        $this->assertCount(1, $cases);
        $this->assertSame([$expected, $region], [$cases[0]->case, $cases[0]->region]);
        $this->assertSame(! $expected->isTaxable(), $cases[0]->zeroRated());
    }

    public function test_small_business_is_section_19(): void {
        $this->organization->update(['settings' => ['einvoice' => ['country' => 'DE', 'small_business' => '1']]]);

        $cases = app(OutgoingTaxCaseResolver::class)->forPlaces($this->organization->refresh(), $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']), null, false, []);

        $this->assertSame(SalesTaxCase::SmallBusiness, $cases[0]->case);
        $this->assertStringContainsString('§ 19', (string) $cases[0]->note);
    }

    public function test_reverse_charge_keeps_its_note_and_vat_id(): void {
        $cases = app(OutgoingTaxCaseResolver::class)->forPlaces($this->organization, $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']), null, false, []);

        $this->assertTrue($cases[0]->buyerHasVatId);
        $this->assertStringContainsString('Reverse Charge', (string) $cases[0]->note);
    }

    /** Leistungsorte zu verschiedenen Fällen: zwei Fälle, die Übergabe ist zu teilen. */
    public function test_places_with_different_cases_are_mixed(): void {
        $cases = app(OutgoingTaxCaseResolver::class)->forPlaces($this->organization, $this->customer(['country' => 'CH', 'is_consumer' => true]), null, false, [
            new PlaceOfSupply(PlaceOfSupplyKind::Catalog),
            new PlaceOfSupply(PlaceOfSupplyKind::General),
        ]);

        $this->assertEqualsCanonicalizing([SalesTaxCase::NotTaxable, SalesTaxCase::Standard], array_map(static fn (OutgoingTaxCase $case): SalesTaxCase => $case->case, $cases));
    }

    /** Derselbe Fall in zwei Gebieten bleibt einer, das Gebiet offen. */
    public function test_not_taxable_in_two_regions_leaves_the_region_open(): void {
        $cases = app(OutgoingTaxCaseResolver::class)->forPlaces($this->organization, $this->customer(['country' => 'CH']), null, false, [
            new PlaceOfSupply(PlaceOfSupplyKind::General),
            new PlaceOfSupply(PlaceOfSupplyKind::Property, 'AT'),
        ]);

        $this->assertCount(1, $cases);
        $this->assertSame(SalesTaxCase::NotTaxable, $cases[0]->case);
        $this->assertNull($cases[0]->region);
    }

    /** Ausgestellt gilt der eingefrorene Fall, auch wenn der Kunde heute anders steht. */
    public function test_issued_invoice_keeps_its_frozen_category(): void {
        $customer = $this->customer(['country' => 'DE']);
        $invoice = $this->invoice($customer, ['category' => Invoice::CATEGORY_MATERIAL, 'is_reverse_charge' => true, 'tax_rate' => '0.00'], [
            'tax_context' => ['category' => 'AE'],
            'party_snapshot' => ['buyer' => ['country' => 'AT', 'vat_id' => 'ATU13585627']],
        ]);

        $cases = app(OutgoingTaxCaseResolver::class)->forInvoice($invoice);

        // Material an ein EU-Unternehmen: innergemeinschaftliche Lieferung, wie die Buchhaltung (zeroRatedCategory).
        $this->assertSame([SalesTaxCase::IntraCommunitySupply], array_map(static fn (OutgoingTaxCase $case): SalesTaxCase => $case->case, $cases));
    }

    /** Belege vor MVP-1684 tragen bei Leistungen `G`: nicht steuerbar, nicht Ausfuhr. */
    public function test_legacy_g_on_services_is_not_taxable(): void {
        $invoice = $this->invoice($this->customer(['country' => 'CH']), ['tax_rate' => '0.00'], ['tax_context' => ['category' => 'G']]);

        $this->assertSame(SalesTaxCase::NotTaxable, app(OutgoingTaxCaseResolver::class)->forInvoice($invoice)[0]->case);
    }

    /** Ein Entwurf bekommt den Fall, den das Ausstellen einfrieren wird. */
    public function test_draft_invoice_follows_the_customer(): void {
        $invoice = $this->invoice($this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']), ['is_reverse_charge' => true, 'tax_rate' => '0.00']);

        $this->assertSame(SalesTaxCase::EuReverseCharge, app(OutgoingTaxCaseResolver::class)->forInvoice($invoice)[0]->case);
    }

    /** Gegenprobe Inland: steuerpflichtig. */
    public function test_domestic_draft_invoice_is_standard(): void {
        $invoice = $this->invoice($this->customer(['country' => 'DE']), ['tax_rate' => '19.00']);

        $this->assertSame(SalesTaxCase::Standard, app(OutgoingTaxCaseResolver::class)->forInvoice($invoice)[0]->case);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Entwurf mit einer Position; mit `$issued` danach ausgestellt (Positionen ausgestellter Rechnungen sind gesperrt).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $issued
     */
    private function invoice(Customer $customer, array $attributes, array $issued = []): Invoice {
        $invoice = Invoice::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 99999),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create(['description' => 'Wartung', 'quantity' => '2.00', 'unit' => 'Std', 'unit_price' => '90.00', 'position' => 1]);
        if ($issued !== []) {
            $invoice->update($issued + ['status' => InvoiceStatus::Issued, 'issued_on' => '2030-04-30']);
        }

        return $invoice->refresh();
    }
}
