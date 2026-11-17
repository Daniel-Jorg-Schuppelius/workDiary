<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCaseOnCreationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceScheduleStatus, InvoiceStatus, PlaceOfSupplyKind, TaxCaseAlignment};
use App\Enums\Project\ProjectStatus;
use App\Enums\Rental\RentalChargeKind;
use App\Models\Article\Article;
use App\Models\Asset\{Asset, MeterReading};
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceSchedule};
use App\Models\Metering\MeterBillingAgreement;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Reselling\ResaleSubscription;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueException, InvoiceIssueService, QuoteService, RecurringInvoiceService, ServiceDefaultResolver};
use App\Services\Metering\MeterBillingService;
use App\Services\Rental\{RentalBillingService, RentalCaseService};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleDraftRun};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1704: Abrechnungsplan, Verleih, Zähler, Reselling, Abschlag, Retainer
 * und die Umwandlung einer Pro-forma legen den Steuerfall des Kopfes über den
 * Leistungsort ihrer Positionen an — wie der Rechnungslauf aus Zeiten. Vorher
 * nahmen sie die Vorgabe der Organisation, und das erste Ausstellen hielt mit
 * „Steuerfall geändert“ an.
 *
 * Fall: Verbraucher in der Schweiz. Eine Katalogleistung ist dort nicht
 * steuerbar (O), die Vorgabe der Organisation („Allgemein“) ergäbe 19 %.
 */
final class TaxCaseOnCreationTest extends TestCase {
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

    public function test_billing_plan_follows_the_standard_service_and_issues_without_stop(): void {
        $this->standardService(PlaceOfSupplyKind::Catalog);
        $customer = $this->consumerAbroad();
        $this->schedule($customer);

        app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        $this->assertCatalogCaseAndIssue(Invoice::query()->where('customer_id', $customer->id)->sole());
    }

    public function test_rental_invoice_follows_the_project_of_the_case(): void {
        $customer = $this->consumerAbroad();
        $case = app(RentalCaseService::class)->open($this->organization, $this->admin, [
            'customer_id' => $customer->id,
            'project_id' => $this->project($customer, PlaceOfSupplyKind::Catalog)->id,
            'starts_at' => '2030-04-01 08:00:00',
            'ends_at' => '2030-04-03 17:00:00',
        ]);
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => RentalChargeKind::DailyRate->value, 'label' => 'Tagessatz', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);

        $invoice = $billing->invoiceReleasedCharges($case, $this->admin);

