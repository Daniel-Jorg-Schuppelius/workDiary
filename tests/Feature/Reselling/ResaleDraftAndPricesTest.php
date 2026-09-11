<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDraftAndPricesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\PeriodStatus;
use App\Models\{Article, Customer, ExternalReference, ForeignCustomer, LexofficeArticle};
use App\Models\Reselling\{ResalePriceEntry, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\PeriodPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Preisprüfung (MVP-766, aus 151 übernommen) und Lexoffice-Rechnungsentwurf
 * aus offenen Perioden (MVP-764).
 */
class ResaleDraftAndPricesTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    public function test_price_check_flags_products(): void {
        $admin = $this->orgAdmin();
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        foreach ([['247.20', '187.92'], ['126.00', '244.76']] as [$sale, $purchase]) {
            ResaleSubscription::query()->create([
                'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium', 'lexoffice_article_id' => $article->id,
                'customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
                'purchase_unit_price' => $purchase, 'sale_unit_price' => $sale, 'currency' => 'EUR', 'status' => 'active',
            ]);
        }
        ResalePriceEntry::create([
            'organization_id' => $this->organization->id, 'provider' => 'qualityhosting', 'product' => 'Microsoft 365 Business Premium', 'term_months' => 12, 'interval' => 'yearly',
            'valid_from' => '2026-09-01', 'purchase_unit_price' => '187.92', 'list_unit_price' => '228.72', 'currency' => 'EUR',
        ]);
        // Lokaler Artikel mit Verkaufspreis (Review 2026-09-11): weicht der Abo-Preis ab, meldet die Prüfung es.
        $exchange = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Exchange Online (Plan 1)', 'number' => 'EXO1', 'default_sale_price' => '60.00', 'currency' => 'EUR']);
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'manual', 'label' => 'Exchange Online (Plan 1)', 'article_id' => $exchange->id,
            'customer_id' => $customer->id, 'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '40.00', 'sale_unit_price' => '47.40', 'currency' => 'EUR', 'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('finance.resale.prices'))
            ->assertOk()
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('228,72 €')
            ->assertSee(__('resale.prices.flag.contract_above_catalog'), false)
            ->assertSee('126,00 €')
            ->assertSee(__('resale.prices.flag.article_price_differs'), false)
            ->assertDontSee(__('resale.prices.flag.below_purchase'), false);
        $rows = app(\App\Services\Reselling\Register\ResalePriceCheck::class)->build(\App\Models\Reselling\ResalePeriod::today())['rows'];
        $row = collect($rows)->firstWhere('label', 'Exchange Online (Plan 1)');
        $this->assertNotNull($row);
        $this->assertSame(60.0, $row['article_sale']);
        $this->assertContains('article_price_differs', $row['flags']);
        $premium = collect($rows)->firstWhere('label', 'Microsoft 365 Business Premium');
        $this->assertNull($premium['article_sale'] ?? null, 'ohne lokalen Artikel kein Artikelpreis');
        $this->assertNotContains('article_price_differs', $premium['flags'] ?? []);
    }

    public function test_local_billing_creates_a_local_invoice_draft_with_proposed_links(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => \App\Enums\Finance\BillingMode::Workdiary]);
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bp', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium', 'lexoffice_article_id' => $article->id,
            'customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);

        $this->actingAs($admin)->post(route('finance.resale.periods.draft.store'), ['customer_id' => $customer->sqid])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('success');

        $invoice = \App\Models\Invoice::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(\App\Models\Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertSame('resale', $invoice->category);
        $this->assertCount(2, $invoice->items, 'eine Position je Periode');
        $this->assertSame('24.000', $invoice->items->first()?->quantity, '2 Lizenzen × 12 Monate');
        $this->assertSame('2025-08-05', $invoice->items->first()?->service_from?->toDateString(), 'Leistungszeitraum = Periode (Review 2026-09-11)');
        $this->assertSame('2026-08-04', $invoice->items->first()?->service_to?->toDateString());
        $this->assertSame('98880', (string) $invoice->subtotal?->getMinorAmount(), '2 Perioden × 2 × 247,20');
        foreach ($subscription->periods()->get() as $period) {
            $this->assertSame(PeriodStatus::Billed, $period->status);
            $this->assertTrue($period->isProposedOnly(), 'Bezug auf die lokale Rechnungsposition ist ein Vorschlag');
            $this->assertSame(\App\Models\InvoiceItem::class, $period->links()->first()?->linkable_type);
            $this->assertSame($invoice->number, $period->draft_reference, 'Stempel = Rechnungsnummer des Entwurfs');
            $this->assertNotNull($period->draft_created_at);
            $this->assertStringContainsString($invoice->number, (string) $period->note);
        }
        // Der Vorschlagslauf lässt lokale Rechnungsbezüge stehen — und die Perioden „berechnet".
        app(\App\Services\Reselling\Register\LinkProposer::class)->propose($this->organization);
        $this->assertSame(2, \App\Models\Reselling\ResalePeriodLink::query()->count());
        $this->assertSame([PeriodStatus::Billed, PeriodStatus::Billed], $subscription->periods()->get()->map(static fn($p) => $p->status)->all());

        // Zweiter Klick: nichts mehr offen — als 422 in den Dialog (Review 2026-09-10, C1), kein zweiter Entwurf.
        $this->actingAs($admin)->postJson(route('finance.resale.periods.draft.store'), ['customer_id' => $customer->sqid])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', __('resale.draft.error.nothing_open'));
        $this->assertSame(1, \App\Models\Invoice::query()->where('customer_id', $customer->id)->count());

        // Rechnung ausgestellt, Periode bestätigt: der entschiedene Bezug trägt die Nummer → Stempel weg.
        $first = $subscription->periods()->firstOrFail();
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $first->sqid))->assertRedirect();
        $first->refresh();
        $this->assertNull($first->draft_reference, 'Entwurf wurde Rechnung');
        $this->assertNull($first->draft_created_at);
        $this->assertNotNull($first->decided_at);
    }

    public function test_draft_is_created_in_lexoffice_from_open_periods(): void {
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH', 'billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-lds', 'referenceable_type' => $partner->getMorphClass(), 'referenceable_id' => $partner->getKey(),
        ]);
        $kaik = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bb', 'name' => 'Microsoft 365 Business Basic', 'article_number' => 'BB',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '5.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Basic', 'lexoffice_article_id' => $article->id,
            'foreign_customer_id' => $kaik->id, 'quantity' => 1, 'starts_on' => '2025-04-09', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '67.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);
        $this->assertSame(2, $subscription->periods()->count());
        $subscription->periods()->firstOrFail()->forceFill(['note' => 'Kunde fragt nach Sammelrechnung'])->save();
        // Abo ohne Verkaufspreis: seine Perioden kommen nicht in den Entwurf und dürfen keinen Stempel bekommen.
        $noPrice = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $partner->id, 'quantity' => 1, 'starts_on' => '2025-04-09', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($noPrice);
        $unpriced = $noPrice->periods()->firstOrFail();

        $captured = null;
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices' => static function (RequestInterface $request) use (&$captured) {
                $captured = json_decode((string) $request->getBody(), true);

                return FakePluginHttp::response(['id' => 'draft-42', 'resourceUri' => 'https://api.lexoffice.io/v1/invoices/draft-42'], 201);
            },
        ]);

        $this->actingAs($admin)->get(route('finance.resale.periods.draft.create'))->assertOk()->assertSee('LDS Systems GmbH');
        $this->actingAs($admin)->post(route('finance.resale.periods.draft.store'), ['customer_id' => $partner->sqid])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($captured, 'Entwurf wurde an Lexoffice gesendet');
        $this->assertSame('c-lds', $captured['address']['contactId']);
        $this->assertArrayNotHasKey('finalize', $captured);
        $this->assertCount(2, $captured['lineItems'], 'eine Position je Periode');
        $this->assertSame('Microsoft 365 Business Basic', $captured['lineItems'][0]['name']);
        $this->assertStringContainsString('Steuerbüro Kaik', $captured['lineItems'][0]['description']);
        $this->assertStringContainsString('09.04.2025 – 08.04.2026', $captured['lineItems'][0]['description']);
        $this->assertEquals(12, $captured['lineItems'][0]['quantity']);
        $this->assertSame('Monat', $captured['lineItems'][0]['unitName']);
        $this->assertEqualsWithDelta(5.6, $captured['lineItems'][0]['unitPrice']['netAmount'], 0.001);
        $this->assertSame('net', $captured['taxConditions']['taxType']);

        $period = $subscription->periods()->firstOrFail();
        $this->assertSame(PeriodStatus::Open, $period->status, 'Entwurf entscheidet nichts');
        $this->assertSame('draft-42', $period->draft_reference);
        $this->assertNotNull($period->draft_created_at);
        $this->assertStringContainsString('draft-42', (string) $period->note);
        $this->assertStringStartsWith('Kunde fragt nach Sammelrechnung', (string) $period->note, 'vorhandene Bemerkung bleibt, Stempel wird angehängt');
        $this->assertSame(2, $subscription->periods()->whereNotNull('draft_reference')->count(), 'nur Perioden mit Position werden gestempelt');
        $this->assertNull($unpriced->fresh()?->draft_reference, 'Periode ohne Position im Entwurf bleibt ungestempelt');

        // Zweiter Klick: kein zweiter Entwurf, sondern der Hinweis auf den ausstehenden.
        $calls = 0;
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices' => static function () use (&$calls) {
                $calls++;

                return FakePluginHttp::response(['id' => 'draft-43', 'resourceUri' => 'https://api.lexoffice.io/v1/invoices/draft-43'], 201);
            },
        ]);
        $this->actingAs($admin)->postJson(route('finance.resale.periods.draft.store'), ['customer_id' => $partner->sqid])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', __('resale.draft.already_drafted', ['reference' => 'draft-42', 'date' => '04.09.2026']));
        $this->assertSame(0, $calls, 'Lexoffice wurde nicht erneut gerufen');
        // Der Dialog bietet den Empfänger nicht erneut an, nennt aber den ausstehenden Entwurf.
        $dialog = $this->actingAs($admin)->get(route('finance.resale.periods.draft.create'))->assertOk();
        $dialog->assertSee('draft-42')->assertSee(__('resale.draft_dialog.drafted'));
        $this->assertStringNotContainsString('<option value="' . $partner->sqid . '"', $dialog->getContent(), 'gestempelter Empfänger nicht wählbar');
        $this->assertSame('draft-42', $period->fresh()?->draft_reference);

        // Verzichten lässt den Stempel stehen; ein bestätigter Bezug auf die gespiegelte Rechnung (gleiche
        // Lexoffice-ID wie der Entwurf) löscht ihn — der Entwurf wurde abgeschlossen.
        $second = $subscription->periods()->orderBy('starts_on')->get()[1];
        $this->actingAs($admin)->post(route('finance.resale.periods.waive', $second->sqid), ['decision' => 'waived', 'reason' => 'Kulanz'])->assertRedirect();
        $this->assertSame('draft-42', $second->fresh()?->draft_reference);
        $voucher = \App\Models\LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'draft-42', 'contact_external_id' => 'c-lds', 'voucher_type' => 'invoice',
            'voucher_status' => 'open', 'voucher_number' => 'RE/2026/0900', 'voucher_date' => '2026-09-04', 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $line = \App\Models\LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service', 'external_article_id' => 'art-bb', 'lexoffice_article_id' => $article->id,
            'name' => 'Microsoft 365 Business Basic', 'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '5.60', 'total_net' => '67.20', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        (new \App\Services\Reselling\Register\PeriodLinker)->attach($period, $line->load('voucher'), 12.0, null, $admin->id);
        $period->refresh();
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $this->assertNull($period->draft_reference, 'Bezug auf die Rechnung mit der Entwurfs-ID löscht den Stempel');

        // Ohne Lexoffice-Kontakt: verständliche Fehlermeldung als 422 statt Absturz.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ohne Kontakt', 'billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice]);
        $this->actingAs($admin)->postJson(route('finance.resale.periods.draft.store'), ['customer_id' => $other->sqid])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', __('resale.link.no_contacts'));
        // Unbekannter oder fremder Empfänger: Sqid-Decode + Org-Prüfung im FormRequest.
        $this->actingAs($admin)->postJson(route('finance.resale.periods.draft.store'), ['customer_id' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_draft_service_skips_unpriced_periods_reports_nothing_open_and_appends_the_note(): void {
        // Review 2026-09-10 (G, A4) auf Service-Ebene: Perioden ohne Verkaufspreis liefern keine Position und bleiben
        // ungestempelt; ohne eine einzige Position ist es die Ausnahme „nichts offen" (kein Entwurf, kein Stempel);
        // die Bemerkung der Periode wird um den Stempel ergänzt, nie ersetzt.
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => \App\Enums\Finance\BillingMode::Workdiary]);
        $unpriced = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $customer->id, 'quantity' => 1, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($unpriced);
        $this->assertNull($unpriced->sale_unit_price);
        $unpricedPeriod = $unpriced->periods()->firstOrFail();
        $unpricedPeriod->forceFill(['note' => 'Preis klären'])->save();
        $service = app(\App\Services\Reselling\Register\ResaleInvoiceDraftService::class);
        $this->assertCount(2, $service->openPeriodsFor($customer), 'offen sind die Perioden schon — nur ohne Preis nicht vorschlagbar');

        try {
            $service->draft($this->organization, $customer, $admin);
            $this->fail('ohne Verkaufspreis gibt es nichts zu entwerfen');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('resale.draft.error.nothing_open'), $e->getMessage());
        }
        $this->assertSame(0, \App\Models\Invoice::query()->count(), 'kein leerer Entwurf');
        $this->assertNull($unpricedPeriod->fresh()?->draft_reference, 'Periode ohne Position bleibt ungestempelt');
        $this->assertSame('Preis klären', $unpricedPeriod->fresh()?->note, 'Bemerkung unangetastet');
        $this->assertSame(PeriodStatus::Open, $unpricedPeriod->fresh()?->status);

        // Ein zweites Abo mit Preis: nur dessen Perioden kommen in den Entwurf, die preislosen bleiben außen vor.
        $priced = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($priced);
        $pricedPeriod = $priced->periods()->firstOrFail();
        $pricedPeriod->forceFill(['note' => 'Kunde will Sammelrechnung'])->save();

        $result = $service->draft($this->organization, $customer, $admin);
        $this->assertTrue($result['local']);
        $this->assertSame('local', $result['target']);
        $this->assertSame(2, $result['lines'], 'zwei Perioden des bepreisten Abos');
        $this->assertSame(2, $result['periods']);
        $this->assertSame(988.8, $result['net'], '2 × 2 × 247,20');
        $invoice = \App\Models\Invoice::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($invoice->number, $result['draft_id']);
        $this->assertSame(2, $priced->periods()->whereNotNull('draft_reference')->count());
        $this->assertSame(0, $unpriced->periods()->whereNotNull('draft_reference')->count(), 'preislose Perioden bleiben ungestempelt');
        $pricedPeriod->refresh();
        $this->assertSame($invoice->number, $pricedPeriod->draft_reference);
        $this->assertSame('Kunde will Sammelrechnung · ' . __('resale.draft.local_note', ['number' => $invoice->number]), $pricedPeriod->note, 'Stempel wird angehängt (Trenner „ · ")');
        $this->assertNotNull($pricedPeriod->draft_created_at);
        $this->assertStringStartsWith('Microsoft 365 Business Premium · ', (string) $invoice->items->first()?->description);
        $this->assertSame('2.000', $invoice->items->first()?->quantity, 'kein Monatsartikel → Stück: 2 Lizenzen × 12/12');
        $this->assertSame('247.2000', $invoice->items->first()?->unit_price?->getAmount(), 'Stückpreis = Verkaufspreis je Periode und Lizenz');

        // Nochmal: nichts mehr offen (die bepreisten sind „berechnet", die preislosen liefern keine Position) → Ausnahme, kein zweiter Entwurf.
        try {
            $service->draft($this->organization, $customer, $admin);
            $this->fail('kein zweiter Entwurf');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('resale.draft.error.nothing_open'), $e->getMessage());
        }
        $this->assertSame(1, \App\Models\Invoice::query()->count());
        $this->assertSame('Kunde will Sammelrechnung · ' . __('resale.draft.local_note', ['number' => $invoice->number]), $pricedPeriod->fresh()?->note, 'Stempel nicht doppelt');
    }
}
