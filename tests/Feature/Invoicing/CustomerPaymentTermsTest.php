<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerPaymentTermsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Finance\ProfitDetermination;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoiceSchedule};
use App\Models\Platform\{Organization, User};
use App\Services\Accounting\{AccountingProfileService, FiscalYearService};
use App\Services\Accounting\Reports\LiquidityForecastBuilder;
use App\Services\Invoicing\InvoiceIssueService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-996: Zahlungsziel und Skonto als Vorgabe am Kunden. */
final class CustomerPaymentTermsTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(CarbonImmutable::create(2026, 3, 4, 9, 0, 0, 'UTC'));
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes = []): Customer {
        return Customer::factory()->create(['organization_id' => $this->org->id, 'country' => 'DE'] + $attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function draft(Customer $customer, array $attributes = []): Invoice {
        $invoice = Invoice::query()->create($attributes + [
            'organization_id' => $this->org->id, 'customer_id' => $customer->id, 'number' => 'R-' . random_int(1000, 9999),
            'status' => Invoice::STATUS_DRAFT, 'type' => Invoice::TYPE_INVOICE, 'tax_rate' => '19.00',
        ]);
        $invoice->items()->create(['organization_id' => $this->org->id, 'description' => 'Leistung', 'quantity' => '1', 'unit' => 'h', 'unit_price' => '100.00', 'position' => 1]);

        return $invoice;
    }

    public function test_new_invoices_take_the_customer_terms_and_issue_freezes_the_resolved_term(): void {
        $customer = $this->customer(['payment_terms_days' => 30, 'skonto_percent' => '2.00', 'skonto_days' => 10]);

        $invoice = $this->draft($customer);
        $this->assertSame(30, $invoice->payment_terms_days);
        $this->assertSame('2.00', $invoice->skonto_percent?->getNumericValue());
        $this->assertSame(10, $invoice->skonto_days);

        app(InvoiceIssueService::class)->issue($invoice);
        $this->assertSame('2026-04-03', $invoice->refresh()->due_on?->toDateString());

        $own = $this->draft($customer, ['payment_terms_days' => 7, 'skonto_percent' => null, 'skonto_days' => 5]);
        $this->assertSame(7, $own->payment_terms_days, 'eigene Angabe am Beleg gewinnt');
        $this->assertNull($own->skonto_percent, 'teilweise gesetztes Skonto wird nicht ergänzt');

        $credit = $this->draft($customer, ['type' => Invoice::TYPE_CREDIT_NOTE]);
        $this->assertNull($credit->payment_terms_days);
        $this->assertNull($credit->skonto_days);
    }

    public function test_without_customer_terms_the_organisation_and_then_fourteen_days_apply(): void {
        $plain = $this->customer();
        $first = $this->draft($plain);
        $this->assertNull($first->payment_terms_days);
        app(InvoiceIssueService::class)->issue($first);
        $this->assertSame(14, $first->refresh()->payment_terms_days);
        $this->assertSame('2026-03-18', $first->due_on?->toDateString());

        Setting::set('einvoice.payment_terms_days', 21, SettingScope::Organization, $this->org);
        $second = $this->draft($plain);
        app(InvoiceIssueService::class)->issue($second);
        $this->assertSame(21, $second->refresh()->payment_terms_days);
        $this->assertSame('2026-03-25', $second->due_on?->toDateString());
    }

    public function test_customer_form_stores_the_terms_and_requires_both_skonto_parts(): void {
        $customer = $this->customer(['name' => 'Muster GmbH', 'currency' => 'EUR']);

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Muster GmbH', 'currency' => 'EUR', 'payment_terms_days' => '30', 'skonto_percent' => '2',
        ])->assertSessionHasErrors('skonto_days');

        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Muster GmbH', 'currency' => 'EUR', 'payment_terms_days' => '30', 'skonto_percent' => '2', 'skonto_days' => '10',
        ])->assertSessionHasNoErrors();
        $customer->refresh();
        $this->assertSame(30, $customer->payment_terms_days);
        $this->assertSame('2.00', $customer->skonto_percent?->getNumericValue());

        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertOk()
            ->assertSee(__(':percent innerhalb von :days Tagen', ['percent' => '2,00 %', 'days' => 10]));
    }

    public function test_liquidity_forecast_uses_the_customer_term_for_invoice_schedules(): void {
        app(AccountingProfileService::class)->configure($this->org, [
            'profit_determination' => ProfitDetermination::DoubleEntry, 'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1, 'starts_on' => CarbonImmutable::create(2026, 1, 1), 'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->org, CarbonImmutable::create(2026, 1, 1));
        app(AccountingProfileService::class)->activateLocal($this->org, $this->admin);

        $schedule = InvoiceSchedule::query()->create([
            'organization_id' => $this->org->id, 'customer_id' => $this->customer(['payment_terms_days' => 30])->id,
            'title' => 'Wartung', 'interval_unit' => 'month', 'interval_count' => 1, 'billing_period_mode' => 'previous',
            'next_run_on' => '2026-03-05', 'status' => 'active', 'created_by' => $this->admin->id,
        ]);
        $schedule->items()->create(['organization_id' => $this->org->id, 'position' => 1, 'description' => 'Wartung', 'quantity' => '1', 'unit' => 'x', 'unit_price' => '100.00', 'tax_rate' => '19.00']);

        $buckets = app(LiquidityForecastBuilder::class)->build($this->org, CarbonImmutable::create(2026, 3, 4))['buckets'];
        $this->assertSame('0.00', $buckets[2]['sources']['invoice_schedules']['in'], 'nicht mehr pauschal 14 Tage');
        $this->assertSame('119.00', $buckets[4]['sources']['invoice_schedules']['in'], '05.03. + 30 Tage = 04.04.');
    }
}
