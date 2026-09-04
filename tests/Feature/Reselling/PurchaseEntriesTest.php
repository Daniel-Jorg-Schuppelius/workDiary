<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseEntriesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\{Customer, LexofficeVoucher, Supplier};
use App\Models\Domain\{DomainAccountingEntry, DomainProjection, DomainProviderConnection};
use App\Models\Reselling\{ResalePurchaseEntry, ResaleSubscription};
use App\Services\Reselling\Marketplace\QualityHostingInvoiceReader;
use App\Services\Reselling\Register\{DomainSubscriptionSync, PeriodPlanner, PurchaseAllocator};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einkaufsbelege (Feature 152, MVP-762): Quality-Hosting-Rechnung
 * positionsgenau, Eingangsbeleg pro rata, Domain-Buchungen, Oberfläche.
 */
class PurchaseEntriesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const INVOICE_TEXT = <<<'TXT'
QualityHosting GmbH- Postbox 791407 - D-11516 Berlin
Rechnung
Rechnungsnr. 31970911 Kundennr. 95229
Rechnungsdatum 3. September 2026
Pos. Menge Beschreibung Einzelpreis Gesamtpreis
Endkunde: CNL00007 (Klimpel Bäder GmbH)
Vertrag: CNLCON00156
1 1 Microsoft 365 Business Premium 187,92 187,92
Grundgebühr pro Einheit
Dienst: CNLOUI
Vertrag: CNLCON00156
03.09.26 - 02.09.27
Endkunde: CNL00010 (Schub- und Schleppreederei U. Golka GmbH &
Co.KG) Vertrag: CNLCON00109
2 2 Exchange Online Plan 1 34,32 68,64
Grundgebühr pro Einheit
Dienst: CNLOUK
Vertrag: CNLCON00109
15.08.26 - 14.08.27
Endkunde: CNL00002 ( Ambulanter Pflegedienst Regina Zukanovic
GmbH) Vertrag: CNLCON00062
3 1 Microsoft 365 Apps for Business 79,49096 79,49
Grundgebühr pro Einheit
Vertrag: CNLCON00062
06.05.26 - 25.03.27
Total EUR ohne MwSt. 336,05
TXT;

    private const CREDIT_TEXT = <<<'TXT'
