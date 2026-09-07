<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LinkProposerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, ExternalReference, ForeignCustomer, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Rechnungszuordnung (Feature 152, MVP-761): Vorschlagslauf gegen den
 * Belegspiegel, Partner-Sammelrechnungen mit Endkundennennung, Bestätigen,
 * Verzichten, manuelle Bezüge, Periodenseite.
 */
class LinkProposerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $premium;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->premium = $this->article('art-bp', 'Microsoft 365 Business Premium', '20.60');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)', '3.95');
    }

    private function article(string $externalId, string $name, string $price): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => $externalId, 'name' => $name, 'article_number' => strtoupper($externalId),
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => $price, 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function customerWithContact(string $name, string $contactId): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /**
     * @param  list<array{article?: LexofficeArticle|null, name: string, description?: string, quantity: float, unit?: string|null, net: string}>  $lines
     */
    private function voucher(string $contactId, string $number, string $date, array $lines, string $text = '', ?string $serviceFrom = null, ?string $serviceTo = null): LexofficeVoucher {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false,
            'voucher_text' => $text, 'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo, 'lines_synced_at' => now(),
        ]);
        foreach ($lines as $position => $line) {
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $position + 1, 'type' => 'service',
                'external_article_id' => $line['article']?->external_id, 'lexoffice_article_id' => $line['article']?->id,
                'name' => $line['name'], 'description' => $line['description'] ?? null, 'quantity' => $line['quantity'], 'unit_name' => $line['unit'] ?? 'Monat',
                'unit_net' => $line['net'], 'total_net' => (string) round((float) $line['net'] * $line['quantity'], 2), 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $voucher;
    }

    public function test_proposal_links_nearest_invoice_lines_and_sets_period_status(): void {
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->premium->id, 'starts_on' => '2025-08-05', 'sale_unit_price' => '247.20']);
        $this->assertSame(['2025-08-05', '2026-08-05'], $subscription->periods->map(static fn($p) => $p->starts_on->toDateString())->all());

        // 2025 wurde erst im Oktober berechnet, 2026 pünktlich — plus eine Support-Zeile, die nie zählt.
        $this->voucher('c-kl', 'RE/2025/0820', '2025-10-14', [
            ['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60'],
            ['article' => null, 'name' => 'Business Support', 'description' => 'Microsoft 365 Einrichtung', 'quantity' => 2, 'unit' => 'Stunde', 'net' => '90.00'],
        ]);
        $this->voucher('c-kl', 'RE/2026/1022', '2026-08-22', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(['periods' => 2, 'linked' => 2, 'partial' => 0, 'links' => 2, 'lines_without_subscription' => 0], $result);

        $subscription->refresh();
        [$p2025, $p2026] = $subscription->periods;
        $this->assertSame(PeriodStatus::Billed, $p2025->status);
        $this->assertSame('RE/2025/0820', $p2025->links->first()?->voucher_number);
        $this->assertSame('12.00', $p2025->links->first()?->months);
        $this->assertSame('247.20', $p2025->links->first()?->amount?->getAmount());
        $this->assertSame(LinkOrigin::Proposed, $p2025->links->first()?->origin);
        $this->assertSame('RE/2026/1022', $p2026->links->first()?->voucher_number, 'nächste Periode zuerst — 2025 schluckt 2026 nicht');
        $this->assertNull($p2025->decided_at, 'Vorschlag ist keine Entscheidung');

        // Zweiter Lauf: Vorschläge werden ersetzt, nicht verdoppelt.
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, ResalePeriodLink::query()->count());
    }

    public function test_article_classification_override_controls_what_counts_as_a_licence_line(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->premium->id, 'starts_on' => '2025-08-05']);
        // Dienstleistung mit Microsoft im Namen: die Erkennung hielte sie für ein Produkt.
        $service = $this->article('art-pc', 'Microsoft Partner Center Verwaltung', '90.00');
        $this->voucher('c-kl', 'RE/2025/0900', '2025-08-06', [
            ['article' => $service, 'name' => 'Microsoft Partner Center Verwaltung', 'quantity' => 12, 'unit' => 'Monat', 'net' => '20.00'],
        ]);
        $classifier = new \App\Services\Reselling\Register\LicenseArticleClassifier;
        $this->assertTrue($classifier->isLicense($service), 'ohne Einstufung: Name trifft');

        $this->actingAs($admin)->get(route('finance.resale.products'))->assertOk()->assertSee('Microsoft Partner Center Verwaltung');
        $this->actingAs($admin)->post(route('finance.resale.products.store'), [
            'article_id' => \App\Support\Sqid::encode(LexofficeArticle::class, $service->id),
            'role' => 'excluded',
        ])->assertRedirect(route('finance.resale.products'));
        $this->assertFalse($classifier->isLicense($service->fresh()), 'Betreiber: nie Abo-Position');

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(0, $result['links'], 'ausgeschlossener Artikel wird nie zugeordnet');
        $this->assertSame(0, $result['lines_without_subscription'], 'und zählt auch nicht als Lizenzposition ohne Abo');
        $this->assertSame(PeriodStatus::Open, $subscription->periods()->first()?->status);

        // Umgekehrt: Artikel ohne Microsoft im Namen als Abo-Produkt erzwingen.
        $plain = $this->article('art-plain', 'Cloud-Arbeitsplatz Premium', '20.60');
        $this->voucher('c-kl', 'RE/2025/0901', '2025-08-07', [['article' => $plain, 'name' => 'Cloud-Arbeitsplatz Premium', 'quantity' => 12, 'unit' => 'Monat', 'net' => '20.60']]);
        $this->assertFalse($classifier->isLicense($plain));
        $plain->forceFill(['resale_role' => \App\Enums\Reselling\ResaleArticleRole::License])->save();
        $subscription->forceFill(['lexoffice_article_id' => $plain->id, 'label' => 'Cloud-Arbeitsplatz Premium'])->save();
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $subscription->periods()->first()?->fresh()?->status);
    }

    public function test_end_customer_in_closing_text_is_recognised_even_when_shortened(): void {
        $partner = $this->customerWithContact('LDS Systems GmbH', 'c-lds');
        $haus = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Haus 24 GmbH', 'matchcode' => null]);
        $kaik = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik', 'matchcode' => 'STBK']);
        $subHaus = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $haus->id, 'lexoffice_article_id' => $this->premium->id, 'starts_on' => '2025-09-04']);
        $subKaik = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $kaik->id, 'lexoffice_article_id' => $this->premium->id, 'starts_on' => '2025-09-04']);

        // Gleicher Tag, gleiches Produkt — nur der Schlusstext unterscheidet die Endkunden.
        $this->voucher('c-lds', 'RE/2025/1116', '2025-09-04', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 12, 'net' => '12.13']], 'Rechnung Unsere Lieferungen/Leistungen stellen wir Ihnen wie folgt in Rechnung. Vielen Dank für die gute Zusammenarbeit. (M365 Haus24)');
        $this->voucher('c-lds', 'RE/2025/1117', '2025-09-05', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 12, 'net' => '12.13']], 'Rechnung Vielen Dank für die gute Zusammenarbeit. M365 STBK');

        (new LinkProposer)->propose($this->organization);
        $this->assertSame('RE/2025/1116', $subHaus->periods()->first()?->links()->first()?->voucher_number, '„Haus24" trifft „Haus 24 GmbH" ohne Leerzeichen');
        $this->assertSame('RE/2025/1117', $subKaik->periods()->first()?->links()->first()?->voucher_number, 'Matchcode STBK im Schlusstext');

        $voucher = LexofficeVoucher::query()->where('voucher_number', 'RE/2025/1116')->firstOrFail();
        $this->assertSame('M365 Haus24', $voucher->voucherTextHint());
    }

    public function test_partner_invoice_lines_need_the_end_customer_name(): void {
        $partner = $this->customerWithContact('LDS Systems GmbH', 'c-lds');
        $kaik = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $ute = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Ute Mayershofer']);
        $subKaik = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $kaik->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 1]);
        $subUte = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $ute->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 3]);

        $this->voucher('c-lds', 'RE/2025/0945', '2025-10-26', [
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Endkunde Steuerbüro Kaik', 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Ute Mayershofer, 2 Postfächer', 'quantity' => 24, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => '', 'quantity' => 12, 'net' => '3.95'],
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links']);
        $this->assertSame(1, $result['lines_without_subscription'], 'die Zeile ohne Endkunden bleibt liegen: nächste Periode (Kaik) ist schon gedeckt, im Fenster-Pass zählt ohne Nennung nichts');

        $this->assertSame(PeriodStatus::Billed, $subKaik->periods()->first()?->status);
        $this->assertSame(PeriodStatus::Partial, $subUte->periods()->first()?->status, '24 von 36 Lizenzmonaten');
        $this->assertSame('24.00', $subUte->periods()->first()?->links()->first()?->months);
    }

    public function test_confirm_waive_reopen_and_manual_link_via_ui(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->premium->id, 'starts_on' => '2025-08-05']);
        $this->voucher('c-kl', 'RE/2025/0820', '2025-10-14', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);

        $this->actingAs($admin)->post(route('finance.resale.periods.propose'))->assertRedirect();
        [$p2025, $p2026] = $subscription->periods()->get();
        $this->assertTrue($p2025->isProposedOnly());

        $voucherSqid = \App\Support\Sqid::encode(\App\Models\LexofficeVoucher::class, LexofficeVoucher::query()->where('voucher_number', 'RE/2025/0820')->value('id'));
        $this->actingAs($admin)->get(route('finance.resale.periods.index'))->assertOk()->assertSee('RE/2025/0820')->assertSee(__('resale.link.proposed_hint'))
            ->assertSee(route('lexoffice.vouchers.preview', $voucherSqid), false);

        // Bestätigen
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $p2025->sqid))->assertRedirect();
        $p2025->refresh();
        $this->assertSame(PeriodStatus::Billed, $p2025->status);
        $this->assertNotNull($p2025->decided_at);
        $this->assertSame(LinkOrigin::Confirmed, $p2025->links()->first()?->origin);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(LinkOrigin::Confirmed, $p2025->links()->first()?->origin, 'bestätigte Bezüge überleben den Vorschlagslauf');

        // Verzichten auf 2026, dann zurücknehmen
        $this->actingAs($admin)->get(route('finance.resale.periods.waive.create', $p2026->sqid))->assertOk();
        // Zurück auf die Seite, von der der Dialog kam (Perioden, Abo oder Abgleich).
        $this->actingAs($admin)->from(route('finance.resale.periods.index'))->post(route('finance.resale.periods.waive', $p2026->sqid), ['decision' => 'waived', 'reason' => 'Kulanz'])->assertRedirect(route('finance.resale.periods.index'));
        $p2026->refresh();
        $this->assertSame(PeriodStatus::Waived, $p2026->status);
        $this->assertSame('Kulanz', $p2026->waived_reason);
        $this->actingAs($admin)->post(route('finance.resale.periods.reopen', $p2026->sqid))->assertRedirect();
        $this->assertSame(PeriodStatus::Open, $p2026->fresh()?->status);

        // Manueller Bezug für 2026 auf eine Rechnung, die der Vorschlagslauf nicht kennt
        $voucher = $this->voucher('c-kl', 'RE/2026/0001', '2026-08-30', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'description' => 'Nachberechnung', 'quantity' => 12, 'net' => '20.60']]);
        $line = $voucher->lines()->first();
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2026->sqid))->assertOk()->assertSee('RE/2026/0001');
        $this->actingAs($admin)->post(route('finance.resale.periods.link.store', $p2026->sqid), ['line_id' => \App\Support\Sqid::encode(\App\Models\LexofficeVoucherLine::class, $line?->id), 'months' => 12])
            ->assertRedirect(route('finance.resale.show', $subscription->sqid));
        $p2026->refresh();
        $this->assertSame(PeriodStatus::Billed, $p2026->status);
        $link = $p2026->links()->first();
        $this->assertSame(LinkOrigin::Manual, $link?->origin);
        $this->assertSame('247.20', $link?->amount?->getAmount());

        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk()->assertSee('RE/2026/0001');
        $this->actingAs($admin)->delete(route('finance.resale.links.destroy', $link?->sqid))->assertRedirect();
        $this->assertSame(PeriodStatus::Open, $p2026->fresh()?->status);

        // Abo-Seite listet die Rechnungen des Empfängers mit Schnellzuordnung je Position.
        $page = $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk();
        $page->assertSee(__('resale.invoices.title'))->assertSee('RE/2026/0001')->assertSee('Nachberechnung');
        // Schnellzuordnung in Lizenzen: 1 × 12 Monate je Lizenz.
        $this->actingAs($admin)->post(route('finance.resale.links.quick', $subscription->sqid), [
            'period_id' => $p2026->sqid,
            'line_id' => \App\Support\Sqid::encode(\App\Models\LexofficeVoucherLine::class, $line?->id),
            'licences' => 1,
            'per_licence' => 12,
        ])->assertRedirect(route('finance.resale.show', $subscription->sqid))->assertSessionHas('success');
        $this->assertSame(PeriodStatus::Billed, $p2026->fresh()?->status);
        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk()
            ->assertSee('1 × 12 ' . __('resale.link.months_short'))
            ->assertSee('1 / 1 Lizenzen · 12 Mon.');
        $this->actingAs($this->orgUser())->post(route('finance.resale.periods.propose'))->assertForbidden();
    }

    public function test_multi_year_line_stays_with_one_subscription_across_consecutive_periods(): void {
        // ReproBerlin-Muster: drei Verträge desselben Produkts (1 + 1 + 2 Lizenzen), zwei Jahre
        // erst im August 2025 nachberechnet — je Lizenz eine Position „24 Monat".
        $this->travelTo('2025-12-01');
        $customer = $this->customerWithContact('ReproBerlin GmbH', 'c-repro');
        $standard = $this->article('art-bs', 'Microsoft 365 Business Standard', '11.70');
        $one = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'lexoffice_article_id' => $standard->id, 'starts_on' => '2024-02-07', 'ends_on' => '2026-02-07', 'status' => 'cancelled']);
        $two = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'lexoffice_article_id' => $standard->id, 'starts_on' => '2024-02-25', 'ends_on' => '2026-02-25', 'status' => 'cancelled']);
        $pair = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'lexoffice_article_id' => $standard->id, 'starts_on' => '2024-02-25', 'ends_on' => '2026-02-25', 'quantity' => 2, 'status' => 'cancelled']);
        // Leistungszeitraum zwei Jahre: damit ist „24 Monat" sicher EINE Lizenz über zwei Perioden.
        $this->voucher('c-repro', 'RE/2025/0895', '2025-08-21', [['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 24, 'net' => '11.70']], '', '2024-02-07', '2026-02-06');
        $this->voucher('c-repro', 'RE/2025/0896', '2025-08-21', [
            ['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 24, 'net' => '11.70'],
            ['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 24, 'net' => '11.70'],
            ['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 24, 'net' => '11.70'],
        ], '', '2024-02-25', '2026-02-24');

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(6, $result['linked'], 'alle sechs Perioden gedeckt');

        foreach ([$one, $two, $pair] as $subscription) {
            $periods = $subscription->periods()->with('links')->get();
            $this->assertCount(2, $periods);
            // Jede Position deckt je Periode höchstens eine Lizenz × 12 — und bleibt beim selben Abo.
            $first = $periods[0]->links->pluck('linkable_id')->sort()->values()->all();
            $second = $periods[1]->links->pluck('linkable_id')->sort()->values()->all();
            $this->assertSame($first, $second, 'dieselben Positionen decken beide Jahre des Abos');
            $this->assertCount($subscription->quantity, $first);
            foreach ($periods as $period) {
                foreach ($period->links as $link) {
                    $this->assertSame('12.00', $link->months, 'eine Lizenz × 12 je Periode, nicht 24');
                }
            }
        }
        // Keine Position hängt an zwei verschiedenen Abos.
        $bySubscription = ResalePeriodLink::query()->get()->groupBy('linkable_id')->map(static fn($links) => $links->pluck('subscription_id')->unique()->count());
        $this->assertSame([1, 1, 1, 1], $bySubscription->values()->all());
    }
}
