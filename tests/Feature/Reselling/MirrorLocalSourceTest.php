<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorLocalSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus, ResaleArticleRole};
use App\Models\{Article, Customer, Invoice, InvoiceItem};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\{InvoiceMirror, LocalInvoiceMirrorSource};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Lokale Rechnungen als Spiegelquelle (Feature 152, Review 2026-09-10,
 * Spiegel-Abstraktion): Lizenzposition über den Artikel des Abos oder den
 * Namensmatch, Vorschlagslauf deckt die Periode, Storno neutralisiert,
 * Entwürfe sind keine Kandidaten, Bezug nie über Kundengrenzen.
 */
class MirrorLocalSourceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $premium;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-10');
        $this->premium = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'M365-BP', 'name' => 'Microsoft 365 Business Premium', 'sellable' => true]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'article_id' => $this->premium->id, 'quantity' => 1, 'starts_on' => '2025-08-05',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '247.20',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /**
     * Lokale Rechnung mit Positionen; `article` null = Position ohne Artikel (nur Text).
     *
     * @param  list<array{article?: Article|null, description: string, quantity: float, unit?: string, unit_price?: string, service_date?: string|null}>  $items
     */
    private function invoice(string $number, string $issuedOn, array $items, string $status = Invoice::STATUS_ISSUED, ?Customer $customer = null): Invoice {
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => ($customer ?? $this->customer)->id, 'number' => $number, 'status' => $status,
            'type' => Invoice::TYPE_INVOICE, 'category' => 'resale', 'issued_on' => $status === Invoice::STATUS_DRAFT ? null : $issuedOn, 'currency' => 'EUR',
        ]);
        foreach ($items as $position => $item) {
            $invoice->items()->create([
                'organization_id' => $this->organization->id, 'position' => $position + 1, 'article_id' => ($item['article'] ?? null)?->id,
                'description' => $item['description'], 'quantity' => (string) $item['quantity'], 'unit' => $item['unit'] ?? 'Monat',
                'unit_price' => $item['unit_price'] ?? '20.60', 'service_date' => $item['service_date'] ?? $issuedOn,
            ]);
        }

        return $invoice;
    }

    public function test_local_invoice_with_the_subscription_article_covers_the_period(): void {
        $subscription = $this->subscription();
        $this->assertSame(['2025-08-05', '2026-08-05'], $subscription->periods->map(static fn($p) => $p->starts_on->toDateString())->all());
        $this->invoice('RE-2025-0820', '2025-08-06', [
            ['article' => $this->premium, 'description' => 'Microsoft 365 Business Premium · 05.08.2025 – 04.08.2026', 'quantity' => 12, 'service_date' => '2025-08-05'],
            ['article' => null, 'description' => 'Business Support', 'quantity' => 2, 'unit' => 'Stunde', 'unit_price' => '90.00'],
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(['periods' => 2, 'linked' => 1, 'partial' => 0, 'links' => 1, 'lines_without_subscription' => 0], $result);
        [$p2025, $p2026] = $subscription->periods()->with('links')->get();
        $this->assertSame(PeriodStatus::Billed, $p2025->status);
        $this->assertSame(PeriodStatus::Open, $p2026->status, 'zweites Jahr noch ohne Rechnung');
        $link = $p2025->links->first();
        $this->assertSame((new InvoiceItem)->getMorphClass(), $link?->linkable_type, 'Bezug zeigt auf die lokale Rechnungsposition');
        $this->assertSame('RE-2025-0820', $link?->voucher_number);
        $this->assertSame('12.00', $link?->months);
        $this->assertSame('247.20', $link?->amount?->getAmount(), '12 × 20,60');
        $this->assertSame(LinkOrigin::Proposed, $link?->origin);

        // Zweiter Lauf ersetzt, verdoppelt nicht.
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, ResalePeriodLink::query()->count());
    }

    public function test_line_without_article_matches_by_name_and_drafts_are_no_candidates(): void {
        $subscription = $this->subscription(['article_id' => null]);
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        // Entwurf allein: kein Kandidat.
        $this->invoice('RE-2025-0001', '2025-08-06', [['article' => null, 'description' => 'Microsoft 365 Business Premium · 05.08.2025 – 04.08.2026', 'quantity' => 12, 'service_date' => '2025-08-05']], Invoice::STATUS_DRAFT);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Open, $period->fresh()?->status, 'Rechnungsentwurf deckt nichts');
        $this->assertSame(0, ResalePeriodLink::query()->count());

        // Ausgestellt, ohne Artikel: der Positionstext trifft das Abo-Label.
        $this->invoice('RE-2025-0002', '2025-08-06', [['article' => null, 'description' => 'Microsoft 365 Business Premium · 05.08.2025 – 04.08.2026', 'quantity' => 12, 'service_date' => '2025-08-05']]);
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);
        $this->assertSame('RE-2025-0002', $period->links()->first()?->voucher_number);

        $source = app(InvoiceMirror::class)->source(LocalInvoiceMirrorSource::KEY);
        $this->assertNotNull($source);
        $numbers = $source->linesFor($this->organization, [$this->customer->id])->map(static fn($l): string => (string) $l->voucherNumber)->all();
        $this->assertSame(['RE-2025-0002'], $numbers, 'nur ausgestellte Rechnungen, nur Lizenzpositionen');
        $this->assertSame(0, $source->pendingCount($this->organization, [$this->customer->id]));
        $this->assertTrue($source->coversRecipient($this->organization, $this->customer), 'lokale Rechnungshoheit');
        $this->assertFalse($source->coversRecipient($this->organization, Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Lexoffice])));
    }

    public function test_cancelled_invoice_neutralises_confirmed_links(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription();
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $invoice = $this->invoice('RE-2025-0820', '2025-08-06', [['article' => $this->premium, 'description' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'service_date' => '2025-08-05']]);
        (new LinkProposer)->propose($this->organization);
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $period->sqid))->assertRedirect();
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);
        $this->assertNotNull($period->fresh()?->decided_at);

        $invoice->cancel('Falsche Menge', $admin->id);
        (new LinkProposer)->propose($this->organization);
        $period->refresh();
        $link = $period->links()->first();
        $this->assertSame('0.00', $link?->months, 'Storno: bestätigter Bezug bleibt als Spur mit 0 Monaten');
        $this->assertStringContainsString(__('resale.link.note_voided', ['months' => '1 × 12 ' . __('resale.link.months_short')]), (string) $link?->note);
        $this->assertSame(PeriodStatus::Open, $period->status);
        $this->assertNull($period->decided_at, 'Entscheidung aufgehoben — die Ersatzrechnung darf vorgeschlagen werden');
        $voided = app(InvoiceMirror::class)->voidedLines($this->organization, [$this->customer->id]);
        $this->assertSame(['RE-2025-0820'], $voided->map(static fn($l): string => (string) $l->voucherNumber)->all());
    }

    public function test_manual_link_checks_the_recipient_and_lists_local_lines(): void {
        $admin = $this->orgAdmin();
        $subscription = $this->subscription();
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fremde GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $theirs = $this->invoice('RE-2025-0900', '2025-08-06', [['article' => $this->premium, 'description' => 'Microsoft 365 Business Premium', 'quantity' => 12]], Invoice::STATUS_ISSUED, $other);
        $theirLine = $theirs->items()->firstOrFail();

        // Position eines anderen Kunden: abgelehnt (B13), nichts geschrieben.
        $this->actingAs($admin)->from(route('finance.resale.show', $subscription->sqid))->post(route('finance.resale.periods.link.store', $period->sqid), [
            'line_id' => Sqid::encode(InvoiceItem::class, $theirLine->id), 'months' => 12,
        ])->assertRedirect(route('finance.resale.show', $subscription->sqid))->assertSessionHasErrors('line_id');
        $this->assertSame(0, ResalePeriodLink::query()->count());

        $mine = $this->invoice('RE-2025-0901', '2025-08-07', [['article' => $this->premium, 'description' => 'Microsoft 365 Business Premium · Nachberechnung', 'quantity' => 12]]);
        $line = $mine->items()->firstOrFail();
        // Dialog und Abo-Seite kennen die lokale Position; Vorschau gibt es nur im Quellsystem, hier nicht.
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $period->sqid))->assertOk()
            ->assertSee('RE-2025-0901')->assertSee('Pos. 1')->assertDontSee('RE-2025-0900');
        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk()
            ->assertSee(__('resale.invoices.title'))->assertSee('RE-2025-0901')->assertSee('Nachberechnung')->assertDontSee(__('resale.mirror.no_source'));

        $this->actingAs($admin)->post(route('finance.resale.periods.link.store', $period->sqid), [
            'line_id' => Sqid::encode(InvoiceItem::class, $line->id), 'months' => 12,
        ])->assertRedirect(route('finance.resale.show', $subscription->sqid));
        $link = ResalePeriodLink::query()->firstOrFail();
        $this->assertSame((new InvoiceItem)->getMorphClass(), $link->linkable_type);
        $this->assertSame((int) $line->id, (int) $link->linkable_id);
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertSame('247.20', $link->amount?->getAmount());
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);

        // Periodenseite zeigt den Bezug ohne Lexoffice-Vorschau; Abgleich führt den Empfänger mit seiner Position.
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['status' => 'all']))->assertOk()->assertSee('RE-2025-0901');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $this->customer))->assertOk()->assertSee('RE-2025-0901')->assertDontSee('RE-2025-0900');
    }
    /**
     * Review 2026-09-11: die Einstufung des lokalen Artikels (`resale_role`)
     * entscheidet vor Abo-Artikel und Namensmatch; der Leistungszeitraum kommt
     * aus `service_from`/`service_to` der Position (Fallback Leistungsdatum).
     */
    public function test_article_role_steers_the_classification_and_service_period_comes_from_the_position(): void {
        $subscription = $this->subscription();
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $source = app(InvoiceMirror::class)->source(LocalInvoiceMirrorSource::KEY);
        $this->assertNotNull($source);

        // Abo-Artikel als „nie Abo-Position" eingestuft: auch mit passendem Text keine Lizenzposition.
        $this->premium->forceFill(['resale_role' => ResaleArticleRole::Excluded])->save();
        $this->invoice('RE-2025-0810', '2025-08-06', [['article' => $this->premium, 'description' => 'Microsoft 365 Business Premium · 05.08.2025 – 04.08.2026', 'quantity' => 12, 'service_date' => '2025-08-05']]);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Open, $period->fresh()?->status, 'ausgeschlossener Artikel deckt nichts');
        $this->assertSame(0, ResalePeriodLink::query()->count());
        $this->assertSame([], $source->linesFor($this->organization, [$this->customer->id])->all());

        // Als Abo-Produkt eingestufter Artikel ohne Abo und ohne Produktnamen: Lizenzposition — mit Zeitraum aus der Position.
        $this->premium->forceFill(['resale_role' => null])->save();
        $cloud = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'CAP', 'name' => 'Cloud-Arbeitsplatz Premium', 'resale_role' => ResaleArticleRole::License]);
        $invoice = $this->invoice('RE-2025-0811', '2025-08-07', [['article' => $cloud, 'description' => 'Cloud-Arbeitsplatz Premium · Jahreslizenz', 'quantity' => 12, 'service_date' => '2025-08-05']]);
        InvoiceItem::query()->whereKey($invoice->items()->firstOrFail()->id)->update(['service_from' => '2025-08-05', 'service_to' => '2026-08-04']);

        $lines = $source->linesFor($this->organization, [$this->customer->id]);
        $this->assertSame(['RE-2025-0810', 'RE-2025-0811'], $lines->map(static fn($l): string => (string) $l->voucherNumber)->sort()->values()->all(), 'Abo-Artikel ohne Einstufung zählt wieder, eingestufter Artikel dazu');
        $cloudLine = $lines->first(static fn($l): bool => $l->voucherNumber === 'RE-2025-0811');
        $this->assertNotNull($cloudLine);
        $this->assertTrue($cloudLine->articleIsLicence);
        $this->assertSame('05.08.2025 – 04.08.2026', $cloudLine->servicePeriodLabel());
        $this->assertSame(12, $cloudLine->serviceMonths());
        $premiumLine = $lines->first(static fn($l): bool => $l->voucherNumber === 'RE-2025-0810');
        $this->assertSame('05.08.2025', $premiumLine?->servicePeriodLabel(), 'nur Leistungsdatum → kein Ende');
        $this->assertNull($premiumLine?->serviceMonths());

        // Rechnungsliste am Abo: Leistungsende des Belegs = spätestes Positionsende.
        $voucher = $source->vouchersFor($this->organization, [$this->customer->id], CarbonImmutable::parse('2025-01-01'))->first(static fn($v): bool => $v->voucherNumber === 'RE-2025-0811');
        $this->assertSame('2026-08-04', $voucher?->serviceTo?->toDateString());
    }
}
