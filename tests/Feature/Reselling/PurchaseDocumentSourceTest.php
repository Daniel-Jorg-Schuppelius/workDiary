<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseDocumentSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Expense\ExpenseStatus;
use App\Enums\Reselling\SubscriptionProvider;
use App\Models\{Document, Expense, IncomingEInvoice, LexofficeVoucher, Organization, Supplier};
use App\Models\Reselling\{ResalePurchaseEntry, ResaleSubscription};
use App\Services\Reselling\Purchase\{PurchaseDocument, PurchaseDocumentSource, PurchaseDocuments};
use App\Services\Reselling\Register\{PeriodPlanner, PurchaseAllocator};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Eingangsbelege anbieterneutral (Feature 152, Review 2026-09-11, Einkauf):
 * Registry aus lokalen Quellen (Ausgaben, Eingangs-E-Rechnungen) und der
 * Lexoffice-Quelle des Plugins; pro-rata-Zuteilung mit Morph-Bezug, Altzeilen
 * über `lexoffice_voucher_id`, Dialog und Speichern mit Quellschlüssel.
 */
class PurchaseDocumentSourceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-11');
    }

    private function subscription(string $contract, string $company, string $startsOn, int $quantity = 1): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'external_id' => $contract, 'label' => 'Microsoft 365 Business Premium', 'company_name' => $company,
            'is_own_holding' => true, 'quantity' => $quantity, 'starts_on' => $startsOn, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function expense(string $vendor, string $date, string $net, ExpenseStatus $status, ?string $reference = null, ?Organization $organization = null): Expense {
        return Expense::factory()->create([
            'organization_id' => ($organization ?? $this->organization)->id, 'vendor' => $vendor, 'date' => $date, 'description' => 'Sammelrechnung Marketplace',
            'currency' => 'EUR', 'amount_net' => $net, 'tax_rate' => '19', 'status' => $status->value, 'reimbursement_reference' => $reference,
        ]);
    }

    private function einvoice(string $number, string $date, string $net, string $status): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', $number), 'source' => 'upload', 'received_at' => now(),
            'status' => $status, 'invoice_number' => $number, 'seller_name' => 'Lieferant GmbH', 'issue_date' => $date, 'currency' => 'EUR',
            'amount_net' => $net, 'amount_tax' => '0.00', 'amount_gross' => $net,
        ]);
    }

    private function purchaseVoucher(string $externalId, string $number, string $date, string $supplierName = 'Lieferant AG'): LexofficeVoucher {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => $supplierName]);

        return LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'supplier_id' => $supplier->id, 'voucher_type' => 'purchaseinvoice', 'voucher_status' => 'paid',
            'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 400, 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    /** @param  class-string  $modelClass */
    private function documentFor(string $modelClass, int $id): PurchaseDocument {
        $document = app(PurchaseDocuments::class)->byKey($this->organization, Sqid::encode($modelClass, $id));
        $this->assertNotNull($document, 'Quelle löst den Schlüssel auf: ' . $modelClass);

        return $document;
    }

    public function test_registry_merges_local_sources_and_lexoffice_newest_first_and_resolves_keys(): void {
        $registry = app(PurchaseDocuments::class);
        $this->assertSame(['expense', 'incoming_einvoice', 'lexoffice'], array_map(static fn(PurchaseDocumentSource $s): string => $s->key(), $registry->sources()), 'Kern registriert Ausgaben und E-Rechnungen, das Plugin Lexoffice');
        $this->assertSame((new Expense)->getMorphClass(), $registry->source('expense')?->morphClass());
        $this->assertSame('lexoffice', $registry->sourceFor((new LexofficeVoucher)->getMorphClass())?->key());

        $expense = $this->expense('Telekom Deutschland GmbH', '2026-03-30', '400.00', ExpenseStatus::Approved, 'AUS-77');
        $this->expense('Storniert GmbH', '2026-03-31', '10.00', ExpenseStatus::Cancelled);
        $this->expense('Entwurf GmbH', '2026-03-31', '10.00', ExpenseStatus::Draft);
        $einvoice = $this->einvoice('ER-2026-5', '2026-04-02', '250.00', IncomingEInvoice::STATUS_APPROVED);
        $received = $this->einvoice('ER-2026-6', '2026-04-03', '99.00', IncomingEInvoice::STATUS_RECEIVED);
        $voucher = $this->purchaseVoucher('pv-1', '726 039 1495', '2026-02-15');

        $documents = $registry->search($this->organization, null, CarbonImmutable::parse('2025-01-01'), 50);
        $this->assertSame(['incoming_einvoice', 'expense', 'lexoffice'], $documents->map(static fn(PurchaseDocument $d): string => $d->sourceKey)->all(), 'zusammengeführt, neueste zuerst; Entwurf, Storno und bloßer Empfang fehlen');
        $this->assertSame(['ER-2026-5', 'AUS-77', '726 039 1495'], $documents->map(static fn(PurchaseDocument $d): ?string => $d->number)->all());
        $this->assertSame(['250.00', '400.00', '400.00'], $documents->map(static fn(PurchaseDocument $d): string => $d->net->getAmount())->all(), 'Lexoffice ohne Detailabruf: Belegsumme');
        $this->assertSame(['Lieferant GmbH', 'Telekom Deutschland GmbH', 'Lieferant AG'], $documents->map(static fn(PurchaseDocument $d): ?string => $d->vendorName)->all());
        $this->assertSame(['AUS-77'], $registry->search($this->organization, 'telekom', null, 50)->map(static fn(PurchaseDocument $d): ?string => $d->number)->all(), 'Suche über den Lieferanten');
        $this->assertSame(['726 039 1495'], $registry->search($this->organization, 'Lieferant AG', null, 50)->map(static fn(PurchaseDocument $d): ?string => $d->number)->all(), 'Suche über den Lexoffice-Lieferanten');
        $this->assertCount(1, $registry->search($this->organization, null, CarbonImmutable::parse('2026-04-01'), 50), 'Fenster ab Belegdatum');
        $this->assertCount(2, $registry->search($this->organization, null, null, 2), 'Limit gilt über alle Quellen');

        // Schlüssel je Quelle: die Quelle, deren Sqid-Alphabet ihn dekodiert, liefert den Beleg.
        $this->assertSame($expense->id, $registry->byKey($this->organization, Sqid::encode(Expense::class, $expense->id))?->morphId);
        $this->assertSame('incoming_einvoice', $registry->byKey($this->organization, Sqid::encode(IncomingEInvoice::class, $einvoice->id))?->sourceKey);
        $this->assertSame('lexoffice', $registry->byKey($this->organization, Sqid::encode(LexofficeVoucher::class, $voucher->id))?->sourceKey, 'bestehende Lexoffice-Sqids bleiben gültig');
        $this->assertNull($registry->byKey($this->organization, 'kein-schluessel'));
        $this->assertNull($registry->byKey($this->organization, Sqid::encode(IncomingEInvoice::class, $received->id)), 'nur empfangene E-Rechnung ist nicht zuteilbar');
        $this->assertNull($registry->byKey($this->organization, (string) $expense->id), 'rohe IDs sind keine Schlüssel');

        // Fremde Organisation sieht nichts.
        $other = Organization::factory()->create();
        $this->assertNull($registry->byKey($other, Sqid::encode(Expense::class, $expense->id)));
        $this->assertCount(0, $registry->search($other, null, null, 50));
    }

    public function test_local_expense_is_spread_pro_rata_with_morph_reference_and_reallocation_replaces(): void {
        $a = $this->subscription('ent-1', 'A', '2026-01-01', 1);
        $b = $this->subscription('ent-2', 'B', '2026-04-01', 3);
        $expense = $this->expense('Telekom Deutschland GmbH', '2026-03-30', '400.00', ExpenseStatus::Approved, 'AUS-77');
        $allocator = app(PurchaseAllocator::class);
        $document = $this->documentFor(Expense::class, $expense->id);
        $net = Money::of('400.00', CurrencyCode::Euro);

        // März: nur A läuft → alles an A, Bezug als Morph auf die Ausgabe.
        $march = $allocator->allocateVoucher($this->organization, $document, SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(['entries' => 1, 'allocated' => 400.0, 'unallocated' => 0.0], $march);
        $entry = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame($a->id, $entry->subscription_id);
        $this->assertSame((new Expense)->getMorphClass(), $entry->document_type);
        $this->assertSame($expense->id, $entry->document_id);
        $this->assertNull($entry->lexoffice_voucher_id, 'keine Altspalte für lokale Quellen');
        $this->assertSame('AUS-77', $entry->document_number);
        $this->assertSame('2026-03-30', $entry->entry_date->toDateString(), 'Belegdatum der Ausgabe');
        $this->assertSame((string) CryptoHelper::hash('expense|' . $expense->id . '|telekom_marketplace|2026-03|' . $entry->period_id), $entry->raw_hash, 'Hash-Basis quelle|id für Nicht-Lexoffice-Belege');
        $this->assertInstanceOf(Expense::class, $entry->document, 'MorphTo lädt die Ausgabe');
        $this->assertSame($document->identity(), app(PurchaseDocuments::class)->forEntry($entry)?->identity());

        // Dublette: dieselbe Zuteilung nochmal ersetzt, ergänzt nicht.
        $again = $allocator->allocateVoucher($this->organization, $document, SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(1, $again['entries']);
        $this->assertSame(1, ResalePurchaseEntry::query()->count(), 'ersetzt, nicht verdoppelt');

        // Umbuchung auf April: A und B (1 : 3), die März-Zeile ist weg.
        $april = $allocator->allocateVoucher($this->organization, $document, SubscriptionProvider::TelekomMarketplace, $net, CarbonImmutable::parse('2026-04-15'));
        $this->assertSame(['entries' => 2, 'allocated' => 400.0, 'unallocated' => 0.0], $april);
        $this->assertNull($entry->fresh(), 'alte Zuteilung gelöscht');
        $entries = ResalePurchaseEntry::query()->orderBy('subscription_id')->get();
        $this->assertSame([$a->id, $b->id], $entries->pluck('subscription_id')->all());
        $this->assertSame(['100.00', '300.00'], $entries->map(static fn(ResalePurchaseEntry $e): string => $e->net_amount->getAmount())->all());
        $this->assertSame([$expense->id, $expense->id], $entries->pluck('document_id')->all());
    }

    public function test_incoming_einvoice_is_a_source_and_allocates_with_its_morph(): void {
        $this->subscription('ent-1', 'A', '2026-01-01', 1);
        $einvoice = $this->einvoice('ER-2026-5', '2026-05-04', '250.00', IncomingEInvoice::STATUS_PAYMENT_RELEASED);
        $document = $this->documentFor(IncomingEInvoice::class, $einvoice->id);
        $this->assertSame('incoming_einvoice', $document->sourceKey);
        $this->assertSame('Lieferant GmbH', $document->vendorName);
        $this->assertSame('250.00', $document->net->getAmount());
        $this->assertSame('2026-05-04', $document->date?->toDateString());
        $this->assertSame(CurrencyCode::Euro, $document->currency);

        $result = app(PurchaseAllocator::class)->allocateVoucher($this->organization, $document, SubscriptionProvider::TelekomMarketplace, Money::of('250.00', CurrencyCode::Euro), CarbonImmutable::parse('2026-05-01'));
        $this->assertSame(1, $result['entries']);
        $entry = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame((new IncomingEInvoice)->getMorphClass(), $entry->document_type);
        $this->assertSame($einvoice->id, $entry->document_id);
        $this->assertNull($entry->lexoffice_voucher_id);
        $this->assertSame('ER-2026-5', $entry->document_number);
        $this->assertSame('2026-05-04', $entry->entry_date->toDateString());
        $this->assertInstanceOf(IncomingEInvoice::class, $entry->document);
    }

    public function test_legacy_entry_with_only_lexoffice_voucher_id_still_resolves_its_document(): void {
        $a = $this->subscription('ent-1', 'A', '2026-01-01', 1);
        $voucher = $this->purchaseVoucher('pv-9', '726 039 0009', '2026-03-30');
        $period = $a->periods()->firstOrFail();
        $entry = ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $a->id, 'period_id' => $period->id, 'provider' => SubscriptionProvider::TelekomMarketplace,
            'source' => ResalePurchaseEntry::SOURCE_VOUCHER, 'lexoffice_voucher_id' => $voucher->id, 'document_number' => '726 039 0009', 'entry_date' => '2026-03-30',
            'description' => 'Zuteilung vor der Spiegel-Abstraktion', 'net_amount' => '400.00', 'currency' => 'EUR',
            'raw_hash' => (string) CryptoHelper::hash($voucher->id . '|telekom_marketplace|2026-03|' . $period->id),
        ]);
        $this->assertNull($entry->document_type);
        $this->assertSame([(new LexofficeVoucher)->getMorphClass(), $voucher->id], $entry->documentReference(), 'Altspalte liefert den Bezug');

        $registry = app(PurchaseDocuments::class);
        $document = $registry->forEntry($entry);
        $this->assertSame('lexoffice', $document?->sourceKey);
        $this->assertSame($voucher->id, $document?->morphId);
        $registry->preload($this->organization, [$entry]);
        $this->assertSame('726 039 0009', $entry->purchaseDocument()?->number);
        $this->assertSame('726 039 0009', $entry->documentLabel());

        // Neue Zuteilung desselben Belegs ersetzt die Altzeile (gleiche Hash-Basis) statt sie zu verdoppeln.
        $result = app(PurchaseAllocator::class)->allocateVoucher($this->organization, $this->documentFor(LexofficeVoucher::class, $voucher->id), SubscriptionProvider::TelekomMarketplace, Money::of('400.00', CurrencyCode::Euro), CarbonImmutable::parse('2026-03-01'));
        $this->assertSame(1, $result['entries']);
        $this->assertSame(1, ResalePurchaseEntry::query()->count(), 'Altzeile ersetzt, nicht verdoppelt');
        $fresh = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame($entry->raw_hash, $fresh->raw_hash, 'Hash-Basis der Lexoffice-Belege unverändert');
        $this->assertSame((new LexofficeVoucher)->getMorphClass(), $fresh->document_type);
        $this->assertSame($voucher->id, $fresh->document_id);
        $this->assertSame($voucher->id, $fresh->lexoffice_voucher_id, 'Altspalte bleibt für Lexoffice-Belege gefüllt');
    }

    public function test_dialog_lists_all_sources_and_store_accepts_source_keys_and_rejects_unknown_ones(): void {
        $admin = $this->orgAdmin();
        $this->subscription('ent-1', 'A', '2026-01-01', 1);
        $expense = $this->expense('Telekom Deutschland GmbH', '2026-03-30', '400.00', ExpenseStatus::Approved, 'AUS-77');
        $this->einvoice('ER-2026-5', '2026-04-02', '250.00', IncomingEInvoice::STATUS_APPROVED);
        $this->purchaseVoucher('pv-1', '726 039 1495', '2026-02-15');

        $this->actingAs($admin)->get(route('finance.resale.purchases.create'))->assertOk()
            ->assertSee('AUS-77')->assertSee('ER-2026-5')->assertSee('726 039 1495')
            ->assertSee(Sqid::encode(Expense::class, $expense->id))
            ->assertSee((string) __('resale.purchase_document.source.expense'))
            ->assertSee((string) __('resale.purchase_document.source.incoming_einvoice'))
            ->assertSee((string) __('resale.purchase_document.source.lexoffice'));
        $this->actingAs($admin)->get(route('finance.resale.purchases.create', ['q' => 'ER-2026']))->assertOk()->assertSee('ER-2026-5')->assertDontSee('AUS-77')->assertDontSee('726 039 1495');

        $response = $this->actingAs($admin)->post(route('finance.resale.purchases.store'), [
            'document' => Sqid::encode(Expense::class, $expense->id), 'provider' => 'telekom_marketplace', 'net_amount' => '400.00', 'month' => '2026-03',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect(route('finance.resale.purchases.index'))->assertSessionHas('success');
        $entry = ResalePurchaseEntry::query()->firstOrFail();
        $this->assertSame((new Expense)->getMorphClass(), $entry->document_type);
        $this->assertSame($expense->id, $entry->document_id);
        $this->assertSame('400.00', $entry->net_amount->getAmount());

        // Einkaufsliste zeigt Kennung und Quelle des Belegs.
        $this->actingAs($admin)->get(route('finance.resale.purchases.index'))->assertOk()->assertSee('AUS-77')->assertSee((string) __('resale.purchase_document.source.expense'));

        // Unbekannter Schlüssel und fehlender Beleg → 422 am Feld `document`, nichts gespeichert.
        $this->actingAs($admin)->postJson(route('finance.resale.purchases.store'), ['document' => 'kein-schluessel', 'provider' => 'telekom_marketplace', 'net_amount' => '1.00', 'month' => '2026-03'])
            ->assertStatus(422)->assertJsonValidationErrors(['document']);
        $this->actingAs($admin)->postJson(route('finance.resale.purchases.store'), ['provider' => 'telekom_marketplace', 'net_amount' => '1.00', 'month' => '2026-03'])
            ->assertStatus(422)->assertJsonValidationErrors(['document']);
        $this->assertSame(1, ResalePurchaseEntry::query()->count());

        // Zuteilung lösen: als Ganzes über den Belegbezug.
        $this->actingAs($admin)->delete(route('finance.resale.purchases.destroy', $entry->sqid))->assertRedirect();
        $this->assertSame(0, ResalePurchaseEntry::query()->count());
    }
}
