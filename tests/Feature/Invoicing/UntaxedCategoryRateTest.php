<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UntaxedCategoryRateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceScheduleStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\InvoiceSchedule;
use App\Models\Platform\User;
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1623: Eine Position mit Steuerkategorie ohne Steuer (Z, E, AE, K, G, O)
 * trägt 0 % — die Eingabe setzt den Satz, statt dass erst die E-Rechnungs-
 * Prüfung anhält; die Summenrechnung bleibt unberührt.
 */
final class UntaxedCategoryRateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispiel GmbH',
            'currency' => 'EUR',
            'country' => 'DE',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin);
    }

    public function test_invoice_line_with_a_category_without_tax_gets_zero_percent(): void {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($this->customer);
        $this->assertTrue($invoice->tax_rate?->isPositive());

        foreach (['K' => '', 'O' => '19', 'E' => null] as $category => $rate) {
            $this->post(route('invoices.items.store', $invoice), $this->line($category, $rate))->assertRedirect();
        }
        $this->post(route('invoices.items.store', $invoice), $this->line('S', '7'))->assertRedirect();

        $rates = $invoice->items()->orderBy('position')->get()->mapWithKeys(fn ($item): array => [(string) $item->tax_category => $item->tax_rate?->getNumericValue()])->all();
        $this->assertEqualsCanonicalizing(['K' => '0.00', 'O' => '0.00', 'E' => '0.00', 'S' => '7.00'], $rates);
        // Der Kopfsatz greift nur noch für die Position mit Steuer.
        $invoice->refresh()->load('items');
        $this->assertSame('11.20', $invoice->tax_amount?->getAmount());
    }

    public function test_changing_the_category_of_an_existing_line_resets_the_rate(): void {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($this->customer);
        $this->post(route('invoices.items.store', $invoice), $this->line('S', '19'))->assertRedirect();
        $item = $invoice->items()->firstOrFail();

        $this->put(route('invoices.items.update', [$invoice, $item]), $this->line('G', '19'))->assertRedirect();

        $this->assertSame('0.00', $item->refresh()->tax_rate?->getNumericValue());
    }

    public function test_schedule_line_with_a_category_without_tax_gets_zero_percent(): void {
        $schedule = InvoiceSchedule::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'title' => 'Wartungsvertrag',
            'interval_unit' => InvoiceSchedule::UNIT_MONTH,
            'interval_count' => 1,
            'billing_period_mode' => InvoiceSchedule::MODE_PREVIOUS,
            'next_run_on' => '2030-06-01',
            'status' => InvoiceScheduleStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $this->post(route('invoice-schedules.items.store', $schedule), $this->line('AE', ''))->assertRedirect();

        $item = $schedule->items()->firstOrFail();
        $this->assertSame('AE', $item->tax_category);
        $this->assertSame('0.00', $item->tax_rate?->getNumericValue());
    }

    public function test_item_form_binds_the_rate_to_the_category(): void {
        $invoice = app(InvoiceGenerator::class)->emptyDraft($this->customer);

        $html = $this->get(route('invoices.items.create', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('data-prefill-from="tax_category"', (string) $html);
        $this->assertMatchesRegularExpression('/<option value="K"[^>]*data-prefill-rate="0"/', (string) $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="S"[^>]*data-prefill-rate/', (string) $html);
        $this->assertStringContainsString(e((string) __('invoicing.item.tax_category_hint')), (string) $html);
    }

    /** @return array<string, string|null> */
    private function line(string $category, ?string $rate): array {
        return [
            'description' => 'Position ' . $category,
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'tax_category' => $category,
            'tax_rate' => $rate,
        ];
    }
}
