<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalCommissionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\{CommissionReversalKind, CommissionScope, CommissionStatus};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionRule, InvoiceCommission, Lead};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeVoucherSync;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Provisionen an gespiegelten Rechnungen des Buchhaltungsprogramms (MVP-1119). */
final class ExternalCommissionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $seller;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->seller = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Vera Vertrieb']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'contact-muster',
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
        ]);
        CommissionRule::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Grundsatz', 'scope' => CommissionScope::All,
            'rate_percent' => '10.00', 'priority' => 100, 'is_active' => true,
        ]);
    }

    private function lead(): void {
        Lead::query()->create([
            'organization_id' => $this->organization->id, 'company' => 'Muster GmbH', 'source' => 'referral',
            'status' => 'converted', 'customer_id' => $this->customer->id, 'responsible_user_id' => $this->seller->id,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(string $externalId, array $attributes = []): LexofficeVoucher {
        return LexofficeVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'contact_external_id' => 'contact-muster',
            'customer_id' => $this->customer->id, 'voucher_type' => 'invoice', 'voucher_status' => 'open', 'voucher_number' => 'LX-' . $externalId,
            'voucher_date' => '2026-08-01', 'total_amount' => '119.00', 'open_amount' => '119.00', 'net_amount' => '100.00', 'currency' => 'EUR',
        ]);
    }

    /** @param list<array<string, mixed>> $items */
    private function sync(array $items): void {
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/voucherlist*' => FakePluginHttp::response(['content' => $items, 'totalPages' => 1]),
            'https://api.lexware.io/v1/vouchers/*' => FakePluginHttp::response([]),
            // Zahlungen laufen über das SDK, das den Body nicht zurückspult: je Abruf eine frische Antwort.
            'https://api.lexware.io/v1/payments/*' => static fn () => FakePluginHttp::response([]),
        ]);
        (new LexofficeVoucherSync('test-key'))->sync($this->organization);
    }

    /** @return array<string, mixed> */
    private function item(string $externalId, string $status, float $open, string $type = 'invoice'): array {
        return [
            'id' => $externalId, 'contactId' => 'contact-muster', 'voucherType' => $type, 'voucherStatus' => $status,
            'voucherNumber' => 'LX-' . $externalId, 'voucherDate' => '2026-08-01', 'totalAmount' => 119.0, 'openAmount' => $open, 'currency' => 'EUR',
        ];
    }

    public function test_observed_payment_accrues_commission_on_the_net_amount(): void {
        $this->lead();
        $voucher = $this->voucher('v1');

        $this->sync([$this->item('v1', 'paid', 0.0)]);

        $row = InvoiceCommission::query()->sole();
        $this->assertNull($row->invoice_id);
        $this->assertSame($voucher->getMorphClass(), $row->document_type);
        $this->assertSame($voucher->id, $row->document_id);
        $this->assertSame('LX-v1', $row->document_number);
        $this->assertSame($this->customer->id, $row->customer_id);
        $this->assertSame($this->seller->id, $row->user_id);
        $this->assertSame('100.00', $row->base_amount?->getAmount());
        $this->assertSame('10.00', $row->commission_amount?->getAmount());
    }

    public function test_handed_over_local_invoice_keeps_its_commission_local(): void {
        $this->lead();
        \App\Models\Invoicing\Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'number' => 'LX-v1']);
        $this->voucher('v1');

        $this->sync([$this->item('v1', 'paid', 0.0)]);

        $this->assertSame(0, InvoiceCommission::query()->where('document_type', (new LexofficeVoucher)->getMorphClass())->count());
    }

    public function test_cancellation_reverses_and_vouchers_mirrored_as_paid_stay_manual(): void {
        $this->lead();
        $this->voucher('v1');
        $this->sync([$this->item('v1', 'paid', 0.0), $this->item('v-old', 'paid', 0.0)]);
        $this->assertSame(1, InvoiceCommission::query()->count(), 'Ein schon bezahlt gespiegelter Beleg erzeugt nichts von selbst.');

        $this->sync([$this->item('v1', 'voided', 0.0), $this->item('v-old', 'paid', 0.0)]);

        $original = InvoiceCommission::query()->whereNull('reversal_of_id')->sole();
        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->sole();
        $this->assertSame(CommissionReversalKind::Cancellation, $reversal->reversal_kind);
        $this->assertSame(CommissionStatus::Reversed, $original->fresh()?->status);
    }

    public function test_paid_credit_note_reduces_the_commission_of_its_invoice(): void {
        $this->lead();
        $this->voucher('v1');
        $this->sync([$this->item('v1', 'paid', 0.0)]);
        $this->voucher('gs1', ['voucher_type' => 'creditnote', 'total_amount' => '23.80', 'open_amount' => '23.80', 'net_amount' => '20.00', 'related_voucher_ids' => ['v1']]);

        $this->sync([$this->item('v1', 'paid', 0.0), $this->item('gs1', 'paidoff', 0.0, 'creditnote')]);

        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->sole();
        $this->assertSame(CommissionReversalKind::CreditNote, $reversal->reversal_kind);
        $this->assertSame('-20.00', $reversal->base_amount?->getAmount());
    }

    /** MVP-1122: Bezug der Gutschrift kommt erst mit dem Positions-Sync nach der Erstattung. */
    public function test_credit_note_relation_loaded_after_refund_still_reduces(): void {
        $this->lead();
        $this->voucher('v1');
        $this->sync([$this->item('v1', 'paid', 0.0)]);
        $creditNote = $this->voucher('gs2', ['voucher_type' => 'creditnote', 'voucher_status' => 'paidoff', 'total_amount' => '23.80', 'open_amount' => '0.00', 'net_amount' => '20.00']);
        $this->assertSame(0, InvoiceCommission::query()->whereNotNull('reversal_of_id')->count());

        FakePluginHttp::fake(['https://api.lexware.io/v1/credit-notes/gs2' => FakePluginHttp::response([
            'id' => 'gs2', 'title' => 'Gutschrift', 'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 20.0],
            'relatedVouchers' => [['id' => 'v1', 'voucherNumber' => 'LX-v1', 'voucherType' => 'invoice']], 'lineItems' => [],
        ])]);
        (new \App\Plugins\Lexoffice\Services\LexofficeVoucherLineSync('test-key'))->withoutThrottle()->syncVoucher($creditNote);
        (new \App\Plugins\Lexoffice\Services\LexofficeVoucherLineSync('test-key'))->withoutThrottle()->syncVoucher($creditNote->fresh() ?? $creditNote);

        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->sole();
        $this->assertSame('-20.00', $reversal->base_amount?->getAmount());
    }

    public function test_unassigned_paid_voucher_is_listed_and_assigned_by_hand(): void {
        $voucher = $this->voucher('v1', ['voucher_status' => 'paid', 'open_amount' => '0.00', 'paid_date' => '2026-08-20']);
        $token = Sqid::encode(LexofficeVoucher::class, (int) $voucher->id);

        $this->actingAs($this->admin)->get(route('commissions.index'))->assertOk()->assertSee('LX-v1')
            ->assertSee(route('commissions.document.assign.form', ['type' => $voucher->getMorphClass(), 'document' => $token]), false);
        $this->actingAs($this->admin)->get(route('commissions.document.assign.form', ['type' => $voucher->getMorphClass(), 'document' => $token]))->assertOk()->assertSee('LX-v1');

        $this->actingAs($this->admin)
            ->post(route('commissions.document.assign', ['type' => $voucher->getMorphClass(), 'document' => $token]), ['user_id' => Sqid::encode(User::class, (int) $this->seller->id)])
            ->assertRedirect();

        $this->assertSame($this->seller->id, $voucher->fresh()?->sales_user_id);
        $row = InvoiceCommission::query()->sole();
        $this->assertSame('10.00', $row->commission_amount?->getAmount());
        $this->actingAs($this->admin)->get(route('commissions.index'))->assertOk()->assertSee('Muster GmbH');
    }
}
