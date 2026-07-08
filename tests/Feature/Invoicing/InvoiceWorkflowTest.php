<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceWorkflowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\TaxResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 066, MVP-163: §-19-Regel wirkt jetzt auch LOKAL (TaxResolver),
 * optionaler Freigabe-Zwang vor Ausstellung, Mahnstatus nur für
 * überfällige Rechnungen (max. Stufe 3), Zahlungsziel je Rechnung.
 */
final class InvoiceWorkflowTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'country' => 'DE']);
    }

    private function draft(array $overrides = []): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_DRAFT,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            ...$overrides,
        ]);
    }

    public function test_small_business_rule_wins_in_tax_resolver(): void {
        $this->org->update(['settings' => ['einvoice' => ['small_business' => '1']]]);

        $result = app(TaxResolver::class)->resolve($this->org->fresh(), $this->customer);

        $this->assertSame('0.00', $result['rate']);
        $this->assertFalse($result['reverse_charge']);
        $this->assertStringContainsString('§ 19', (string) $result['note']);
    }

    public function test_require_approval_blocks_issue_until_approved(): void {
        $this->org->update(['settings' => ['invoicing' => ['require_approval' => '1']]]);
        $invoice = $this->draft();

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->fresh()->status, 'Ohne Freigabe keine Ausstellung.');

        $this->actingAs($this->user)->post(route('invoices.approve', $invoice))->assertRedirect();
        $this->assertNotNull($invoice->fresh()->approved_at);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()->status);
    }

    public function test_payment_terms_days_drive_due_date(): void {
        $invoice = $this->draft(['payment_terms_days' => 30]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice));

        $this->assertSame(
            now()->addDays(30)->toDateString(),
            $invoice->fresh()->due_on->toDateString(),
        );
    }

    public function test_dunning_only_for_overdue_and_capped(): void {
        $notDue = $this->draft(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now(), 'due_on' => now()->addWeek()]);
        $this->actingAs($this->user)->post(route('invoices.dun', $notDue));
        $this->assertSame(0, (int) $notDue->fresh()->dunning_level, 'Nicht fällig → keine Mahnung.');

        $overdue = $this->draft(['status' => Invoice::STATUS_ISSUED, 'issued_on' => now()->subMonth(), 'due_on' => now()->subWeek()]);
        foreach ([1, 2, 3] as $level) {
            $this->actingAs($this->user)->post(route('invoices.dun', $overdue))->assertRedirect();
            $this->assertSame($level, (int) $overdue->fresh()->dunning_level);
        }

        // Stufe 4 gibt es nicht.
        $this->actingAs($this->user)->post(route('invoices.dun', $overdue));
        $this->assertSame(3, (int) $overdue->fresh()->dunning_level);
        $this->assertNotNull($overdue->fresh()->dunned_at);
    }
}
