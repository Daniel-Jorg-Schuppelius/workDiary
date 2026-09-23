<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValueObjectJsonFormatTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Api;

use App\Models\Customer\Customer;
use App\Models\{Expense, ExpenseCategory, Invoice, Material};
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Entscheid 2026-09-19: Die API gibt Beträge, Mengen und Prozentsätze
 * einheitlich als Wertobjekt aus (OpenAPI-Schemas Money/Quantity/Percentage).
 * Vorher mischten sich Dezimal-Strings, Objekte und „12.34 EUR".
 */
class ValueObjectJsonFormatTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_customer_rate_is_money(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'hourly_rate' => '85.00']);
        Sanctum::actingAs($this->admin, ['customers:read']);

        $this->getJson(route('api.customers.show', $customer))
            ->assertOk()
            ->assertJsonPath('data.hourly_rate', ['amount' => '85.00', 'currency' => 'EUR']);
    }

    public function test_invoice_totals_are_money(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'R2026-9001',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'issued_on' => '2026-08-01',
        ]);
        Sanctum::actingAs($this->admin, ['invoices:read']);

        $this->getJson(route('api.invoices.show', $invoice))
            ->assertOk()
            ->assertJsonPath('data.subtotal', ['amount' => '100.00', 'currency' => 'EUR'])
            ->assertJsonPath('data.total', ['amount' => '119.00', 'currency' => 'EUR']);
    }

    public function test_material_price_is_money_and_tax_rate_is_percentage(): void {
        Material::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Dübel',
            'unit' => 'Stk',
            'default_unit_price' => '0.1200',
            'tax_rate' => '19.00',
        ]);
        Sanctum::actingAs($this->admin, ['materials:read']);

        $this->getJson(route('api.materials.index'))
            ->assertOk()
            ->assertJsonPath('data.0.default_unit_price', ['amount' => '0.1200', 'currency' => 'EUR'])
            ->assertJsonPath('data.0.tax_rate', ['value' => '19.00', 'scale' => 2]);
    }

    /** Vorher „107.00 EUR" als String — aus null wurde "". */
    public function test_expense_amounts_are_money(): void {
        $category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
        $expense = Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'expense_category_id' => $category->id,
        ]);
        Sanctum::actingAs($this->admin, ['*']);

        $gross = $expense->fresh()?->amount_gross?->getAmount();
        $this->getJson(route('api.expenses.show', $expense))
            ->assertOk()
            ->assertJsonPath('data.amount_gross', ['amount' => $gross, 'currency' => 'EUR']);
    }
}
