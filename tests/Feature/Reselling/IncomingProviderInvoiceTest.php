<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingProviderInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Events\Invoicing\IncomingInvoiceAssigned;
use App\Jobs\Reselling\ImportIncomingProviderInvoiceJob;
use App\Models\Customer\Customer;
use App\Models\Document\Document;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Reselling\{ResaleProviderSupplier, ResalePurchaseEntry, ResaleSubscription};
use App\Models\Supplier\Supplier;
use App\Services\Reselling\Marketplace\{ProviderInvoice, ProviderInvoiceLine};
use App\Services\Reselling\Register\{IncomingProviderInvoiceImport, PeriodPlanner, ProviderSuppliers};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Queue};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einkauf aus dem Rechnungseingang (Feature 185, MVP-1419–1421): Lieferant →
 * Anbieter, Übernahme nach der Zuordnung (positionsgenau, pro rata), Einkauf
 * ohne Abo als Prüffall, Rückwirkung per Befehl, Marge im Lizenzkonto.
 */
class IncomingProviderInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Supplier $supplier;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Qualityhosting AG']);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'external_id' => 'CNLCON00156', 'company_name' => 'Klimpel Bäder GmbH', 'quantity' => 1, 'starts_on' => '2025-09-03', 'term_months' => 12, 'interval' => 'yearly',
            'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function incoming(string $number, string $date, string $net, ?Supplier $supplier = null): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', $number), 'source' => 'upload', 'received_at' => now(),
            'status' => 'received', 'invoice_number' => $number, 'seller_name' => 'Qualityhosting AG', 'issue_date' => $date, 'currency' => 'EUR',
            'amount_net' => $net, 'amount_tax' => '0.00', 'amount_gross' => $net, 'direction' => 'incoming', 'supplier_id' => ($supplier ?? $this->supplier)->id,
        ]);
    }

    /** Leser und Original ersetzt: Positionen aus dem Test statt aus einem PDF. */
    private function bindImport(ProviderInvoice $invoice): void {
        $this->app->bind(IncomingProviderInvoiceImport::class, static fn($app): IncomingProviderInvoiceImport => new class($invoice, ...array_values($app->make('resale.test.import.deps'))) extends IncomingProviderInvoiceImport {
            public function __construct(private readonly ProviderInvoice $fixture, ...$deps) {
                parent::__construct(...$deps);
            }

            protected function read(string $path, string $name): ProviderInvoice {
                return $this->fixture;
            }

            protected function storedOriginal(IncomingEInvoice $incoming): ?array {
                return ['contents' => '', 'mime' => 'application/pdf', 'path' => '/tmp/fixture.pdf', 'name' => 'Rechnung.pdf'];
            }
        });
    }

    public function test_supplier_mapping_dialog_suggests_and_saves_and_removes(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.purchases.suppliers.create'))
            ->assertOk()
            ->assertSee(__('resale.purchase.supplier.title'))
            ->assertSee(__('resale.purchase.supplier.suggested', ['provider' => SubscriptionProvider::QualityHosting->label()]))
            ->assertSee(__('resale.purchase.supplier.empty'));
        $this->assertSame(SubscriptionProvider::DomainReselling, app(ProviderSuppliers::class)->suggest('united-domains AG'));
        $this->assertNull(app(ProviderSuppliers::class)->suggest('Bürobedarf Müller'));

        $this->actingAs($admin)->from(route('finance.resale.purchases.index'))
            ->post(route('finance.resale.purchases.suppliers.store'), ['supplier_id' => $this->supplier->sqid, 'provider' => 'qualityhosting'])
            ->assertRedirect(route('finance.resale.purchases.index'))->assertSessionHas('success');
        $mapping = ResaleProviderSupplier::query()->firstOrFail();
        $this->assertSame(SubscriptionProvider::QualityHosting, $mapping->provider);
        $this->assertSame(SubscriptionProvider::QualityHosting, app(ProviderSuppliers::class)->providerFor($this->organization, $this->supplier));
        $this->actingAs($admin)->get(route('finance.resale.purchases.index'))->assertOk()->assertSee(route('finance.resale.purchases.suppliers.create'), false);

        // Fremder Lieferant: abgelehnt; Löschen entfernt die Zuordnung.
        $other = Supplier::factory()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        $this->actingAs($admin)->post(route('finance.resale.purchases.suppliers.store'), ['supplier_id' => $other->sqid, 'provider' => 'qualityhosting'])->assertSessionHasErrors('supplier_id');
        $this->actingAs($admin)->delete(route('finance.resale.purchases.suppliers.destroy', $mapping->sqid))->assertRedirect(route('finance.resale.purchases.index'));
        $this->assertSame(0, ResaleProviderSupplier::query()->count());
        $this->actingAs($this->orgUser())->get(route('finance.resale.purchases.suppliers.create'))->assertForbidden();
    }

    public function test_assignment_in_the_inbox_queues_the_import_only_for_mapped_suppliers(): void {
        Queue::fake();
        $incoming = $this->incoming('31970999', '2026-09-03', '187.92');
        IncomingInvoiceAssigned::dispatch($incoming);
        Queue::assertNotPushed(ImportIncomingProviderInvoiceJob::class);

        ResaleProviderSupplier::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id, 'provider' => SubscriptionProvider::QualityHosting]);
        IncomingInvoiceAssigned::dispatch($incoming->fresh() ?? $incoming);
        Queue::assertPushed(ImportIncomingProviderInvoiceJob::class, static fn(ImportIncomingProviderInvoiceJob $job): bool => $job->incomingId === $incoming->id);
    }

    public function test_quality_hosting_invoice_is_imported_line_by_line_with_origin_and_unmatched_rows(): void {
        ResaleProviderSupplier::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $this->supplier->id, 'provider' => SubscriptionProvider::QualityHosting]);
        $subscription = $this->subscription();
        $incoming = $this->incoming('31970999', '2026-09-03', '210.42');
        $this->app->instance('resale.test.import.deps', [
            app(ProviderSuppliers::class), app(\App\Services\Reselling\Register\PurchaseAllocator::class), app(\App\Services\Reselling\Marketplace\QualityHostingInvoiceReader::class),
            app(\App\Services\Billing\Purchase\IncomingEInvoicePurchaseDocumentSource::class), app(\App\Services\Invoicing\EInvoice\IncomingEInvoiceService::class),
        ]);
        $this->bindImport(new ProviderInvoice('31970999', CarbonImmutable::parse('2026-09-03'), false, '95229', [
            new ProviderInvoiceLine(1, 1.0, 'Microsoft 365 Business Premium', 187.92, 187.92, 'CNLCON00156', 'CNL00007', 'Klimpel Bäder GmbH', CarbonImmutable::parse('2026-09-03'), CarbonImmutable::parse('2027-09-02')),
            new ProviderInvoiceLine(2, 1.0, 'Exchange Online Plan 1', 22.50, 22.50, 'CNLCON99999', 'CNL00099', 'Unbekannte GmbH', CarbonImmutable::parse('2026-09-03'), CarbonImmutable::parse('2027-09-02')),
        ], 210.42));

        $result = app(IncomingProviderInvoiceImport::class)->import($incoming);
        $this->assertNotNull($result);
        $this->assertSame(IncomingProviderInvoiceImport::KIND_LINES, $result['kind']);
        $this->assertSame(1, $result['entries']);
        $this->assertCount(1, $result['unmatched']);
        $this->assertNull($result['error']);

        $entries = ResalePurchaseEntry::query()->orderBy('id')->get();
        $this->assertCount(2, $entries, 'Treffer und Position ohne Abo');
        $matched = $entries->firstWhere('subscription_id', $subscription->id);
        $this->assertNotNull($matched);
        $this->assertSame($incoming->getMorphClass(), $matched->document_type, 'Herkunft ist die Eingangsrechnung');
        $this->assertSame($incoming->id, $matched->document_id);
        $this->assertSame('31970999', $matched->document_number);
        $this->assertSame('2026-09-03', $matched->period?->starts_on->toDateString(), 'Periode aus der Laufzeit der Position');
        $unmatched = $entries->firstWhere('subscription_id', null);
        $this->assertNotNull($unmatched);
        $this->assertSame('22.50', $unmatched->net_amount->withScale(2)->getAmount());

        // Zweiter Lauf: Dubletten, nichts doppelt.
        $again = app(IncomingProviderInvoiceImport::class)->import($incoming);
        $this->assertSame(2, $again['duplicates'] ?? 0);
        $this->assertSame(2, ResalePurchaseEntry::query()->count());

        // Prüffall im Einkauf-Reiter: Filter, Abzeichen, Zuordnen.
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.purchases.index', ['unmatched' => 1]))
            ->assertOk()->assertSee(__('resale.purchase.unmatched.badge'))->assertSee('Exchange Online Plan 1')->assertDontSee('name="ids[]"', false);
        $exchange = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'external_id' => 'CNLCON00157', 'company_name' => 'Unbekannte GmbH']);
        $this->actingAs($admin)->get(route('finance.resale.purchases.assign.create', $unmatched->sqid))->assertOk()->assertSee('Exchange Online (Plan 1)');
        $this->actingAs($admin)->from(route('finance.resale.purchases.index'))
            ->put(route('finance.resale.purchases.assign.store', $unmatched->sqid), ['subscription_id' => $exchange->sqid])
            ->assertRedirect(route('finance.resale.purchases.index', ['unmatched' => 1]))->assertSessionHas('success');
        $unmatched->refresh();
        $this->assertSame($exchange->id, $unmatched->subscription_id);
        $this->assertSame('2026-09-03', $unmatched->period?->starts_on->toDateString());
        $this->actingAs($admin)->get(route('finance.resale.purchases.assign.create', $unmatched->sqid))->assertNotFound();
    }

    public function test_other_providers_are_allocated_pro_rata_on_the_invoice_month_and_the_command_catches_up(): void {
        $telekom = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Telekom Deutschland GmbH']);
        ResaleProviderSupplier::query()->create(['organization_id' => $this->organization->id, 'supplier_id' => $telekom->id, 'provider' => SubscriptionProvider::TelekomMarketplace]);
        $this->subscription(['provider' => 'telekom_marketplace', 'external_id' => 'ent-0001', 'purchase_unit_price' => '244.76']);
        $incoming = $this->incoming('TK-2026-09', '2026-09-01', '20.40', $telekom);

        $result = app(IncomingProviderInvoiceImport::class)->import($incoming);
        $this->assertSame(IncomingProviderInvoiceImport::KIND_PRO_RATA, $result['kind'] ?? null);
        $this->assertSame(2, $result['entries'], 'September 2026 berührt die Perioden 2025/26 und 2026/27');
        $entries = ResalePurchaseEntry::query()->get();
        $this->assertSame('20.40', \CommonToolkit\ValueObjects\Money::ofFloat((float) $entries->sum(static fn(ResalePurchaseEntry $e): float => $e->net_amount->toFloat()), \CommonToolkit\Enums\CurrencyCode::Euro, 2)->getAmount());
        $this->assertSame(ResalePurchaseEntry::SOURCE_VOUCHER, $entries->first()?->source);
        $this->assertSame($incoming->id, $entries->first()?->document_id);

        // Ohne Zuordnung: nicht zuständig; Befehl holt zugeordnete Rechnungen nach, ohne zu doppeln.
        $this->assertNull(app(IncomingProviderInvoiceImport::class)->import($this->incoming('X-1', '2026-09-02', '10.00')));
        Artisan::call('resale:import-incoming', ['--since' => '2026-08-01']);
        $this->assertStringContainsString('1 Eingangsrechnungen übernommen, 1 ohne Anbieter-Zuordnung', Artisan::output());
        $this->assertSame(2, ResalePurchaseEntry::query()->count(), 'pro rata ersetzt die eigene Zuteilung, keine Dublette');
        $this->assertSame(\Illuminate\Console\Command::INVALID, Artisan::call('resale:import-incoming', ['--since' => 'gestern']), 'ungültiges Datum');
    }

    public function test_licence_account_shows_expected_and_actual_purchase_and_margin(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $subscription = $this->subscription(['customer_id' => $customer->id, 'provider_account' => 'CNL00007', 'quantity' => 2, 'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20']);
        $period = $subscription->periods()->where('starts_on', '2025-09-03')->firstOrFail();
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'period_id' => $period->id, 'provider' => 'qualityhosting',
            'source' => ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, 'document_number' => '31970000', 'entry_date' => '2025-09-03', 'description' => 'Rechnung', 'net_amount' => '380.00', 'currency' => 'EUR', 'raw_hash' => 'h1',
        ]);

        $account = ResaleSubscription::accountKeyOf($subscription->provider, (string) $subscription->provider_account);
        $page = $this->actingAs($this->orgAdmin())->get(route('finance.resale.accounts.show', $account))->assertOk();
        $page->assertSee(__('resale.account.col.actual_purchase'))
            ->assertSee('380,00')   // Ist-Einkauf der Periode 2025 (einzige mit Beleg)
            ->assertSee(__('resale.account.economics_partial', ['with' => 1, 'periods' => 2]))
            ->assertSee('751,68');  // Soll-Einkauf 2 × 187,92 × 2 Perioden
        // Marge: Soll-Verkauf 2 × 2 × 247,20 = 988,80 − (380,00 + 375,84) = 232,96
        $page->assertSee('232,96');
    }
}
