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
use App\Services\Reselling\Marketplace\{ProviderInvoice, ProviderInvoiceLine, QualityHostingInvoiceReader};
use App\Services\Reselling\Purchase\{PurchaseDocument, PurchaseDocuments};
use App\Services\Reselling\Register\{DomainSubscriptionSync, PeriodPlanner, PurchaseAllocator};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einkaufsbelege (Feature 152, MVP-762): Quality-Hosting-Rechnung
 * positionsgenau, Eingangsbeleg pro rata (Lexoffice-Quelle über die
 * Belegregistry, Review 2026-09-11), Domain-Buchungen, Oberfläche.
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

    public function test_provider_invoice_reports_total_mismatch_takes_the_currency_and_finds_credits_across_providers(): void {
        // Review 2026-09-10 (B17-Anschluss, Währung, Lauf-Cache): Summenabweichung als Hinweis, Import läuft weiter.
        $klimpel = $this->subscription('CNLCON00156', 'Microsoft 365 Business Premium', 'Klimpel Bäder GmbH', '2025-09-03');
        $telekomOnly = $this->subscription('ent-77', 'Exchange Online Plan 1', 'Ambulanter Pflegedienst Regina Zukanovic GmbH', '2025-05-06', 1, 'telekom_marketplace');
        $allocator = app(PurchaseAllocator::class);
        $invoice = new ProviderInvoice('31970999', CarbonImmutable::parse('2026-09-03'), false, '95229', [
            new ProviderInvoiceLine(1, 1.0, 'Microsoft 365 Business Premium', 187.92, 187.92, 'CNLCON00156', 'CNL00007', 'Klimpel Bäder GmbH', CarbonImmutable::parse('2026-09-03'), CarbonImmutable::parse('2027-09-02')),
        ], 336.05, ['Seite 2 nicht lesbar']);

        $result = $allocator->importProviderInvoice($this->organization, $invoice, SubscriptionProvider::QualityHosting, null, '31970999.pdf', CurrencyCode::SwissFranc);
        $this->assertSame(1, $result['matched']);
        $this->assertCount(2, $result['issues'], 'Reader-Befund + Summenabweichung 187,92 ≠ 336,05');
        $entry = ResalePurchaseEntry::query()->where('subscription_id', $klimpel->id)->firstOrFail();
        $this->assertSame(CurrencyCode::SwissFranc, $entry->currency, 'Währung aus dem Parameter, nicht hart EUR');

        // Gutschrift für eine Firma ohne QH-Abo: jüngstes Abo der Firma egal welchen Anbieters (Lauf-Cache, kein Volltabellen-Read je Zeile).
        $credit = new ProviderInvoice('7006999', CarbonImmutable::parse('2026-06-02'), true, '95229', [
            new ProviderInvoiceLine(1, 1.0, 'Umzugsbonus', -100.0, -100.0, null, 'CNL00002', 'Ambulanter Pflegedienst Regina Zukanovic GmbH'),
        ], -100.0);
        $creditResult = $allocator->importProviderInvoice($this->organization, $credit, SubscriptionProvider::QualityHosting);
        $this->assertSame(1, $creditResult['matched']);
        $this->assertSame([], $creditResult['issues'], 'konsistent: keine Hinweise');
        $this->assertSame(1, ResalePurchaseEntry::query()->where('subscription_id', $telekomOnly->id)->count());

        // Neues Abo nach dem Cache-Aufbau: erst nach reset() sichtbar.
        $late = $this->subscription('CNLCON00999', 'Exchange Online Plan 1', 'Neue Firma GmbH', '2026-01-01');
        $lateInvoice = new ProviderInvoice('31971000', CarbonImmutable::parse('2026-09-03'), false, '95229', [
            new ProviderInvoiceLine(1, 1.0, 'Exchange Online Plan 1', 34.32, 34.32, 'CNLCON00999', 'CNL00099', 'Neue Firma GmbH'),
        ], 34.32);
        $this->assertSame(0, $allocator->importProviderInvoice($this->organization, $lateInvoice, SubscriptionProvider::QualityHosting)['matched']);
        $allocator->reset();
        $this->assertSame(1, $allocator->importProviderInvoice($this->organization, $lateInvoice, SubscriptionProvider::QualityHosting)['matched']);
        $this->assertSame(1, ResalePurchaseEntry::query()->where('subscription_id', $late->id)->count());
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
            'document' => Sqid::encode(LexofficeVoucher::class, $voucher->id), 'provider' => 'telekom_marketplace', 'net_amount' => '400.00', 'month' => '2026-03',
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
        $this->assertSame((new LexofficeVoucher)->getMorphClass(), $entries[0]->document_type, 'Belegbezug als Morph');
        $this->assertSame($voucher->id, $entries[0]->document_id);
        $this->assertSame((string) \CommonToolkit\Helper\Data\CryptoHelper::hash($voucher->id . '|telekom_marketplace|2026-03|' . $entries[0]->period_id), $entries[0]->raw_hash, 'Hash-Basis der Lexoffice-Belege unverändert (Altbestand bleibt Dublette)');

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

    private function purchaseVoucher(string $externalId, string $number, string $date): LexofficeVoucher {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Telekom Deutschland GmbH']);

        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 400, 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    /** Lexoffice-Beleg als anbieterneutraler Eingangsbeleg — über den Quellschlüssel (Sqid), wie das Formular ihn schickt. */
    private function documentFor(LexofficeVoucher $voucher): PurchaseDocument {
        $document = app(PurchaseDocuments::class)->byKey($this->organization, Sqid::encode(LexofficeVoucher::class, $voucher->id));
        $this->assertNotNull($document, 'Lexoffice-Quelle löst die Beleg-Sqid auf');

        return $document;
    }

    public function test_pro_rata_without_weights_leaves_everything_unallocated_and_never_divides_by_zero(): void {
        // Review 2026-09-10 (G): Gewichtssumme 0 — keine Periode im Monat, oder Perioden ohne Soll-Einkauf
        // (kein Einkaufspreis): nichts wird verteilt, der Betrag bleibt komplett „nicht zugeteilt".
        $allocator = app(PurchaseAllocator::class);
        $voucher = $this->purchaseVoucher('pv-0', '726 039 0001', '2026-03-30');
        $net = \CommonToolkit\ValueObjects\Money::of('400.00', CurrencyCode::Euro);

        // Keine einzige Periode beim Anbieter.
        $result = $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(['entries' => 0, 'allocated' => 0.0, 'unallocated' => 400.0], $result);
        $this->assertSame(0, ResalePurchaseEntry::query()->count());

        // Periode vorhanden, aber ohne Soll-Einkauf (kein Einkaufspreis am Abo) → Gewicht 0.
        $free = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => 'ent-free', 'label' => 'Microsoft 365 Business Premium',
            'is_own_holding' => true, 'quantity' => 1, 'starts_on' => '2026-01-01', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($free);
        $this->assertNull($free->periods()->first()?->expected_purchase);
        $result = $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(['entries' => 0, 'allocated' => 0.0, 'unallocated' => 400.0], $result, 'kein Division-durch-null, nichts verteilt');
        $this->assertSame(0, ResalePurchaseEntry::query()->count());

        // Anderer Anbieter im selben Monat zählt nicht (Perioden des Quality-Hosting-Abos sind keine Telekom-Gewichte).
        $this->subscription('CNLCON00156', 'Microsoft 365 Business Premium', 'Klimpel Bäder GmbH', '2026-01-01');
        $result = $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(0, $result['entries']);
        $this->assertSame(400.0, $result['unallocated']);
    }

    public function test_reallocating_a_voucher_to_another_month_replaces_the_entries(): void {
        // Review 2026-09-10 (G): ein Beleg = eine Zuteilung — der Monat gehört zum Hash, die alte pro-rata-Verteilung
        // desselben Belegs (gleiche Quelle) wird ersetzt, nicht ergänzt.
        $allocator = app(PurchaseAllocator::class);
        $a = $this->subscription('ent-1', 'Microsoft 365 Business Premium', 'A', '2026-01-01', 1, 'telekom_marketplace');
        $b = $this->subscription('ent-2', 'Microsoft 365 Business Premium', 'B', '2026-04-01', 3, 'telekom_marketplace');
        $voucher = $this->purchaseVoucher('pv-2', '726 039 1495', '2026-03-30');
        $net = \CommonToolkit\ValueObjects\Money::of('400.00', CurrencyCode::Euro);

        // März: nur A läuft (B beginnt im April) → alles an A.
        $march = $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(['entries' => 1, 'allocated' => 400.0, 'unallocated' => 0.0], $march);
        $marchEntry = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame($a->id, $marchEntry->subscription_id);
        $this->assertSame((string) __('resale.purchase.pro_rata', ['month' => '03/2026']), $marchEntry->description);
        $this->assertSame('2026-03-30', $marchEntry->entry_date->toDateString(), 'Belegdatum, nicht Monatsanfang');

        // Umbuchung auf April: A und B (1 : 3) — der März-Eintrag ist weg, die Hashes sind neu.
        $april = $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-04-15'));
        $this->assertSame(['entries' => 2, 'allocated' => 400.0, 'unallocated' => 0.0], $april);
        $entries = ResalePurchaseEntry::query()->orderBy('subscription_id')->get();
        $this->assertCount(2, $entries, 'ersetzt, nicht ergänzt');
        $this->assertNull($marchEntry->fresh(), 'alte Zuteilung gelöscht');
        $this->assertSame([$a->id, $b->id], $entries->pluck('subscription_id')->all());
        $this->assertSame(['100.00', '300.00'], $entries->map(static fn(ResalePurchaseEntry $e): string => $e->net_amount->getAmount())->all());
        $this->assertSame([(string) __('resale.purchase.pro_rata', ['month' => '04/2026'])], $entries->pluck('description')->unique()->values()->all());
        $this->assertNotSame($marchEntry->raw_hash, $entries[0]->raw_hash, 'Monat gehört zum Hash');
        $this->assertSame((new LexofficeVoucher)->getMorphClass(), $entries[0]->document_type);

        // Manuelle Einträge zum selben Beleg (andere Quelle) überleben die Umbuchung.
        $manual = ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $a->id, 'period_id' => $a->periods()->firstOrFail()->id, 'provider' => SubscriptionProvider::TelekomMarketplace,
            'source' => ResalePurchaseEntry::SOURCE_MANUAL, 'document_type' => (new LexofficeVoucher)->getMorphClass(), 'document_id' => $voucher->id, 'document_number' => '726 039 1495', 'entry_date' => '2026-04-01',
            'description' => 'Nachtrag von Hand', 'net_amount' => '12.34', 'currency' => 'EUR', 'raw_hash' => 'manual-1',
        ]);
        $allocator->allocateVoucher($this->organization, $this->documentFor($voucher), SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-04-15'));
        $this->assertNotNull($manual->fresh());
        $this->assertSame(3, ResalePurchaseEntry::query()->count());
    }

    public function test_domain_accounting_entry_without_matching_period_is_still_imported_without_period(): void {
        // Review 2026-09-10 (G): Buchung vor der Registrierung bzw. jenseits der geplanten Perioden — der Einkauf
        // gehört zum Abo, nur nicht zu einer Periode (period_id null), er wird nicht übersprungen.
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $projection = DomainProjection::factory()->create([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => 'klimpel.de', 'domain_hash' => DomainProjection::hashFor('klimpel.de'),
            'customer_id' => $customer->id, 'registration_at' => '2024-03-15', 'expiration_at' => '2027-03-15', 'renewal_price' => '8.87',
        ]);
        app(DomainSubscriptionSync::class)->sync($this->organization);
        $subscription = ResaleSubscription::query()->where('external_id', 'klimpel.de')->firstOrFail();
        $this->assertSame(['2024-03-15', '2025-03-15', '2026-03-15'], $subscription->periods()->get()->map(static fn($p) => $p->starts_on->toDateString())->all());
        $entry = static fn(string $id, string $date, string $hash): DomainAccountingEntry => DomainAccountingEntry::query()->create([
            'organization_id' => $connection->organization_id, 'connection_id' => $connection->id, 'external_user' => 'reseller', 'accounting_id' => $id, 'domain_projection_id' => $projection->id,
            'entry_date' => $date, 'type' => 'CREATE', 'description' => 'klimpel.de', 'reference' => 'INV-' . $id, 'quantity' => 1, 'net_amount' => 4.50, 'currency' => 'EUR', 'raw_hash' => $hash,
        ]);
        $entry('acc-before', '2023-12-01', 'h-before'); // vor der Registrierung
        $entry('acc-after', '2028-06-01', 'h-after'); // nach dem Planungshorizont
        $entry('acc-inside', '2025-06-01', 'h-inside');

        $result = app(PurchaseAllocator::class)->syncDomainAccounting($this->organization);
        $this->assertSame(['entries' => 3, 'skipped' => 0], $result);
        $rows = ResalePurchaseEntry::query()->where('source', ResalePurchaseEntry::SOURCE_DOMAIN)->orderBy('entry_date')->get();
        $this->assertSame([$subscription->id, $subscription->id, $subscription->id], $rows->pluck('subscription_id')->all());
        $this->assertNull($rows[0]->period_id, 'vor der ersten Periode');
        $this->assertSame('2025-03-15', $rows[1]->period?->starts_on->toDateString());
        $this->assertNull($rows[2]->period_id, 'jenseits der geplanten Perioden');
        $this->assertSame('4.50', $rows[0]->net_amount->getAmount());
        $this->assertSame('CREATE klimpel.de', $rows[0]->description);

        // Idempotent: derselbe Lauf importiert nichts doppelt — auch die periodenlosen nicht.
        $this->assertSame(['entries' => 0, 'skipped' => 3], app(PurchaseAllocator::class)->syncDomainAccounting($this->organization));
        $this->assertSame(3, ResalePurchaseEntry::query()->count());
        $this->assertSame(4.5, $subscription->periods()->get()[1]->load('purchases')->actualPurchase(), 'nur der Eintrag in der Periode zählt zum Ist-Einkauf');
        $this->assertNull($subscription->periods()->get()[0]->load('purchases')->actualPurchase());
    }

    public function test_legacy_credit_note_hash_with_position_zero_still_counts_as_duplicate(): void {
        // Review 2026-09-10 (B18): vor dem Review trugen Gutschrift-Positionen alle die Nummer 0 im Hash. Ein so
        // gespeicherter Eintrag mit demselben Betrag bleibt beim Re-Import eine Dublette — sonst käme die Zeile doppelt.
        $klimpel = $this->subscription('CNLCON00156', 'Microsoft 365 Business Premium', 'Klimpel Bäder GmbH', '2025-09-03');
        $schub = $this->subscription('CNLCON00109', 'Exchange Online Plan 1', 'Schub- und Schleppreederei U. Golka GmbH & Co.KG', '2025-08-15', 2);
        $credit = (new QualityHostingInvoiceReader)->parse(self::CREDIT_TEXT);
        $this->assertSame([1, 2], array_map(static fn(ProviderInvoiceLine $l): int => $l->position, $credit->lines), 'Reader vergibt heute laufende Nummern („0" im Text)');
        $legacy = static fn(string $companyKey): string => (string) \CommonToolkit\Helper\Data\CryptoHelper::hash('provider|qualityhosting|7006872|0|' . $companyKey);

        // Alt-Eintrag für Klimpel mit Legacy-Hash und gleichem Betrag; für Schub mit Legacy-Hash, aber anderem Betrag.
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $klimpel->id, 'period_id' => $klimpel->periods()->firstOrFail()->id, 'provider' => SubscriptionProvider::QualityHosting,
            'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, 'document_number' => '7006872', 'entry_date' => '2026-06-02', 'description' => 'Umzugsbonus',
            'net_amount' => '-100.00', 'currency' => 'EUR', 'raw_hash' => $legacy('CNL00007'),
        ]);
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $schub->id, 'period_id' => $schub->periods()->firstOrFail()->id, 'provider' => SubscriptionProvider::QualityHosting,
            'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, 'document_number' => '7006872', 'entry_date' => '2026-06-02', 'description' => 'Umzugsbonus (alt, anderer Betrag)',
            'net_amount' => '-50.00', 'currency' => 'EUR', 'raw_hash' => $legacy('CNL00010'),
        ]);

        $result = app(PurchaseAllocator::class)->importProviderInvoice($this->organization, $credit, SubscriptionProvider::QualityHosting);
        $this->assertSame(1, $result['duplicates'], 'Klimpel: Legacy-Hash + gleicher Betrag = Dublette');
        $this->assertSame(1, $result['matched'], 'Schub: Legacy-Hash, aber anderer Betrag → neue Zeile');
        $this->assertSame(-100.0, $result['net']);
        $this->assertSame(1, ResalePurchaseEntry::query()->where('subscription_id', $klimpel->id)->count());
        $this->assertSame(2, ResalePurchaseEntry::query()->where('subscription_id', $schub->id)->count());

        // Der neue Eintrag trägt den aktuellen Hash (Position statt 0); ein weiterer Import erkennt beide.
        $again = app(PurchaseAllocator::class)->importProviderInvoice($this->organization, $credit, SubscriptionProvider::QualityHosting);
        $this->assertSame(2, $again['duplicates']);
        $this->assertSame(0, $again['matched']);
        $this->assertSame(3, ResalePurchaseEntry::query()->count());
    }
}
