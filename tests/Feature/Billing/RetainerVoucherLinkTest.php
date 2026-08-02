<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerVoucherLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Finance\BillingMode;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate, CustomerBillingStatement};
use App\Models\{Customer, LexofficeVoucher, User};
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Handverknüpfung eines bereits in Lexoffice geführten Belegs an
 * einen Retainer-Monat — für Bestände, die workDiary nie gepusst hat.
 */
class RetainerVoucherLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private CustomerBillingAgreement $agreement;

    private CustomerBillingStatement $statement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => BillingMode::Lexoffice->value,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'mode' => 'retainer',
            'expected_monthly_amount' => 650.00,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'hourly_rate' => 18.00,
        ]);
        $this->statement = app(CustomerAccountStatementService::class)->ensure($this->agreement, 2026, 4);
    }

    private function voucher(string $uuid = 'lex-manual-1', float $total = 773.50, float $net = 650.00): LexofficeVoucher {
        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => $uuid,
            'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'paid',
            'voucher_number' => 'RE-2026-0042',
            // Bewusst außerhalb des Statement-Monats: der Auto-Match griffe hier
            // nicht, die Handverknüpfung muss trotzdem gehen.
            'voucher_date' => '2026-05-04',
            'total_amount' => $total,
            'open_amount' => 0.00,
            'net_amount' => $net,
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }

    public function test_dialog_lists_linkable_vouchers(): void {
        $voucher = $this->voucher();

        $this->actingAs($this->user)
            ->get(route('customers.billing.retainer.voucher.edit', [$this->customer, $this->statement]))
            ->assertOk()
            ->assertSee($voucher->sqid)
            ->assertSee('RE-2026-0042');
    }

    public function test_linking_books_the_net_payment(): void {
        $voucher = $this->voucher();

        $this->actingAs($this->user)
            ->post(route('customers.billing.retainer.voucher.link', [$this->customer, $this->statement]), [
                'voucher' => $voucher->sqid,
            ])
            ->assertRedirect(route('customers.show', $this->customer));

        $this->assertSame($voucher->id, $this->statement->fresh()->lexoffice_voucher_id);
        // 773,50 brutto → 650,00 netto im Leistungssaldo.
        $this->assertSame('650.00', $this->agreement->payments()->firstOrFail()->amount?->getAmount());
    }

    public function test_unlinking_reverts_the_payment(): void {
        $voucher = $this->voucher();
        $this->actingAs($this->user)->post(route('customers.billing.retainer.voucher.link', [$this->customer, $this->statement]), [
            'voucher' => $voucher->sqid,
        ]);

        $this->actingAs($this->user)
            ->delete(route('customers.billing.retainer.voucher.unlink', [$this->customer, $this->statement]))
            ->assertRedirect(route('customers.show', $this->customer));

        $this->assertNull($this->statement->fresh()->lexoffice_voucher_id);
        $this->assertSame(0, $this->agreement->payments()->count());
    }

    public function test_voucher_of_another_customer_is_rejected(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = $this->voucher('lex-foreign-1');
        $foreign->update(['customer_id' => $other->id]);

        $this->actingAs($this->user)
            ->post(route('customers.billing.retainer.voucher.link', [$this->customer, $this->statement]), [
                'voucher' => $foreign->sqid,
            ])
            ->assertNotFound();

        $this->assertNull($this->statement->fresh()->lexoffice_voucher_id);
    }
}