        $this->assertNotNull($invoice);
        $this->assertCatalogCaseAndIssue($invoice);
    }

    public function test_meter_draft_follows_the_project_of_the_agreement_with_matching_line_categories(): void {
        $customer = $this->consumerAbroad();
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $agreement = MeterBillingAgreement::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'asset_id' => $asset->id,
            'project_id' => $this->project($customer, PlaceOfSupplyKind::Catalog)->id,
            'title' => 'Kopierer EG',
            'base_price' => '25.00',
            'unit_price' => '0.0120',
            'free_units' => '1000',
            'unit' => 'Klicks',
            'interval_unit' => 'monthly',
            'interval_count' => 1,
            'next_run_on' => '2030-06-30',
        ]);
        foreach (['2030-05-31 10:00:00' => '10000', '2030-06-30 10:00:00' => '13500'] as $at => $value) {
            MeterReading::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $asset->id, 'read_at' => $at, 'value' => $value, 'unit' => 'Klicks', 'is_estimated' => false]);
        }

        $invoice = app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2030-06-01'), CarbonImmutable::parse('2030-06-30'));

        $this->assertNotNull($invoice);
        $this->assertSame(['O'], $invoice->items()->pluck('tax_category')->unique()->values()->all(), 'Positionen und Kopf tragen denselben Fall.');
        $this->assertCatalogCaseAndIssue($invoice);
    }

    public function test_resale_draft_follows_the_article_of_the_subscription(): void {
        $this->travelTo('2026-09-04');
        $customer = $this->consumerAbroad();
        $article = $this->article(PlaceOfSupplyKind::Catalog);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'kind' => 'license', 'provider' => 'qualityhosting',
            'label' => 'Bürosoftware im Abo', 'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly',
            'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '120.00', 'article_ref' => 'art:' . $article->id,
        ]);
        (new PeriodPlanner)->sync($subscription);

        app(ResaleDraftRun::class)->run($this->organization);

        $invoice = Invoice::query()->where('customer_id', $customer->id)->sole();
        $this->assertNull($invoice->items()->first()?->tax_category, 'Keine kopierte Kategorie: der Leistungsort des Abo-Artikels zählt.');
        $this->assertCatalogCaseAndIssue($invoice);
    }

    public function test_down_payment_follows_the_project_service_and_its_line_carries_the_same_case(): void {
        $customer = $this->consumerAbroad();

        $invoice = app(InvoiceGenerator::class)->downPaymentFor($customer, $this->project($customer, PlaceOfSupplyKind::Catalog), 'Abschlag Beratung', '500.00', CarbonImmutable::parse('2030-04-15'));

        $this->assertSame('O', $invoice->items()->first()?->tax_category);
        $this->assertCatalogCaseAndIssue($invoice);
    }

    public function test_retainer_charge_follows_the_standard_service(): void {
        $this->standardService(PlaceOfSupplyKind::Catalog);
        $customer = $this->consumerAbroad();

        $invoice = app(InvoiceGenerator::class)->retainerChargeFor($customer, 'Monatspauschale April', '400.00', 'LX-R-1-203004', Invoice::TYPE_RETAINER, CarbonImmutable::parse('2030-04-30'));

        $this->assertTrue($invoice->tax_rate?->isZero());
        $this->assertSame('O', $invoice->items()->first()?->tax_category);
        $this->assertStringContainsString((string) config('taxation.notes.catalog_third_country'), (string) $invoice->notes);
        $invoice->load(['items', 'customer', 'organization']);
        $this->assertSame(TaxCaseAlignment::Unchanged, app(InvoiceIssueService::class)->alignTaxCase($invoice), 'Die Übergabe an das Programm stellt ohne Anhalten.');
    }

    public function test_proforma_conversion_keeps_the_case_of_its_lines(): void {
        $customer = $this->consumerAbroad();
        $proforma = app(InvoiceGenerator::class)->emptyProforma($customer);
        $proforma->items()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article(PlaceOfSupplyKind::Catalog)->id,
            'description' => 'Beratung', 'quantity' => '2', 'unit' => 'Std.', 'unit_price' => '80.00', 'service_date' => '2030-04-15', 'position' => 1,
        ]);

        $invoice = app(QuoteService::class)->proformaToInvoice($proforma->refresh(), $this->admin);

        $this->assertTrue($invoice->tax_rate?->isZero(), 'Der Kopf folgt der Katalogleistung, auch wenn die Pro-forma nie abgeglichen wurde.');
        $this->assertCatalogCaseAndIssue($invoice);
    }

    /** Gegenprobe: ohne Pflege des Leistungsorts bleibt jeder Weg bei der Vorgabe der Organisation. */
    public function test_without_place_of_supply_the_case_stays_as_before(): void {
        $customer = $this->consumerAbroad();
        $this->schedule($customer);
        app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));
        $plan = Invoice::query()->where('customer_id', $customer->id)->sole();
        $downPayment = app(InvoiceGenerator::class)->downPaymentFor($customer, $this->project($customer, null), 'Abschlag', '500.00', CarbonImmutable::parse('2030-04-15'));

        foreach ([$plan, $downPayment] as $invoice) {
            $this->assertSame('19.00', $invoice->refresh()->tax_rate?->getNumericValue());
            $issued = app(InvoiceIssueService::class)->issue($invoice)->refresh();
            $this->assertSame('S', data_get($issued->tax_context, 'category'));
        }
        $this->assertSame('S', $downPayment->items()->first()?->tax_category);
    }

    /**
     * Gegenprobe zum Mechanismus: Ein Kopf nach der Vorgabe der Organisation, dessen
     * Position eine Katalogleistung ist, hält das erste Ausstellen an — genau das
     * vermeiden die Anlagewege jetzt.
     */
    public function test_a_head_from_the_organization_default_still_stops_on_first_issue(): void {
        $customer = $this->consumerAbroad();
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R2030-0901',
            'status' => InvoiceStatus::Draft, 'currency' => 'EUR', 'tax_rate' => '19.00', 'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article(PlaceOfSupplyKind::Catalog)->id,
            'description' => 'Beratung', 'quantity' => '1', 'unit' => 'Std.', 'unit_price' => '80.00', 'service_date' => '2030-04-15', 'position' => 1,
        ]);

        try {
            app(InvoiceIssueService::class)->issue($invoice->refresh());
            $this->fail('Ein nicht abgeglichener Kopf muss das Ausstellen anhalten.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_TAX_CASE_CHANGED, $e->reason);
        }
    }

    private function assertCatalogCaseAndIssue(Invoice $invoice): void {
        $invoice->refresh();
        $this->assertTrue($invoice->tax_rate?->isZero(), 'Katalogleistung an Verbraucher im Drittland: nicht steuerbar.');
        $this->assertFalse((bool) $invoice->is_reverse_charge);
        $this->assertStringContainsString((string) config('taxation.notes.catalog_third_country'), (string) $invoice->notes);

        $issued = app(InvoiceIssueService::class)->issue($invoice)->refresh();

        $this->assertSame(InvoiceStatus::Issued, $issued->status, 'Das erste Ausstellen hält nicht mit „Steuerfall geändert“ an.');
        $this->assertSame('O', data_get($issued->tax_context, 'category'));
    }

    private function consumerAbroad(): Customer {
        return Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Privatkunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'kunde@privat.example',
            'address_street' => 'Seeweg 4',
            'address_zip' => '8001',
            'address_city' => 'Zürich',
            'country' => 'CH',
            'is_consumer' => true,
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    private function project(Customer $customer, ?PlaceOfSupplyKind $kind): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt ' . fake()->unique()->numberBetween(1, 99999),
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        if ($kind !== null) {
            $project->billingRules()->create([
                'organization_id' => $this->organization->id,
                'applies_to_kind' => null,
                'item_type' => 'service',
                'place_of_supply_kind' => $kind->value,
            ]);
        }

        return $project;
    }

    private function article(PlaceOfSupplyKind $kind): Article {
        return Article::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ArticleType::Service->value,
            'base_unit' => 'Std.',
            'place_of_supply_kind' => $kind->value,
        ]);
    }

    private function standardService(PlaceOfSupplyKind $kind): void {
        $this->organization->update(['settings' => array_replace_recursive(
            (array) $this->organization->settings,
            ['invoicing' => ['default_service_article' => 'art:' . $this->article($kind)->id]],
        )]);
        app(ServiceDefaultResolver::class)->flush();
    }

    private function schedule(Customer $customer): InvoiceSchedule {
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
            'description' => 'Betreuung {zeitraum_von} bis {zeitraum_bis}',
            'quantity' => '1.000',
            'unit' => 'pausch.',
            'unit_price' => '250.0000',
        ]);

        return $schedule;
    }
}
