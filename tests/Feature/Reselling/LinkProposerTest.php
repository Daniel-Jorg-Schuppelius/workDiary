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
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{LinkProposer, PeriodPlanner};
use App\Support\MorphMap;
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
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05', 'sale_unit_price' => '247.20']);
        $this->assertSame(['2025-08-05', '2026-08-05'], $subscription->periods->map(static fn($p) => $p->starts_on->toDateString())->all());

        // 2025 wurde erst im Oktober berechnet, 2026 pünktlich — plus eine Support-Zeile, die nie zählt.
        $this->voucher('c-kl', 'RE/2025/0820', '2025-10-14', [
            ['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60'],
            ['article' => null, 'name' => 'Business Support', 'description' => 'Microsoft 365 Einrichtung', 'quantity' => 2, 'unit' => 'Stunde', 'net' => '90.00'],
        ]);
        $this->voucher('c-kl', 'RE/2026/1022', '2026-08-22', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(['periods' => 2, 'linked' => 2, 'partial' => 0, 'links' => 2, 'lines_without_subscription' => 0, 'auto_confirmed' => 1], $result);

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
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        // Dienstleistung mit Microsoft im Namen: die Erkennung hielte sie für ein Produkt.
        $service = $this->article('art-pc', 'Microsoft Partner Center Verwaltung', '90.00');
        $this->voucher('c-kl', 'RE/2025/0900', '2025-08-06', [
            ['article' => $service, 'name' => 'Microsoft Partner Center Verwaltung', 'quantity' => 12, 'unit' => 'Monat', 'net' => '20.00'],
        ]);
        $org = (int) $this->organization->id;
        $isLicense = static fn (LexofficeArticle $article): bool => (new \App\Services\Reselling\Register\LicenseArticleClassifier)->isLicense($org, 'lex:' . $article->id, $article->name);
        $this->assertTrue($isLicense($service), 'ohne Einstufung: Name trifft');

        $this->actingAs($admin)->get(route('finance.resale.products'))->assertOk()->assertSee('Microsoft Partner Center Verwaltung');
        $this->actingAs($admin)->post(route('finance.resale.products.store'), [
            'article' => 'lex:' . \App\Support\Sqid::encode(LexofficeArticle::class, (int) $service->id),
            'role' => 'excluded',
        ])->assertRedirect(route('finance.resale.products'));
        $this->assertFalse($isLicense($service), 'Betreiber: nie Abo-Position');

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(0, $result['links'], 'ausgeschlossener Artikel wird nie zugeordnet');
        $this->assertSame(0, $result['lines_without_subscription'], 'und zählt auch nicht als Lizenzposition ohne Abo');
        $this->assertSame(PeriodStatus::Open, $subscription->periods()->first()?->status);

        // Umgekehrt: Artikel ohne Microsoft im Namen als Abo-Produkt erzwingen.
        $plain = $this->article('art-plain', 'Cloud-Arbeitsplatz Premium', '20.60');
        $this->voucher('c-kl', 'RE/2025/0901', '2025-08-07', [['article' => $plain, 'name' => 'Cloud-Arbeitsplatz Premium', 'quantity' => 12, 'unit' => 'Monat', 'net' => '20.60']]);
        $this->assertFalse($isLicense($plain));
        \App\Models\Reselling\ResaleArticleClassification::query()->create(['organization_id' => $org, 'article_ref' => 'lex:' . $plain->id, 'role' => \App\Enums\Reselling\ResaleArticleRole::License]);
        $subscription->forceFill(['article_ref' => 'lex:' . $plain->id, 'label' => 'Cloud-Arbeitsplatz Premium'])->save();
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $subscription->periods()->first()?->fresh()?->status);
    }

    public function test_end_customer_in_closing_text_is_recognised_even_when_shortened(): void {
        $partner = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $haus = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Hof 24 GmbH', 'matchcode' => null]);
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk', 'matchcode' => 'STBK']);
        $subHaus = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $haus->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-09-04']);
        $subZahlenwerk = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $zahlenwerk->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-09-04']);

        // Gleicher Tag, gleiches Produkt — nur der Schlusstext unterscheidet die Endkunden.
        $this->voucher('c-nordlicht', 'RE/2025/1116', '2025-09-04', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 12, 'net' => '12.13']], 'Rechnung Unsere Lieferungen/Leistungen stellen wir Ihnen wie folgt in Rechnung. Vielen Dank für die gute Zusammenarbeit. (M365 Hof24)');
        $this->voucher('c-nordlicht', 'RE/2025/1117', '2025-09-05', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 12, 'net' => '12.13']], 'Rechnung Vielen Dank für die gute Zusammenarbeit. M365 STBK');

        (new LinkProposer)->propose($this->organization);
        $this->assertSame('RE/2025/1116', $subHaus->periods()->first()?->links()->first()?->voucher_number, '„Hof24" trifft „Hof 24 GmbH" ohne Leerzeichen');
        $this->assertSame('RE/2025/1117', $subZahlenwerk->periods()->first()?->links()->first()?->voucher_number, 'Matchcode STBK im Schlusstext');

        $voucher = LexofficeVoucher::query()->where('voucher_number', 'RE/2025/1116')->firstOrFail();
        $this->assertSame('M365 Hof24', $voucher->voucherTextHint());
    }

    public function test_named_line_stays_with_its_end_customer_when_periods_start_together(): void {
        $partner = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $ute = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Ute Mahlwerk']);
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);
        // Ute zuerst angelegt: ihre Periode steht bei gleichem Beginn vorn.
        $subUte = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $ute->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 3]);
        $subZahlenwerk = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $zahlenwerk->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 1]);

        $this->voucher('c-nordlicht', 'RE/2025/0945', '2025-10-26', [
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Endkunde Steuerbüro Zahlenwerk', 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Ute Mahlwerk, 2 Postfächer', 'quantity' => 24, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => '', 'quantity' => 12, 'net' => '3.95'],
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $subZahlenwerk->periods()->first()?->status, 'die Zahlenwerk-Zeile geht nie an Ute');
        $this->assertSame(PeriodStatus::Partial, $subUte->periods()->first()?->status);
        $this->assertSame(1, $result['lines_without_subscription'], 'ohne Nennung bei gleichem Abstand zweier Endkunden: mehrdeutig');
    }

    public function test_partner_invoice_lines_need_the_end_customer_name(): void {
        $partner = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);
        $ute = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Ute Mahlwerk']);
        $subZahlenwerk = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $zahlenwerk->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 1]);
        $subUte = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $ute->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 3]);

        $this->voucher('c-nordlicht', 'RE/2025/0945', '2025-10-26', [
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Endkunde Steuerbüro Zahlenwerk', 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Ute Mahlwerk, 2 Postfächer', 'quantity' => 24, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => '', 'quantity' => 12, 'net' => '3.95'],
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links']);
        $this->assertSame(1, $result['lines_without_subscription'], 'die Zeile ohne Endkunden bleibt liegen: nächste Periode (Zahlenwerk) ist schon gedeckt, im Fenster-Pass zählt ohne Nennung nichts');

        $this->assertSame(PeriodStatus::Billed, $subZahlenwerk->periods()->first()?->status);
        $this->assertSame(PeriodStatus::Partial, $subUte->periods()->first()?->status, '24 von 36 Lizenzmonaten');
        $this->assertSame('24.00', $subUte->periods()->first()?->links()->first()?->months);
    }

    public function test_confirm_waive_reopen_and_manual_link_via_ui(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        $this->voucher('c-kl', 'RE/2025/0820', '2025-10-14', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);

        $this->actingAs($admin)->post(route('finance.resale.periods.propose'))->assertRedirect();
        [$p2025, $p2026] = $subscription->periods()->get();
        $this->assertTrue($p2025->isProposedOnly());

        $voucherSqid = \App\Support\Sqid::encode(\App\Plugins\Lexoffice\Models\LexofficeVoucher::class, LexofficeVoucher::query()->where('voucher_number', 'RE/2025/0820')->value('id'));
        // Vorschläge gelten (Phase 163): auf der Periodenseite nur noch über den Filter „Unbestätigte Vorschläge" (Stichprobe).
        $this->actingAs($admin)->get(route('finance.resale.periods.index', ['status' => 'proposed']))->assertOk()->assertSee('RE/2025/0820')->assertSee(__('resale.link.proposed_hint'))
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
        $voucher = $this->voucher('c-kl', 'RE/2026/0001', '2026-08-30', [
            ['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'description' => 'Nachberechnung', 'quantity' => 12, 'net' => '20.60'],
            ['article' => null, 'name' => 'Business Support', 'description' => 'Stunden', 'quantity' => 3, 'unit' => 'Stunde', 'net' => '60.00'],
        ]);
        $line = $voucher->lines()->first();
        // Der Dialog kennt nur Abo-Positionen mit Lizenzen × Monaten — keine Support-Stunden; verbrauchte sind gesperrt.
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $p2026->sqid))->assertOk()
            ->assertSee('RE/2026/0001')->assertSee('Pos. 1')->assertSee('1 × 12 ' . __('resale.link.months_short'))
            ->assertDontSee('Business Support');
        $this->actingAs($admin)->post(route('finance.resale.periods.link.store', $p2026->sqid), ['line_id' => \App\Support\Sqid::encode(\App\Plugins\Lexoffice\Models\LexofficeVoucherLine::class, $line?->id), 'months' => 12])
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
            'line_id' => \App\Support\Sqid::encode(\App\Plugins\Lexoffice\Models\LexofficeVoucherLine::class, $line?->id),
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
        // Druckhaus-Spree-Muster: drei Verträge desselben Produkts (1 + 1 + 2 Lizenzen), zwei Jahre
        // erst im August 2025 nachberechnet — je Lizenz eine Position „24 Monat".
        $this->travelTo('2025-12-01');
        $customer = $this->customerWithContact('Druckhaus Spree GmbH', 'c-repro');
        $standard = $this->article('art-bs', 'Microsoft 365 Business Standard', '11.70');
        $one = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $standard->id, 'starts_on' => '2024-02-07', 'ends_on' => '2026-02-07', 'status' => 'cancelled']);
        $two = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $standard->id, 'starts_on' => '2024-02-25', 'ends_on' => '2026-02-25', 'status' => 'cancelled']);
        $pair = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $standard->id, 'starts_on' => '2024-02-25', 'ends_on' => '2026-02-25', 'quantity' => 2, 'status' => 'cancelled']);
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

    public function test_line_with_service_period_is_only_offered_to_periods_starting_inside_it(): void {
        // Mahlwerk-Muster (RE/2023/0568): eine Position „24 Monat" mit Leistung ab 26.10. ging an zwei
        // Abos, deren Perioden im August und Anfang Oktober begannen — das Datumsfenster um das
        // Rechnungsdatum ließ es zu. Mit Leistungszeitraum zählt nur er (Toleranz 14 Tage davor).
        $customer = $this->customerWithContact('Mahlwerk GmbH', 'c-mayer');
        $august = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-08-12', 'ends_on' => '2024-08-12', 'status' => 'cancelled']);
        $october = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-10-05', 'ends_on' => '2024-10-05', 'status' => 'cancelled']);
        $this->voucher('c-mayer', 'RE/2023/0568', '2022-11-02', [['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 24, 'net' => '3.95']], '', '2022-10-26', '2024-10-25');

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(0, $result['linked'], 'kein Abo beginnt im Leistungszeitraum');
        $this->assertSame(1, $result['lines_without_subscription'], 'die Position bleibt „ohne Abo"');
        foreach ([$august, $october] as $subscription) {
            $this->assertSame([PeriodStatus::Open, PeriodStatus::Open], $subscription->periods()->get()->pluck('status')->all());
        }

        // Das fehlende Abo ab 26.10. („Abo aus Position anlegen") nimmt die Position für beide Jahre.
        $fitting = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-10-26', 'ends_on' => '2024-10-26', 'status' => 'cancelled']);
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['linked']);
        $this->assertSame(0, $result['lines_without_subscription']);
        $this->assertSame(['12.00', '12.00'], $fitting->periods()->get()->map(static fn($p) => $p->links()->first()?->months)->all());
        $this->assertSame(0, ResalePeriodLink::query()->whereIn('subscription_id', [$august->id, $october->id])->count(), 'die früheren Abos bleiben unberührt');
    }

    public function test_domain_subscription_is_covered_by_lines_that_name_the_domain(): void {
        // lastkraft-logistik.de (Prod 2026-10-27): Artikel „Top-Level-Domain“ ist keine Lizenz, der Domainname
        // steht nur im Positionstext — das Register sah 13 offene Jahre, Lexoffice hatte seit 2016 berechnet.
        $customer = $this->customerWithContact('Lastkraft Transporte', 'c-lastkraft');
        $tld = $this->article('art-tld', 'Top-Level-Domain', '8.55');
        $domain = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'lastkraft-logistik.de', 'label' => 'lastkraft-logistik.de', 'customer_id' => $customer->id, 'starts_on' => '2014-03-27']);
        $other = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'lastkraft-transporte.de', 'label' => 'lastkraft-transporte.de', 'customer_id' => $customer->id, 'starts_on' => '2014-03-27']);
        $exchange = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2024-01-01']);
        $this->voucher('c-lastkraft', 'RE/2017/0019', '2017-12-02', [['article' => $tld, 'name' => '[SGIT-IT-DOBB-00001DE] - DE-Domain', 'description' => 'Domain Jahresgebühr - DE => lastkraft-logistik.de => 27.03.2016 - 27.03.2018', 'quantity' => 2, 'unit' => 'Jahr', 'net' => '4.50']], '', '2016-03-27', '2018-03-27');
        $this->voucher('c-lastkraft', 'RE/2023/0572', '2023-12-01', [['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.de-Domain inkl. Verwaltung (lastkraft-logistik.de)', 'quantity' => 6, 'unit' => 'Jahr', 'net' => '8.55']], '', '2018-03-27', '2024-03-26');
        foreach (['RE/2024/0624' => '2024', 'RE/2025/0826' => '2025', 'RE/2026/1036' => '2026'] as $number => $year) {
            $this->voucher('c-lastkraft', $number, $year . '-03-27', [['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.de-Domain inkl. Verwaltung (lastkraft-logistik.de)', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '8.55']], '', $year . '-03-27', ($year + 1) . '-03-26');
        }

        $result = (new LinkProposer)->propose($this->organization);

        $periods = $domain->periods()->orderBy('starts_on')->get();
        $this->assertCount(13, $periods, '2014 bis 2026');
        $this->assertSame([PeriodStatus::Open, PeriodStatus::Open], [$periods[0]->status, $periods[1]->status], '2014 und 2015 liegen vor der ersten gespiegelten Rechnung');
        $this->assertSame(array_fill(0, 11, PeriodStatus::Billed), $periods->slice(2)->pluck('status')->values()->all(), '2016 bis 2026 sind berechnet');
        $this->assertSame(['RE/2017/0019', 'RE/2017/0019', 'RE/2023/0572', 'RE/2023/0572', 'RE/2023/0572', 'RE/2023/0572', 'RE/2023/0572', 'RE/2023/0572', 'RE/2024/0624', 'RE/2025/0826', 'RE/2026/1036'], $periods->slice(2)->map(static fn(ResalePeriod $p) => $p->links()->first()?->voucher_number)->values()->all());
        foreach ($periods->slice(2) as $period) {
            $this->assertSame('12.00', $period->links()->first()?->months, 'ein Jahr je Periode, auch aus „6 Jahr“');
        }
        $this->assertSame(11, $result['linked']);
        $this->assertSame(0, ResalePeriodLink::query()->whereIn('subscription_id', [$other->id, $exchange->id])->count(), 'die andere Domain und das Lizenz-Abo bekommen nichts von den Domainpositionen');
        $this->assertSame(0, $result['lines_without_subscription'], 'Domainpositionen zählen nicht als Lizenzpositionen ohne Abo');
    }

    public function test_collective_domain_invoice_uses_the_period_in_the_line_text_and_idn_names(): void {
        // Steuerberater Zahlenwerk (Prod 2026-10-29): RE/2017/0017 trägt neun Domains mit je eigenem Zeitraum im Text,
        // der Belegzeitraum umspannt alle; RE/2023/0575 berechnet reisewelt-beispiel.de „8 Jahr“ ab 08.09.2016, obwohl das
        // Abo (Registrierung) am 27.01.2017 beginnt; die IDN-Domain heißt in Lexoffice „onlinehändler-…“.
        $customer = $this->customerWithContact('Steuerberater C. Zahlenwerk', 'c-zahlenwerk');
        $tld = $this->article('art-tld', 'Top-Level-Domain', '8.55');
        $world = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'reisewelt-beispiel.de', 'label' => 'reisewelt-beispiel.de', 'customer_id' => $customer->id, 'starts_on' => '2017-01-27']);
        $ascii = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'lieferschwelle-beispiel.de', 'label' => 'lieferschwelle-beispiel.de', 'customer_id' => $customer->id, 'starts_on' => '2017-09-26']);
        $idn = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'xn--onlinehndler-beispiel-c2b.de', 'label' => 'xn--onlinehndler-beispiel-c2b.de', 'customer_id' => $customer->id, 'starts_on' => '2017-09-26']);
        $this->voucher('c-zahlenwerk', 'RE/2017/0017', '2017-12-02', [
            ['article' => $tld, 'name' => '[SGIT-IT-DOBB-00001DE] - DE-Domain', 'description' => 'Domain Jahresgebühr - DE => reisewelt-beispiel.de => 27.01.2017 - 27.01.2018', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '4.50'],
            ['article' => $tld, 'name' => '[SGIT-IT-DOBB-00001DE] - DE-Domain', 'description' => 'Domain Jahresgebühr - DE => lieferschwelle-beispiel.de => 26.09.2017 - 26.09.2018', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '4.50'],
            ['article' => $tld, 'name' => '[SGIT-IT-DOBB-00001DE] - DE-Domain', 'description' => 'Domain Jahresgebühr - DE => onlinehändler-beispiel.de => 26.09.2017 - 26.09.2018', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '4.50'],
            ['article' => null, 'name' => 'DNS-Zoneneintragung', 'quantity' => 3, 'unit' => 'Jahr', 'net' => '0.00'],
        ], '', '2017-01-27', '2018-10-07');
        $this->voucher('c-zahlenwerk', 'RE/2023/0575', '2023-12-03', [['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.de-Domain inkl. Verwaltung (reisewelt-beispiel.de)', 'quantity' => 8, 'unit' => 'Jahr', 'net' => '8.55']], '', '2016-09-08', '2024-09-07');

        $result = (new LinkProposer)->propose($this->organization);

        $worldPeriods = $world->periods()->orderBy('starts_on')->get();
        $this->assertCount(10, $worldPeriods, '2017 bis 2026');
        $this->assertSame(['RE/2017/0017', 'RE/2023/0575', 'RE/2023/0575', 'RE/2023/0575', 'RE/2023/0575', 'RE/2023/0575', 'RE/2023/0575', 'RE/2023/0575', null, null], $worldPeriods->map(static fn(ResalePeriod $p) => $p->links()->first()?->voucher_number)->all(), 'Zeitraum aus dem Positionstext deckt 2017, die 8-Jahres-Position mit verschobenem Jahrestag 2018 bis 2024');
        $this->assertSame(PeriodStatus::Billed, $ascii->periods()->orderBy('starts_on')->first()?->status, 'lieferschwelle-beispiel.de 2017 aus der Sammelrechnung');
        $this->assertSame(1, $ascii->periods()->where('status', PeriodStatus::Billed->value)->count(), 'danach nie wieder berechnet — die Lücke bleibt sichtbar');
        $this->assertSame(PeriodStatus::Billed, $idn->periods()->orderBy('starts_on')->first()?->status, 'die IDN-Domain über ihren Unicode-Namen im Text');
        $this->assertSame(1, $idn->periods()->where('status', PeriodStatus::Billed->value)->count());
        $this->assertSame(10, $result['linked']);
        $this->assertSame(0, $result['lines_without_subscription']);
    }

    public function test_retroactive_four_year_line_fills_the_oldest_periods_of_its_contract(): void {
        // Marina-Vulkan-Muster: vier Jahre eines 1er-Vertrags im August 2025 als „48 Monat" nachberechnet,
        // daneben ein zweiter Vertrag ab Mai 2025 mit einer 12er-Position auf derselben Rechnung.
        $this->travelTo('2026-09-08');
        $customer = $this->customerWithContact('Marina Seestern Werft', 'c-mv');
        $old = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2022-04-01', 'ends_on' => '2026-04-01', 'status' => 'cancelled']);
        $new = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-05-06', 'ends_on' => '2026-05-06', 'status' => 'cancelled']);
        $this->voucher('c-mv', 'RE/2025/0902', '2025-08-22', [['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 48, 'net' => '3.95']]);
        $this->voucher('c-mv', 'RE/2025/0903', '2025-08-22', [['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 12, 'net' => '3.95']]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(5, $result['linked'], 'vier alte Perioden plus die neue');
        $this->assertSame(['RE/2025/0902', 'RE/2025/0902', 'RE/2025/0902', 'RE/2025/0902'], $old->periods()->get()->map(static fn($p) => $p->links()->first()?->voucher_number)->all(), '48 Monate = die vier Jahre des alten Vertrags, auch außerhalb des 730-Tage-Fensters');
        $this->assertSame('RE/2025/0903', $new->periods()->first()?->links()->first()?->voucher_number, 'die passende 12er-Position vor der 48er');
        $this->assertSame(0, $result['lines_without_subscription']);
    }

    public function test_invoice_inside_the_successors_period_is_not_taken_by_the_old_contract(): void {
        // Druckhaus-Spree-Muster: Telekom-Vertrag 2024/2025 noch offen, der Quality-Hosting-Nachfolger ab Februar 2026
        // wird pünktlich berechnet — diese Rechnung gehört dem Nachfolger, nicht den alten Perioden im Fenster.
        $this->travelTo('2026-09-08');
        $customer = $this->customerWithContact('Druckhaus Spree GmbH', 'c-repro');
        $standard = $this->article('art-bs', 'Microsoft 365 Business Standard', '11.70');
        $telekom = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $standard->id, 'starts_on' => '2024-02-25', 'ends_on' => '2026-02-25', 'status' => 'superseded']);
        $qh = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'provider' => 'qualityhosting', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $standard->id, 'starts_on' => '2026-02-25']);
        $this->voucher('c-repro', 'RE/2026/1026', '2026-02-25', [['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 12, 'net' => '11.70']]);
        $this->voucher('c-repro', 'RE/2025/0896', '2025-08-21', [['article' => $standard, 'name' => 'Microsoft 365 Business Standard', 'quantity' => 24, 'net' => '11.70']]);

        (new LinkProposer)->propose($this->organization);
        $this->assertSame('RE/2026/1026', $qh->periods()->first()?->links()->first()?->voucher_number, 'Rechnung in der Laufzeit des Nachfolgers bleibt beim Nachfolger');
        $this->assertSame(['RE/2025/0896', 'RE/2025/0896'], $telekom->periods()->get()->map(static fn($p) => $p->links()->first()?->voucher_number)->all(), 'die 24 Monate decken beide alten Jahre');
    }

    public function test_periods_of_subscriptions_that_lost_their_holder_fall_back_to_open_after_the_run(): void {
        // Review 2026-09-10 (B2): Vorschläge werden org-weit gelöscht, bewertet werden nur Abos mit Halter.
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        // Rechnung zwei Monate nach Beginn: ein Vorschlag, kein sicherer Treffer (Feature 185) — sonst wäre die Periode entschieden.
        $this->voucher('c-kl', 'RE/2025/0820', '2025-10-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);
        (new LinkProposer)->propose($this->organization);
        $period = $subscription->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $this->assertNull($period->decided_at);

        // Halter weg (Posteingang): der nächste Lauf räumt den Vorschlag ab — und den Status.
        $subscription->forceFill(['customer_id' => null])->save();
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(0, ResalePeriodLink::query()->count());
        $this->assertSame(PeriodStatus::Open, $period->fresh()?->status, 'kein Bezug mehr → offen, nicht „berechnet"');

        // Eigener Bestand ebenso.
        $subscription->forceFill(['customer_id' => $customer->id])->save();
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);
        $subscription->forceFill(['customer_id' => null, 'is_own_holding' => true])->save();
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Open, $period->fresh()?->status);
    }

    public function test_local_invoice_draft_link_counts_as_coverage_and_is_not_doubled(): void {
        // Review 2026-09-10 (B3): der Bezug auf eine lokale Rechnungsposition (InvoiceItem) ist ein
        // Vorschlag ohne Spiegelposition — er bleibt beim Lauf stehen und zählt als Deckung.
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        $period = $subscription->periods()->firstOrFail();
        ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $subscription->id,
            'linkable_type' => (new \App\Models\Invoicing\InvoiceItem)->getMorphClass(), 'linkable_id' => 4711,
            'voucher_number' => 'RE-2026-0001', 'voucher_date' => '2026-09-01', 'quantity' => 1, 'months' => 12, 'amount' => '247.20', 'currency' => 'EUR', 'origin' => LinkOrigin::Proposed,
        ]);
        $period->forceFill(['status' => PeriodStatus::Billed])->save();
        // Eine passende Spiegelposition existiert ebenfalls — sie darf die Periode nicht ein zweites Mal decken.
        $this->voucher('c-kl', 'RE/2025/0820', '2025-08-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);

        $result = (new LinkProposer)->propose($this->organization);
        $period->refresh();
        $this->assertSame(PeriodStatus::Billed, $period->status, 'lokaler Entwurfsbezug deckt die Periode');
        $this->assertSame(1, $period->links()->count(), 'kein zusätzlicher Spiegel-Bezug');
        $this->assertSame(MorphMap::alias(\App\Models\Invoicing\InvoiceItem::class), $period->links()->first()?->linkable_type);
        $this->assertSame(1, $result['lines_without_subscription'], 'die Spiegelposition bleibt frei — und sichtbar');
    }

    public function test_multi_year_invoice_before_the_date_prefilter_still_covers_the_latest_period(): void {
        // Review 2026-09-10 (B5): 36-Monats-Rechnung vom Januar 2024, die 2024er- und 2025er-Periode bestätigt,
        // die 2026er offen — der Vorfilter nach Belegdatum (ab 90 Tage vor der ältesten offenen Periode) fand sie nie.
        $customer = $this->customerWithContact('Druckhaus Spree GmbH', 'c-repro');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2024-01-01', 'ends_on' => '2027-01-01', 'status' => 'cancelled']);
        $this->assertSame(3, $subscription->periods()->count());
        $this->voucher('c-repro', 'RE/2024/0001', '2024-01-01', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 36, 'net' => '20.60']], '', '2024-01-01', '2026-12-31');
        (new LinkProposer)->propose($this->organization);
        [$p2024, $p2025, $p2026] = $subscription->periods()->get()->all();
        $this->assertSame([PeriodStatus::Billed, PeriodStatus::Billed, PeriodStatus::Billed], [$p2024->status, $p2025->status, $p2026->status]);

        // 2024/2025 bestätigen, 2026 wieder öffnen (Bezug lösen) — der nächste Lauf muss die alte Rechnung noch sehen.
        foreach ([$p2024, $p2025] as $decided) {
            $decided->links()->update(['origin' => LinkOrigin::Confirmed->value]);
            $decided->forceFill(['decided_at' => now()])->save();
        }
        $p2026->links()->delete();
        $p2026->forceFill(['status' => PeriodStatus::Open])->save();

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $this->assertSame(PeriodStatus::Billed, $p2026->fresh()?->status, 'Leistungszeitraum bis Ende 2026 holt die Rechnung in den Lauf');
        $this->assertSame('12.00', $p2026->links()->first()?->months);
    }

    public function test_voided_invoice_releases_confirmed_links_and_reopens_the_period(): void {
        // Review 2026-09-10 (A3): Storno hebt den Bezug auf — die Spur bleibt (0 Monate, Hinweis), die Periode
        // wird aus der Restdeckung bewertet und ist wieder offen für den Lauf (Ersatzrechnung).
        $customer = $this->customerWithContact('Ökotherm - HLSK GmbH', 'c-hlsk');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        $voucher = $this->voucher('c-hlsk', 'RE/2025/0271', '2025-08-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);
        (new LinkProposer)->propose($this->organization);
        $period = $subscription->periods()->firstOrFail();
        $period->links()->update(['origin' => LinkOrigin::Confirmed->value, 'confirmed_at' => now()]);
        $period->forceFill(['decided_at' => now(), 'note' => 'telefonisch geklärt'])->save();

        $voucher->forceFill(['voucher_status' => 'voided'])->save();
        (new LinkProposer)->propose($this->organization);

        $period->refresh();
        $this->assertSame(PeriodStatus::Open, $period->status, 'stornierte Rechnung deckt nichts');
        $this->assertNull($period->decided_at, 'Entscheidung galt der stornierten Rechnung');
        $this->assertSame('telefonisch geklärt', $period->note, 'Bemerkung der Periode bleibt');
        $link = $period->links()->firstOrFail();
        $this->assertSame(LinkOrigin::Confirmed, $link->origin, 'bestätigter Bezug bleibt als Spur');
        $this->assertSame('0.00', $link->months);
        $this->assertSame('0.00', $link->amount?->getAmount());
        $this->assertStringContainsString('1 × 12 ' . __('resale.link.months_short'), (string) $link->note, 'Original-Monate im Hinweis');

        // Ersatzrechnung: der nächste Lauf deckt die Periode wieder; die Spur wird nicht erneut markiert.
        $this->voucher('c-hlsk', 'RE/2025/0272', '2025-08-20', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $period->fresh()?->status);
        $this->assertSame('RE/2025/0272', $period->links()->whereIn('origin', [LinkOrigin::Proposed->value, LinkOrigin::Auto->value])->first()?->voucher_number);
        $this->assertSame(1, substr_count((string) $link->fresh()?->note, (string) __('resale.link.note_voided', ['months' => '1 × 12 ' . __('resale.link.months_short')])));
    }

    public function test_run_is_locked_per_organization(): void {
        $lock = \Illuminate\Support\Facades\Cache::lock('resale:propose:' . $this->organization->id, 60);
        $this->assertTrue($lock->get());
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage((string) __('resale.propose.locked'));
            (new LinkProposer)->propose($this->organization);
        } finally {
            $lock->release();
        }
    }

    public function test_second_run_without_changes_reproduces_the_same_links(): void {
        // Review 2026-09-10 (G, Idempotenz): der Lauf löscht alle Vorschläge und baut sie neu — ohne Änderung
        // am Bestand müssen Perioden, Monate, Mengen und Beträge exakt gleich herauskommen (IDs dürfen wechseln).
        $customer = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $premium = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2024-08-05', 'quantity' => 2, 'sale_unit_price' => '247.20']);
        $exchange = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-01-01', 'sale_unit_price' => '47.40']);
        $this->assertSame(3, $premium->periods()->count());
        $this->assertSame(2, $exchange->periods()->count());
        $this->voucher('c-kl', 'RE/2024/0810', '2024-08-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 24, 'net' => '20.60']]);
        $this->voucher('c-kl', 'RE/2025/0812', '2025-08-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 24, 'net' => '20.60']]);
        $this->voucher('c-kl', 'RE/2026/0814', '2026-08-06', [['article' => $this->premium, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 12, 'net' => '20.60']]);
        $this->voucher('c-kl', 'RE/2025/0102', '2025-01-02', [['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 12, 'net' => '3.95']]);
        $this->voucher('c-kl', 'RE/2026/0103', '2026-01-02', [['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'quantity' => 12, 'net' => '3.95']]);

        $snapshot = function (): array {
            return ResalePeriodLink::query()->with('period')
                ->get()
                ->map(static fn(ResalePeriodLink $l): array => [
                    'period' => $l->period?->starts_on->toDateString(), 'subscription' => $l->subscription_id, 'voucher' => $l->voucher_number,
                    'line' => $l->linkable_id, 'months' => $l->months, 'quantity' => $l->quantity, 'amount' => $l->amount?->getAmount(),
                    'currency' => $l->currency->value, 'origin' => $l->origin->value,
                ])
                ->sortBy(static fn(array $row): string => $row['period'] . '|' . $row['subscription'] . '|' . $row['voucher'])
                ->values()
                ->all();
        };
        $statuses = static fn(): array => ResalePeriod::query()->orderBy('subscription_id')->orderBy('starts_on')->get()->map(static fn(ResalePeriod $p): array => [$p->status->value, $p->decided_at?->toDateTimeString()])->all();

        $first = (new LinkProposer)->propose($this->organization);
        $this->assertSame(['periods' => 5, 'linked' => 4, 'partial' => 1, 'links' => 5, 'lines_without_subscription' => 0, 'auto_confirmed' => 2], $first);
        $firstLinks = $snapshot();
        $firstStatuses = $statuses();
        $firstIds = ResalePeriodLink::query()->pluck('id')->all();
        $this->assertCount(5, $firstLinks);
        $this->assertSame(['2024-08-05', '2025-08-05', '2026-08-05'], array_column(array_values(array_filter($firstLinks, static fn(array $r): bool => $r['subscription'] === $premium->id)), 'period'));
        $this->assertSame(['24.00', '24.00', '12.00'], array_column(array_values(array_filter($firstLinks, static fn(array $r): bool => $r['subscription'] === $premium->id)), 'months'));
        $this->assertSame([PeriodStatus::Billed, PeriodStatus::Billed, PeriodStatus::Partial], $premium->periods()->get()->map(static fn(ResalePeriod $p) => $p->status)->all(), '2026: 12 von 24 Lizenzmonaten');

        // Feature 185: zwei sichere Treffer sind entschieden und werden im zweiten Lauf nicht mehr bewertet — der Rest bleibt identisch.
        $second = (new LinkProposer)->propose($this->organization);
        $this->assertSame(['periods' => 3, 'linked' => 2, 'partial' => 1, 'links' => 3, 'lines_without_subscription' => 0, 'auto_confirmed' => 0], $second, 'zweiter Lauf ohne die entschiedenen Perioden');
        $this->assertSame($firstLinks, $snapshot(), 'Perioden, Monate, Mengen, Beträge identisch');
        $this->assertSame($firstStatuses, $statuses(), 'Status und Entscheidung identisch');
        $this->assertCount(2, array_intersect($firstIds, ResalePeriodLink::query()->pluck('id')->all()), 'Vorschläge werden ersetzt, automatisch bestätigte Bezüge bleiben');
        $this->assertSame(5, ResalePeriodLink::query()->count());
        $this->assertSame(2, ResalePeriod::query()->whereNotNull('decided_at')->whereNull('decided_by_user_id')->count(), 'nur die sicheren Treffer sind entschieden, ohne Nutzer');

        // Dritter Lauf mit einem anderen Stichtag im selben Bestand: ebenfalls gleich.
        (new LinkProposer)->propose($this->organization, \Carbon\CarbonImmutable::parse('2026-09-04'));
        $this->assertSame($firstLinks, $snapshot());
    }

    public function test_sharing_rule_applies_when_only_one_end_customer_subscription_carries_the_article(): void {
        // Review 2026-09-10 (G, Sharing-Regel mit gemischten Produktschlüsseln): zwei Endkunden desselben
        // Partners mit demselben Produkt — ein Abo trägt den Lexoffice-Artikel, das andere nur den Namen.
        // `ResaleSubscription::productKey()` ist die EINE Regel für Vorschlagslauf, Abgleich und Bericht:
        // mit den Artikelnamen der Rechnungen liefert es für beide `art:<exchange>`. Die Position ohne
        // Endkundennennung darf deshalb nicht still beim zweiten Endkunden landen (wie in
        // test_partner_invoice_lines_need_the_end_customer_name, nur mit gemischten Schlüsseln).
        $partner = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk', 'company' => 'Steuerbüro Zahlenwerk', 'matchcode' => null]);
        $ute = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Ute Mahlwerk', 'company' => 'Ute Mahlwerk', 'matchcode' => null]);
        $subZahlenwerk = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $zahlenwerk->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01', 'quantity' => 1]);
        $subUte = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $ute->id, 'article_ref' => null, 'starts_on' => '2025-10-01', 'quantity' => 3]);
        $this->assertNotSame($subZahlenwerk->productKey(), $subUte->productKey(), 'ohne Artikelnamen: art:… gegen name:…');
        $this->assertSame($subZahlenwerk->productKey(), $subUte->productKey(['lex:' . $this->exchange->id => $this->exchange->name]), 'mit Artikelnamen dasselbe Produkt');

        $this->voucher('c-nordlicht', 'RE/2025/0945', '2025-10-26', [
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Endkunde Steuerbüro Zahlenwerk', 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => 'Ute Mahlwerk, 2 Postfächer', 'quantity' => 24, 'net' => '3.95'],
            ['article' => $this->exchange, 'name' => 'Exchange Online (Plan 1)', 'description' => '', 'quantity' => 12, 'net' => '3.95'],
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(2, $result['links'], 'nur die beiden genannten Positionen');
        $this->assertSame(1, $result['lines_without_subscription'], 'die Zeile ohne Endkunden bleibt liegen — auch bei gemischten Produktschlüsseln');
        $this->assertSame(PeriodStatus::Billed, $subZahlenwerk->periods()->first()?->status);
        $this->assertSame('RE/2025/0945', $subZahlenwerk->periods()->first()?->links()->first()?->voucher_number);
        $this->assertSame(PeriodStatus::Partial, $subUte->periods()->first()?->status, '24 von 36 Lizenzmonaten; die ungenannte 12er-Zeile gehört nicht sicher Ute');
        $this->assertSame(['24.00'], $subUte->periods()->first()?->links()->pluck('months')->all());
    }

    public function test_links_carry_the_currency_of_the_invoice_line_and_the_run_does_not_filter_by_currency(): void {
        // Review 2026-09-10 (G, Währung ≠ EUR), Verhalten aus dem Code: Deckung zählt in Lizenzmonaten, der
        // Bezug übernimmt Währung und Betrag der Position (unit_net × Einheiten) — der Lauf vergleicht keine Währungen.
        $customer = $this->customerWithContact('Helvetia Treuhand AG', 'c-ch');
        $chf = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05', 'currency' => 'CHF', 'sale_unit_price' => '260.40']);
        $this->assertSame('CHF', $chf->periods()->first()?->currency->value);
        $this->assertSame('260.40', $chf->periods()->first()?->expected_sale?->getAmount());

        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-chf', 'contact_external_id' => 'c-ch', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2025/0700', 'voucher_date' => '2025-08-06', 'total_amount' => 260.40, 'currency' => 'CHF', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $this->premium->external_id, 'lexoffice_article_id' => $this->premium->id, 'name' => 'Microsoft 365 Business Premium',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '21.70', 'total_net' => '260.40', 'tax_rate' => 8.1, 'currency' => 'CHF',
        ]);

        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links']);
        $link = $chf->periods()->first()?->links()->firstOrFail();
        $this->assertSame(\CommonToolkit\Enums\CurrencyCode::SwissFranc, $link?->currency, 'Währung der Position');
        $this->assertSame('260.40', $link?->amount?->getAmount(), '12 × 21,70 CHF');
        $this->assertSame(\CommonToolkit\Enums\CurrencyCode::SwissFranc, $link?->amount?->getCurrency());
        $this->assertSame(PeriodStatus::Billed, $chf->periods()->first()?->status);

        // Abweichende Währung zwischen Abo (EUR) und Rechnung (USD): der Lauf deckt trotzdem — Monate sind
        // währungsfrei; der Bezug trägt USD, die Periode bleibt EUR. Kein Filter, kein Fehler.
        $eur = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-08-05', 'sale_unit_price' => '47.40']);
        $usd = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-usd', 'contact_external_id' => 'c-ch', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2025/0701', 'voucher_date' => '2025-08-07', 'total_amount' => 51.60, 'currency' => 'USD', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $usd->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $this->exchange->external_id, 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '4.30', 'total_net' => '51.60', 'tax_rate' => 0, 'currency' => 'USD',
        ]);
        $result = (new LinkProposer)->propose($this->organization);
        $this->assertSame(1, $result['links'], 'die CHF-Periode ist seit dem ersten Lauf entschieden (sicherer Treffer), nur die EUR-Periode ist neu');
        $mixed = $eur->periods()->first()?->links()->firstOrFail();
        $this->assertSame(\CommonToolkit\Enums\CurrencyCode::USDollar, $mixed?->currency);
        $this->assertSame('51.60', $mixed?->amount?->getAmount());
        $this->assertSame('EUR', $eur->periods()->first()?->currency->value, 'Periode behält die Abo-Währung');
        $this->assertSame(PeriodStatus::Billed, $eur->periods()->first()?->status);
    }

    /**
     * Lexoffice-Kontakte des Rechnungsempfängers eines Abos — seit der Spiegel-
     * Abstraktion Plugin-Sache (`LexofficeContactMap`), der Kern kennt nur den Kunden.
     *
     * @return list<string>
     */
    private function contactsFor(ResaleSubscription $subscription): array {
        $billedTo = $subscription->billedTo();

        return $billedTo === null ? [] : $this->contactsForCustomer($billedTo);
    }

    /** @return list<string> */
    private function contactsForCustomer(Customer $customer): array {
        return \App\Plugins\Lexoffice\Services\LexofficeContactMap::forCustomer($customer)->byCustomer($customer->id);
    }

    public function test_contacts_for_returns_the_lexoffice_contacts_of_the_billed_customer(): void {
        // Review 2026-09-10 (G): Dialog-Helfer — Kunde direkt, Partner des Fremdkunden, sonst nichts.
        // Ein Lexoffice-Kontakt je Kunde (Unique `extref_unique` auf Plugin/Typ/Referenz).
        $partner = $this->customerWithContact('Nordlicht Systems GmbH', 'c-nordlicht');
        $direct = $this->customerWithContact('Badhaus Bäder GmbH', 'c-kl');
        $withoutContact = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ohne Kontakt GmbH']);
        $zahlenwerk = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Zahlenwerk']);

        $viaCustomer = $this->subscription(['label' => 'Microsoft 365 Business Premium', 'customer_id' => $direct->id, 'article_ref' => 'lex:' . $this->premium->id, 'starts_on' => '2025-08-05']);
        $this->assertSame(['c-kl'], $this->contactsFor($viaCustomer));

        $viaPartner = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'foreign_customer_id' => $zahlenwerk->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01']);
        $this->assertSame(['c-nordlicht'], $this->contactsFor($viaPartner), 'Fremdkunde → Kontakt des Partners (Rechnungsempfänger)');
        $this->assertSame(['c-nordlicht'], $this->contactsForCustomer($partner));

        $this->assertSame([], $this->contactsFor($this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $withoutContact->id, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01'])), 'Kunde ohne Lexoffice-Kontakt');
        $this->assertSame([], $this->contactsFor($this->subscription(['label' => 'Exchange Online (Plan 1)', 'is_own_holding' => true, 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01'])), 'eigener Bestand hat keinen Rechnungsempfänger');
        $this->assertSame([], $this->contactsFor($this->subscription(['label' => 'Exchange Online (Plan 1)', 'article_ref' => 'lex:' . $this->exchange->id, 'starts_on' => '2025-10-01'])), 'ohne Halter (Inbox)');

        // Fremder Mandant mit demselben Kontaktschlüssel: jeder sieht nur seine eigene Verknüpfung.
        $otherOrg = \App\Models\Platform\Organization::factory()->create();
        $stranger = Customer::factory()->create(['organization_id' => $otherOrg->id, 'name' => 'Fremd GmbH']);
        ExternalReference::create([
            'organization_id' => $otherOrg->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-kl', 'referenceable_type' => $stranger->getMorphClass(), 'referenceable_id' => $stranger->getKey(),
        ]);
        $this->assertSame(['c-kl'], $this->contactsForCustomer($stranger));
        $this->assertSame(['c-kl'], $this->contactsFor($viaCustomer));
    }

    public function test_domain_typo_in_the_invoice_text_is_proposed_but_not_auto_confirmed(): void {
        // Pilot 2026-10-30: „.eu-Domain inkl. Verwaltung (livescroing.eu)“ — Buchstabendreher im handgeschriebenen Text.
        $customer = $this->customerWithContact('Christian Zahlenwerk', 'c-privat');
        $tld = $this->article('art-tld', 'Top-Level-Domain', '8.55');
        $eu = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'livescoring-beispiel.eu', 'label' => 'livescoring-beispiel.eu', 'customer_id' => $customer->id, 'starts_on' => '2023-04-04']);
        $me = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'livescoring-beispiel.me', 'label' => 'livescoring-beispiel.me', 'customer_id' => $customer->id, 'starts_on' => '2023-04-04']);
        $plain = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'zeitreich-beispiel.net', 'label' => 'zeitreich-beispiel.net', 'customer_id' => $customer->id, 'starts_on' => '2023-04-04']);
        $hyphen = $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'external_id' => 'zeit-reich-beispiel.net', 'label' => 'zeit-reich-beispiel.net', 'customer_id' => $customer->id, 'starts_on' => '2023-04-04']);
        $this->voucher('c-privat', 'RE/2023/0578', '2023-12-03', [
            ['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.eu-Domain inkl. Verwaltung (livescroing-beispiel.eu)', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '24.33'],
            ['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.me-Domain inkl. Verwaltung (livescoring-beispiel.me)', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '32.73'],
            ['article' => $tld, 'name' => 'Top-Level-Domain', 'description' => '.net-Domain inkl. Verwaltung (zeitreich-beispiel.net)', 'quantity' => 1, 'unit' => 'Jahr', 'net' => '21.85'],
        ], '', '2023-04-04', '2024-04-03');

        (new LinkProposer)->propose($this->organization);

        $first = static fn(ResaleSubscription $s) => $s->periods()->orderBy('starts_on')->first()?->links()->first();
        $this->assertSame('RE/2023/0578', $first($eu)?->voucher_number, 'Tippfehler im Text: trotzdem vorgeschlagen');
        $this->assertSame(LinkOrigin::Proposed, $first($eu)?->origin, 'aber nicht automatisch bestätigt');
        $this->assertSame(__('resale.link.note_domain_typo', ['written' => 'livescroing-beispiel.eu', 'domain' => 'livescoring-beispiel.eu']), $first($eu)?->note, 'der geschriebene Name steht als Notiz am Bezug');
        $this->assertNull($first($me)?->note, 'exakte Nennung ohne Notiz');
        $this->assertSame(LinkOrigin::Auto, $first($me)?->origin, 'exakte Nennung wird bestätigt');
        $this->assertSame(LinkOrigin::Auto, $first($plain)?->origin, 'exakt genannte Domain');
        $this->assertNull($first($hyphen), 'die eine Bindestrich entfernte Schwester-Domain bekommt nichts — der Text nennt exakt eine andere bekannte Domain');
    }
}