Storno zu Rechnung Seite 1
Gutschriftsnr. 7006872 Kundennr. 95229
Gutschriftsdatum 2. Juni 2026
Pos. Menge Beschreibung Rabatt % Einzelpreis Gesamtpreis
0 1 Umzugsbonus Endkunde Klimpel Bäder GmbH (CNL00007) 100,00 100,00
0 1 Umzugsbonus Endkunde Schub- und Schleppreederei U. Golka GmbH 100,00 100,00
& Co.KG (CNL00010)
Total EUR ohne MwSt. 200,00
TXT;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    private function subscription(string $contract, string $label, string $company, string $startsOn, int $quantity = 1, string $provider = 'qualityhosting'): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => $provider, 'external_id' => $contract, 'label' => $label, 'company_name' => $company,
            'is_own_holding' => true, 'quantity' => $quantity, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_reader_parses_invoice_and_credit_note(): void {
        $reader = new QualityHostingInvoiceReader;
        $invoice = $reader->parse(self::INVOICE_TEXT);
        $this->assertSame('31970911', $invoice->number);
        $this->assertSame('2026-09-03', $invoice->date?->toDateString());
        $this->assertFalse($invoice->credit);
        $this->assertSame('95229', $invoice->customerNumber);
        $this->assertCount(3, $invoice->lines);
        $this->assertSame(336.05, $invoice->linesTotal());
        $this->assertSame(336.05, $invoice->netTotal);
        [$a, $b, $c] = $invoice->lines;
        $this->assertSame('CNLCON00156', $a->contract);
        $this->assertSame('Klimpel Bäder GmbH', $a->companyName);
        $this->assertSame('2026-09-03', $a->periodStart?->toDateString());
        $this->assertSame('CNLCON00109', $b->contract);
        $this->assertSame('Schub- und Schleppreederei U. Golka GmbH & Co.KG', $b->companyName, 'umgebrochener Name');
        $this->assertSame(68.64, $b->total);
        $this->assertSame('Ambulanter Pflegedienst Regina Zukanovic GmbH', $c->companyName);
        $this->assertSame(79.49, $c->total, 'fünfstelliger Einzelpreis, zweistelliger Gesamtpreis');

        $credit = $reader->parse(self::CREDIT_TEXT);
        $this->assertTrue($credit->credit);
        $this->assertSame('7006872', $credit->number);
        $this->assertCount(2, $credit->lines);
        $this->assertSame(-100.0, $credit->lines[0]->total);
        $this->assertSame('CNL00007', $credit->lines[0]->companyKey);
        $this->assertSame('Klimpel Bäder GmbH', $credit->lines[0]->companyName);
        $this->assertSame('Umzugsbonus', $credit->lines[0]->description);
        $this->assertSame('CNL00010', $credit->lines[1]->companyKey, 'umgebrochene Gutschriftzeile');
        $this->assertNull($credit->lines[0]->contract);
    }

    public function test_provider_invoice_lands_exactly_on_contract_periods_and_credits_hit_the_company(): void {
        $klimpel = $this->subscription('CNLCON00156', 'Microsoft 365 Business Premium', 'Klimpel Bäder GmbH', '2025-09-03');
        $schub = $this->subscription('CNLCON00109', 'Exchange Online Plan 1', 'Schub- und Schleppreederei U. Golka GmbH & Co.KG', '2025-08-15', 2);
        $reader = new QualityHostingInvoiceReader;
        $allocator = app(PurchaseAllocator::class);

        $result = $allocator->importProviderInvoice($this->organization, $reader->parse(self::INVOICE_TEXT), SubscriptionProvider::QualityHosting, null, '31970911.pdf');
        $this->assertSame(3, $result['lines']);
        $this->assertSame(2, $result['matched']);
        $this->assertCount(1, $result['unmatched'], 'CNLCON00062 gibt es nicht');
        $this->assertSame(256.56, $result['net']);

        $entry = ResalePurchaseEntry::query()->where('subscription_id', $klimpel->id)->firstOrFail();
        $this->assertSame('31970911', $entry->document_number);
        $this->assertSame('187.92', $entry->net_amount->getAmount());
        $this->assertSame('2026-09-03', $entry->period?->starts_on->toDateString(), 'Periode laut Laufzeit der Position');
        $this->assertSame(187.92, $entry->period?->fresh()?->load('purchases')->actualPurchase());

        // Nochmal: Duplikate werden erkannt.
        $again = $allocator->importProviderInvoice($this->organization, $reader->parse(self::INVOICE_TEXT), SubscriptionProvider::QualityHosting);
        $this->assertSame(2, $again['duplicates']);
        $this->assertSame(2, ResalePurchaseEntry::query()->count());

        // Gutschrift ohne Vertrag → Firma → erstes Abo, Periode am Belegdatum.
        $credit = $allocator->importProviderInvoice($this->organization, $reader->parse(self::CREDIT_TEXT), SubscriptionProvider::QualityHosting);
        $this->assertSame(2, $credit['matched']);
        $bonus = ResalePurchaseEntry::query()->where('subscription_id', $schub->id)->where('document_number', '7006872')->firstOrFail();
        $this->assertSame('-100.00', $bonus->net_amount->getAmount());
        $this->assertSame('2025-08-15', $bonus->period?->starts_on->toDateString(), 'Gutschrift vom 02.06.2026 fällt in die Periode ab 15.08.2025');
    }

    public function test_voucher_is_spread_pro_rata_over_the_month_and_domain_entries_sync(): void {
        $admin = $this->orgAdmin();
        $a = $this->subscription('ent-1', 'Microsoft 365 Business Premium', 'A', '2026-01-01', 1, 'telekom_marketplace');
        $b = $this->subscription('ent-2', 'Microsoft 365 Business Premium', 'B', '2026-03-01', 3, 'telekom_marketplace');
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Telekom Deutschland GmbH']);
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'pv-1', 'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => '726 039 1495', 'voucher_date' => '2026-03-30', 'total_amount' => 2043.55, 'currency' => 'EUR', 'archived' => false,
        ]);

        $this->actingAs($admin)->get(route('finance.resale.purchases.create'))->assertOk()->assertSee('726 039 1495');
        $response = $this->actingAs($admin)->post(route('finance.resale.purchases.store'), [
            'voucher_id' => Sqid::encode(LexofficeVoucher::class, $voucher->id), 'provider' => 'telekom_marketplace', 'net_amount' => '400.00', 'month' => '2026-03',
        ]);
        $this->assertSame([], session('errors')?->all() ?? [], json_encode(session()->all()) ?: '');
        $this->assertNull(session('error'), (string) session('error'));
        $response->assertRedirect(route('finance.resale.purchases.index'))->assertSessionHas('success');

        $entries = ResalePurchaseEntry::query()->orderBy('subscription_id')->get();
        $this->assertCount(2, $entries);
        // Gewichte: A 1 × 187,92/12, B 3 × 187,92/12 → 1:3 → 100 / 300.
        $this->assertSame('100.00', $entries[0]->net_amount->getAmount());
        $this->assertSame('300.00', $entries[1]->net_amount->getAmount());
        $this->assertSame($a->id, $entries[0]->subscription_id);
        $this->assertSame($b->id, $entries[1]->subscription_id);

        $this->actingAs($admin)->get(route('finance.resale.purchases.index'))->assertOk()->assertSee('726 039 1495')->assertSee('300,00');
        $this->actingAs($admin)->delete(route('finance.resale.purchases.destroy', $entries[0]->sqid))->assertRedirect();
        $this->assertSame(0, ResalePurchaseEntry::query()->count(), 'Zuteilung des Belegs als Ganzes gelöst');

        // Domain-Buchungen
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $projection = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'klimpel.de', 'domain_hash' => DomainProjection::hashFor('klimpel.de'),
            'customer_id' => $customer->id, 'registration_at' => '2024-03-15', 'expiration_at' => '2027-03-15', 'renewal_price' => '8.87',
        ]);
        app(DomainSubscriptionSync::class)->sync($this->organization);
        DomainAccountingEntry::query()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'reseller', 'accounting_id' => 'acc-1', 'domain_projection_id' => $projection->id,
            'entry_date' => '2026-03-16', 'type' => 'RENEWAL', 'description' => 'klimpel.de', 'reference' => 'INV-1', 'quantity' => 1, 'net_amount' => 8.87, 'currency' => 'EUR', 'raw_hash' => 'h1',
        ]);
        $this->artisan('resale:sync-purchases', ['--org' => $this->organization->id])->expectsOutputToContain('1 Einkaufsbelege neu')->assertSuccessful();
        $domainEntry = ResalePurchaseEntry::query()->where('source', ResalePurchaseEntry::SOURCE_DOMAIN)->firstOrFail();
        $this->assertSame('8.87', $domainEntry->net_amount->getAmount());
        $this->assertSame('2026-03-15', $domainEntry->period?->starts_on->toDateString());
        $this->artisan('resale:sync-purchases', ['--org' => $this->organization->id])->assertSuccessful();
        $this->assertSame(1, ResalePurchaseEntry::query()->where('source', ResalePurchaseEntry::SOURCE_DOMAIN)->count(), 'idempotent');
    }
}
