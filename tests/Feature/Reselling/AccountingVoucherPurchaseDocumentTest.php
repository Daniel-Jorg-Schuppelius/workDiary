<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherPurchaseDocumentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Billing\DocumentOrigin;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Material\MaterialCostAllocation;
use App\Models\Platform\Organization;
use App\Models\Reselling\{ResalePurchaseEntry, ResaleSubscription};
use App\Models\Supplier\Supplier;
use App\Services\Billing\Purchase\{PurchaseDocument, PurchaseDocuments};
use App\Services\Reselling\Register\PeriodPlanner;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eingehende Belege des allgemeinen Belegspiegels als Einkaufsbelege
 * (MVP-1497): zuteilbar im Reselling-Einkauf und in der Materialkosten-
 * Zuteilung; Entwurf, Storno, Ausgang und gelöschte Belege nicht.
 */
final class AccountingVoucherPurchaseDocumentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-11');
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kabelwerk Elbe GmbH']);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(DocumentOrigin $origin, string $number, array $attributes = []): AccountingVoucher {
        return AccountingVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => $origin->value,
            'external_id' => uniqid($origin->value . '-', true),
            'supplier_id' => $this->supplier->id,
            'direction' => 'incoming',
            'document_kind' => 'invoice',
            'voucher_type' => 'bill',
            'voucher_state' => 'open',
            'voucher_number' => $number,
            'voucher_date' => '2026-04-02',
            'total_amount' => '476.00',
            'net_amount' => '400.00',
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }

    public function test_incoming_mirrored_vouchers_are_allocatable_purchase_documents(): void {
        $bill = $this->incoming(DocumentOrigin::Akaunting, 'AK-BILL-2026-14');
        $sevdesk = $this->incoming(DocumentOrigin::SevDesk, 'SD-ER-88', ['document_kind' => 'other', 'voucher_type' => 'C', 'net_amount' => null, 'total_amount' => '59.50', 'voucher_date' => '2026-03-20']);
        $credit = $this->incoming(DocumentOrigin::InvoiceNinja, 'IN-GS-3', ['document_kind' => 'credit_note', 'total_amount' => '-23.80', 'net_amount' => '-20.00', 'voucher_date' => '2026-03-01']);
        $draft = $this->incoming(DocumentOrigin::Akaunting, 'AK-ENTWURF', ['voucher_state' => 'draft']);
        $cancelled = $this->incoming(DocumentOrigin::Akaunting, 'AK-STORNO', ['voucher_state' => 'cancelled']);
        $deleted = $this->incoming(DocumentOrigin::Akaunting, 'AK-GELOESCHT', ['source_deleted_at' => now()]);
        $outgoing = $this->incoming(DocumentOrigin::Akaunting, 'AK-AUSGANG', ['direction' => 'outgoing']);
        $this->incoming(DocumentOrigin::Akaunting, 'AK-ARCHIV', ['archived' => true]);

        $registry = app(PurchaseDocuments::class);
        $documents = $registry->search($this->organization, null, CarbonImmutable::parse('2026-01-01'), 50);
        $this->assertSame(['AK-BILL-2026-14', 'SD-ER-88', 'IN-GS-3'], $documents->map(static fn (PurchaseDocument $d): ?string => $d->number)->all(), 'neueste zuerst; Entwurf, Storno, gelöscht, Ausgang und Archiv fehlen');
        $this->assertSame(['akaunting', 'sevdesk', 'invoiceninja'], $documents->map(static fn (PurchaseDocument $d): string => $d->sourceKey)->all(), 'Quelle nennt das Programm');
        $this->assertSame(['400.00', '59.50', '20.00'], $documents->map(static fn (PurchaseDocument $d): string => $d->net->getAmount())->all(), 'Netto, sonst Belegsumme; Gutschrift als Betrag');
        $this->assertSame([false, false, true], $documents->map(static fn (PurchaseDocument $d): bool => $d->credit)->all());
        $this->assertSame(['Kabelwerk Elbe GmbH'], $documents->map(static fn (PurchaseDocument $d): ?string => $d->vendorName)->unique()->values()->all());
        $this->assertSame(['SD-ER-88'], $registry->search($this->organization, 'SD-ER', null, 50)->map(static fn (PurchaseDocument $d): ?string => $d->number)->all());
        $this->assertCount(3, $registry->search($this->organization, 'Kabelwerk', null, 50), 'Suche über den Lieferanten');

        $this->assertSame((int) $bill->id, $registry->byKey($this->organization, Sqid::encode(AccountingVoucher::class, (int) $bill->id))?->morphId);
        foreach ([$draft, $cancelled, $deleted, $outgoing] as $blocked) {
            $this->assertNull($registry->byKey($this->organization, Sqid::encode(AccountingVoucher::class, (int) $blocked->id)), 'nicht zuteilbar: ' . $blocked->voucher_number);
        }
        $this->assertSame('SD-ER-88', $registry->byMorph($this->organization, $bill->getMorphClass(), (int) $sevdesk->id)?->number);
        $this->assertSame('IN-GS-3', $registry->byMorph($this->organization, $bill->getMorphClass(), (int) $credit->id)?->number);
        $this->assertNull($registry->byKey(Organization::factory()->create(), Sqid::encode(AccountingVoucher::class, (int) $bill->id)), 'fremde Organisation');
    }

    public function test_resale_purchase_dialog_and_store_accept_a_mirrored_voucher(): void {
        $admin = $this->orgAdmin();
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-7', 'label' => 'Office-Paket', 'company_name' => 'Beispiel GmbH',
            'is_own_holding' => true, 'quantity' => 1, 'starts_on' => '2026-01-01', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);
        $bill = $this->incoming(DocumentOrigin::Akaunting, 'AK-BILL-2026-14', ['voucher_date' => '2026-03-30']);

        $this->actingAs($admin)->get(route('finance.resale.purchases.create'))->assertOk()
            ->assertSee('AK-BILL-2026-14')->assertSee('Akaunting')->assertSee('Buchhaltungsprogramm');

        $this->actingAs($admin)->post(route('finance.resale.purchases.store'), [
            'document' => Sqid::encode(AccountingVoucher::class, (int) $bill->id), 'provider' => 'telekom_marketplace', 'net_amount' => '400.00', 'month' => '2026-03',
        ])->assertSessionHasNoErrors()->assertRedirect(route('finance.resale.purchases.index'));
        $entry = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame($bill->getMorphClass(), $entry->document_type);
        $this->assertSame((int) $bill->id, (int) $entry->document_id);
        $this->actingAs($admin)->get(route('finance.resale.purchases.index'))->assertOk()->assertSee('AK-BILL-2026-14')->assertSee('Akaunting');
    }

    public function test_material_cost_allocation_offers_and_books_a_mirrored_voucher_but_not_a_credit(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werft am Strom']);
        $bill = $this->incoming(DocumentOrigin::SevDesk, 'SD-ER-90', ['document_kind' => 'other', 'voucher_type' => 'C']);
        $credit = $this->incoming(DocumentOrigin::SevDesk, 'SD-GS-91', ['document_kind' => 'credit_note']);

        $this->actingAs($admin)->get(route('customers.material-costs.create', $customer))->assertOk()
            ->assertSee('SD-ER-90')->assertDontSee('SD-GS-91');

        $this->actingAs($admin)->post(route('customers.material-costs.store', $customer), [
            'document' => Sqid::encode(AccountingVoucher::class, (int) $bill->id), 'allocated_amount' => '150.00', 'allocated_on' => '2026-04-05',
        ])->assertSessionHasNoErrors()->assertRedirect(route('customers.show', $customer));
        $allocation = MaterialCostAllocation::query()->firstOrFail();
        $this->assertSame([$bill->getMorphClass(), (int) $bill->id, 'SD-ER-90'], [$allocation->source_type, (int) $allocation->source_id, $allocation->description]);

        $this->actingAs($admin)->post(route('customers.material-costs.store', $customer), [
            'document' => Sqid::encode(AccountingVoucher::class, (int) $credit->id), 'allocated_amount' => '10.00', 'allocated_on' => '2026-04-05',
        ])->assertSessionHasErrors('document');
        $this->actingAs($admin)->post(route('customers.material-costs.store', $customer), [
            'document' => Sqid::encode(AccountingVoucher::class, (int) $bill->id), 'allocated_amount' => '450.00', 'allocated_on' => '2026-04-05',
        ])->assertSessionHasErrors('allocated_amount');
    }
}
