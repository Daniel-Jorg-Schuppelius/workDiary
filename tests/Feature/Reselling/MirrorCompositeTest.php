<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorCompositeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\PeriodStatus;
use App\Models\{Article, Customer, ExternalReference, Invoice, InvoiceItem, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeInvoiceMirrorSource;
use App\Services\Reselling\Mirror\{InvoiceMirror, LocalInvoiceMirrorSource};
use App\Services\Reselling\Register\{LinkProposer, PeriodLinker, PeriodPlanner, RecipientReconciler};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegspiegel als Composite (Feature 152, Review 2026-09-10, Spiegel-
 * Abstraktion): ein Empfänger mit lokaler Rechnungshoheit und einer mit
 * Lexoffice im selben Register; eine Registry ohne Lexoffice-Quelle sieht
 * nur lokale Rechnungen.
 */
class MirrorCompositeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $local;

    private Customer $lexoffice;

    private ResaleSubscription $localSubscription;

    private ResaleSubscription $lexofficeSubscription;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-10');

        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'M365-BP', 'name' => 'Microsoft 365 Business Premium', 'sellable' => true]);
        $lexArticle = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->local = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $this->lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH', 'billing_mode' => BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-lds', 'referenceable_type' => $this->lexoffice->getMorphClass(), 'referenceable_id' => $this->lexoffice->getKey(),
        ]);

        $base = [
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Microsoft 365 Business Premium',
            'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ];
        $this->localSubscription = ResaleSubscription::query()->create($base + ['customer_id' => $this->local->id, 'article_id' => $article->id]);
        $this->lexofficeSubscription = ResaleSubscription::query()->create($base + ['customer_id' => $this->lexoffice->id, 'lexoffice_article_id' => $lexArticle->id]);
        (new PeriodPlanner)->sync($this->localSubscription);
        (new PeriodPlanner)->sync($this->lexofficeSubscription);

        // Lokale Rechnung für den einen, Lexoffice-Spiegel für den anderen — beide decken die erste Periode.
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $this->local->id, 'number' => 'RE-2025-0820', 'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE, 'category' => 'resale', 'issued_on' => '2025-08-06', 'currency' => 'EUR',
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id, 'position' => 1, 'article_id' => $article->id, 'description' => 'Microsoft 365 Business Premium · 05.08.2025 – 04.08.2026',
            'quantity' => '12', 'unit' => 'Monat', 'unit_price' => '20.60', 'service_date' => '2025-08-05',
        ]);
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-1', 'contact_external_id' => 'c-lds', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2025/0821', 'voucher_date' => '2025-08-07', 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-bp', 'lexoffice_article_id' => $lexArticle->id, 'name' => 'Microsoft 365 Business Premium',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '20.60', 'total_net' => '247.20', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_both_sources_are_registered_and_cover_their_recipients_in_one_run(): void {
        $mirror = app(InvoiceMirror::class);
        $this->assertSame(['local', 'lexoffice'], array_map(static fn($s): string => $s->key(), $mirror->sources()), 'Kern registriert lokal, das Plugin Lexoffice');
        $this->assertInstanceOf(LexofficeInvoiceMirrorSource::class, $mirror->sourceFor((new LexofficeVoucherLine)->getMorphClass()));
        $this->assertInstanceOf(LocalInvoiceMirrorSource::class, $mirror->sourceFor((new InvoiceItem)->getMorphClass()));
        $this->assertTrue($mirror->coversRecipient($this->organization, $this->local));
        $this->assertTrue($mirror->coversRecipient($this->organization, $this->lexoffice));

        $lines = $mirror->linesFor($this->organization, null);
        $this->assertSame(['RE-2025-0820' => 'local', 'RE/2025/0821' => 'lexoffice'], $lines->mapWithKeys(static fn($l): array => [(string) $l->voucherNumber => $l->sourceKey])->all());
        $this->assertSame([$this->local->id, $this->lexoffice->id], $lines->map(static fn($l): ?int => $l->recipientCustomerId)->all(), 'Empfänger je Quelle als Kunde');

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links']);
        $this->assertSame(2, $result['linked']);
        $localLink = $this->localSubscription->periods()->orderBy('starts_on')->firstOrFail()->links()->firstOrFail();
        $lexLink = $this->lexofficeSubscription->periods()->orderBy('starts_on')->firstOrFail()->links()->firstOrFail();
        $this->assertSame((new InvoiceItem)->getMorphClass(), $localLink->linkable_type);
        $this->assertSame((new LexofficeVoucherLine)->getMorphClass(), $lexLink->linkable_type);
        $this->assertSame(['RE-2025-0820', 'RE/2025/0821'], [$localLink->voucher_number, $lexLink->voucher_number]);

        // Verbrauch quellübergreifend: dieselbe Positions-ID in zwei Quellen ist zweimal 12 Monate, nicht 24.
        $consumed = (new PeriodLinker)->consumed($lines);
        $this->assertCount(2, $consumed);
        foreach ($consumed as $entry) {
            $this->assertSame(12.0, $entry['months']);
        }

        // Abgleich-Übersicht führt beide Empfänger mit je einer Position, ohne Vorschlag beide berechnet.
        $rows = (new RecipientReconciler)->overview($this->organization);
        $byName = array_column($rows, null, 'name');
        $this->assertSame(1, $byName['Klimpel Bäder GmbH']['lines']);
        $this->assertSame(1, $byName['LDS Systems GmbH']['lines']);
        $this->assertSame([1, 1], [$byName['Klimpel Bäder GmbH']['proposed'], $byName['LDS Systems GmbH']['proposed']]);
        $this->assertSame([0.0, 0.0], [$byName['Klimpel Bäder GmbH']['free'], $byName['LDS Systems GmbH']['free']]);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['status' => 'all']))->assertOk()->assertSee('RE-2025-0820')->assertSee('RE/2025/0821');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'all']))->assertOk()->assertSee('Klimpel Bäder GmbH')->assertSee('LDS Systems GmbH');
    }

    public function test_registry_without_the_lexoffice_source_sees_only_local_invoices(): void {
        $mirror = new InvoiceMirror;
        $mirror->register(new LocalInvoiceMirrorSource);
        $this->assertNull($mirror->sourceFor((new LexofficeVoucherLine)->getMorphClass()));
        $this->assertFalse($mirror->coversRecipient($this->organization, $this->lexoffice), 'ohne Lexoffice-Quelle keine Rechnungen für den Lexoffice-Empfänger');

        $this->assertSame(['RE-2025-0820'], $mirror->linesFor($this->organization, null)->map(static fn($l): string => (string) $l->voucherNumber)->all());
        $result = (new LinkProposer(mirror: $mirror))->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $this->localSubscription->periods()->orderBy('starts_on')->firstOrFail()->status);
        $this->assertSame(PeriodStatus::Open, $this->lexofficeSubscription->periods()->orderBy('starts_on')->firstOrFail()->status, 'Lexoffice-Spiegel unsichtbar');
        $this->assertSame(1, ResalePeriodLink::query()->count());

        // Der volle Spiegel deckt danach auch den Lexoffice-Empfänger — der lokale Bezug wird ersetzt, nicht verdoppelt.
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, ResalePeriodLink::query()->count());
        $this->assertSame(PeriodStatus::Billed, $this->lexofficeSubscription->periods()->orderBy('starts_on')->firstOrFail()->status);
    }
}
