<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherSourcesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Sales\CommissionScope;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionRule, InvoiceCommission, Lead};
use App\Models\Supplier\Supplier;
use App\Services\Billing\Contracts\{ExternalPurchases, ExternalRevenue};
use App\Services\Billing\Dto\ExternalInvoiceQuery;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Der allgemeine Belegspiegel (sevDesk, easybill, JTL, InvoicePlane) als Quelle (MVP-1127–1129). */
final class AccountingVoucherSourcesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(string $number, array $attributes = []): AccountingVoucher {
        return AccountingVoucher::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'easybill',
            'customer_id' => $this->customer->id,
            'direction' => DocumentDirection::Outgoing->value,
            'document_kind' => DocumentKind::Invoice->value,
            'voucher_state' => AccountingVoucherState::Open,
            'voucher_number' => $number,
            'voucher_date' => '2026-06-10',
            'due_date' => '2026-06-20',
        ]);
    }

    public function test_outgoing_vouchers_feed_the_revenue_contract(): void {
        $this->voucher('EB-1');
        $this->voucher('EB-2', ['voucher_state' => AccountingVoucherState::Paid, 'open_amount' => '0.00']);
        $this->voucher('EB-GS', ['document_kind' => DocumentKind::CreditNote->value, 'total_amount' => '23.80', 'net_amount' => '20.00']);
        $this->voucher('SD-1', ['plugin_id' => 'sevdesk', 'direction' => null, 'document_kind' => null, 'voucher_state' => null, 'voucher_type' => 'C', 'voucher_status' => '100']);
        $this->voucher('EB-STORNO', ['document_kind' => DocumentKind::Cancellation->value]);
        $this->voucher('EB-VOID', ['voucher_state' => AccountingVoucherState::Cancelled]);
        $this->voucher('EB-DRAFT', ['voucher_state' => AccountingVoucherState::Draft]);
        $this->voucher('EB-IN', ['direction' => DocumentDirection::Incoming->value]);
        Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'number' => 'EB-LOCAL']);
        $this->voucher('EB-LOCAL');

        $invoices = collect(app(ExternalRevenue::class)->invoices(new ExternalInvoiceQuery))->keyBy('number');

        $this->assertEqualsCanonicalizing(['EB-1', 'EB-2', 'EB-GS', 'SD-1'], $invoices->keys()->all());
        $this->assertSame('easybill', $invoices['EB-1']->source);
        $this->assertSame('sevdesk', $invoices['SD-1']->source);
        $this->assertSame(InvoiceStatus::Paid, $invoices['EB-2']->status);
        $this->assertSame(-23.8, $invoices['EB-GS']->gross);
        $this->assertSame(Invoice::TYPE_CREDIT_NOTE, $invoices['EB-GS']->type);
        $this->assertSame(100.0, $invoices['EB-1']->net);
        $this->assertSame(['count' => 4, 'total' => 333.2], app(ExternalRevenue::class)->perCustomer('2026-06-01', '2026-06-30')[$this->customer->id]);
        $this->assertSame([$this->customer->id], app(ExternalRevenue::class)->customerIdsWithDocuments((int) $this->organization->id));
    }

    public function test_incoming_vouchers_feed_purchases_and_open_payables(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->voucher('ER-1', ['direction' => DocumentDirection::Incoming->value, 'customer_id' => null, 'supplier_id' => $supplier->id, 'total_amount' => '50.00', 'open_amount' => '50.00', 'due_date' => '2026-06-01']);
        $this->voucher('ER-2', ['direction' => DocumentDirection::Incoming->value, 'customer_id' => null, 'supplier_id' => null, 'total_amount' => '30.00', 'open_amount' => '30.00', 'due_date' => '2026-07-30']);
        $this->voucher('ER-PAID', ['direction' => DocumentDirection::Incoming->value, 'customer_id' => null, 'supplier_id' => $supplier->id, 'voucher_state' => AccountingVoucherState::Paid, 'open_amount' => '0.00']);

        $purchases = collect(app(ExternalPurchases::class)->purchases(null, null, [(int) $supplier->id]))->sortBy('amount')->values();
        $this->assertSame([50.0, 119.0], $purchases->pluck('amount')->all());
        $this->assertSame([50.0, 0.0], $purchases->pluck('open')->all());

        $payables = app(ExternalPurchases::class)->openPayables((int) $this->organization->id, '2026-06-15');
        $this->assertSame(['open' => 80.0, 'overdue' => 50.0, 'count' => 2], $payables);
    }

    public function test_observed_payment_in_the_mirror_accrues_commission(): void {
        $seller = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'X', 'source' => 'referral', 'status' => 'converted',
            'customer_id' => $this->customer->id, 'responsible_user_id' => $seller->id,
        ]);
        CommissionRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Grundsatz', 'scope' => CommissionScope::All,
            'rate_percent' => '10.00', 'priority' => 100, 'is_active' => true,
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => 'easybill', 'external_type' => 'contact',
            'external_id' => 'c-1', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id,
        ]);
        $mirror = app(VoucherMirror::class);
        $counters = VoucherMirror::counters();
        $mirrored = fn (string $id, AccountingVoucherState $state, string $open): MirroredVoucher => new MirroredVoucher(
            externalId: $id, direction: DocumentDirection::Outgoing, kind: DocumentKind::Invoice, state: $state,
            number: 'EB-' . $id, date: '2026-06-10', totalAmount: '119.00', netAmount: '100.00', openAmount: $open, contactExternalId: 'c-1',
        );

        $mirror->store((int) $this->organization->id, 'easybill', $mirrored('old', AccountingVoucherState::Paid, '0.00'), $counters);
        $mirror->store((int) $this->organization->id, 'easybill', $mirrored('new', AccountingVoucherState::Open, '119.00'), $counters);
        $this->assertSame(0, InvoiceCommission::query()->count(), 'Erstes Spiegeln erzeugt keine Provision.');

        $mirror->store((int) $this->organization->id, 'easybill', $mirrored('new', AccountingVoucherState::Paid, '0.00'), $counters);

        $row = InvoiceCommission::query()->sole();
        $this->assertSame((new AccountingVoucher)->getMorphClass(), $row->document_type);
        $this->assertSame('EB-new', $row->document_number);
        $this->assertSame('10.00', $row->commission_amount?->getAmount());
    }
}
