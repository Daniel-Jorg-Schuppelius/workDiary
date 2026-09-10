<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipientReconcilerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, ExternalReference, ForeignCustomer, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{LicenseMonths, LinkProposer, PeriodPlanner, RecipientReconciler};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abgleich je Rechnungsempfänger (Feature 152): Bilanz je Produkt trennt
 * „nur nicht zugeordnet" von „nie abgerechnet" und „ohne Abo"; Zuordnung
 * über Abo-Grenzen hinweg; Übersicht mit Empfängern ohne Kunde.
 */
class RecipientReconcilerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private LexofficeArticle $standard;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $this->exchange = $this->article('art-exo', 'Exchange Online (Plan 1)', '3.95');
        $this->standard = $this->article('art-bs', 'Microsoft 365 Business Standard', '12.13');
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
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    /**
     * @param  list<array{article: LexofficeArticle, quantity: float, unit?: string, net: string}>  $lines
     */
    private function voucher(string $contactId, string $number, string $date, array $lines, ?Customer $customer = null, string $status = 'paid', string $text = '', ?string $serviceFrom = null, ?string $serviceTo = null): LexofficeVoucher {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'customer_id' => $customer?->id,
            'voucher_type' => 'invoice', 'voucher_status' => $status, 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'voucher_text' => $text,
            'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo,
            'archived' => false, 'recipient_name' => 'Unbekannte GmbH', 'lines_synced_at' => now(),
        ]);
        foreach ($lines as $position => $line) {
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => $position + 1, 'type' => 'service',
                'external_article_id' => $line['article']->external_id, 'lexoffice_article_id' => $line['article']->id,
                'name' => $line['article']->name, 'quantity' => $line['quantity'], 'unit_name' => $line['unit'] ?? 'Monat',
                'unit_net' => $line['net'], 'total_net' => (string) round((float) $line['net'] * $line['quantity'], 2), 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }

        return $voucher;
    }

    public function test_balance_separates_unassigned_from_never_invoiced_and_without_subscription(): void {
        $customer = $this->customerWithContact('EcoTec Service GmbH', 'c-eco');
        // Ein 1er-Vertrag, sauber berechnet — und ein 5er-Vertrag über zwei Jahre, für den es nie eine Rechnung gab.
        $single = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2024-04-10', 'ends_on' => '2026-04-10', 'status' => 'ended']);
        $five = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2024-04-24', 'ends_on' => '2026-04-24', 'quantity' => 5, 'status' => 'ended']);
        $this->voucher('c-eco', 'RE/2024/0630', '2024-04-14', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']]);
        $this->voucher('c-eco', 'RE/2025/0830', '2025-04-14', [['article' => $this->exchange, 'quantity' => 1, 'unit' => 'Jahr', 'net' => '47.40']]);
        // Business Standard wurde berechnet, steht aber in keinem Abo.
        $this->voucher('c-eco', 'RE/2025/0886', '2025-08-10', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Billed, $single->periods()->first()?->status);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        $this->assertSame(2, $result['open'], 'die beiden 5er-Perioden');
        $this->assertSame(12.0, $result['free'], 'die Business-Standard-Position hängt an keiner Periode');
        $this->assertSame(120.0, $result['missing'], '2 × 5 × 12 Monate ohne jede Position');
        $this->assertSame(12.0, $result['surplus'], 'Business Standard ohne Abo');
        $products = collect($result['products'])->keyBy('label');
        $this->assertSame(['required' => 144.0, 'covered' => 24.0, 'invoiced' => 24.0, 'free' => 0.0, 'missing' => 120.0, 'surplus' => 0.0], collect($products['Exchange Online (Plan 1)'])->only(['required', 'covered', 'invoiced', 'free', 'missing', 'surplus'])->all());
        $this->assertSame(0, $products['Microsoft 365 Business Standard']['subscriptions']);
        $this->assertSame(12.0, $products['Microsoft 365 Business Standard']['surplus']);

        // Die offene Periode sieht die vergebenen Positionen desselben Produkts — und keine freie.
        $first = $result['periods'][0];
        $this->assertSame($five->id, $first['subscription']->id);
        $this->assertSame([], $first['candidates']);
        $this->assertSame(['RE/2024/0630', 'RE/2025/0830'], array_map(static fn(array $c): string => (string) $c['row']['line']->voucher->voucher_number, $first['taken']), 'im Fenster der Periode, nächste zuerst');
        $this->assertStringContainsString('EcoTec Service GmbH · ×1 · Telekom Cloud Marketplace ab 10.04.2024 · 10.04.2024 – 09.04.2025', $first['taken'][0]['row']['periods'][0]);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))
            ->assertOk()
            ->assertSee('0 / 5 Lizenzen · 12 Mon.')
            ->assertSee('10 × 12 Mon. nie abgerechnet')
            ->assertSee('1 × 12 Mon. ohne Periode')
            ->assertSee('RE/2024/0630')
            ->assertSee('vergeben an EcoTec Service GmbH · ×1 · Telekom Cloud Marketplace ab 10.04.2024 · 10.04.2024 – 09.04.2025');
    }

    public function test_overview_lists_recipients_with_problems_first_and_contacts_without_customer(): void {
        $admin = $this->orgAdmin();
        $clean = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subClean = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $clean->id, 'lexoffice_article_id' => $this->standard->id, 'starts_on' => '2025-10-01']);
        $this->voucher('c-kl', 'RE/2025/0900', '2025-10-01', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);
        $gap = $this->customerWithContact('Ute Mayershofer', 'c-um');
        $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $gap->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2025-10-01']);
        // Rechnung an einen Lexoffice-Kontakt, den kein Kunde kennt.
        $this->voucher('c-unknown', 'RE/2025/0950', '2025-10-05', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']]);
        (new LinkProposer)->propose($this->organization);
        // Bestätigter Vorschlag: nichts mehr zu klären. Unbestätigte zählen als Klärungsbedarf.
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $subClean->periods()->firstOrFail()->sqid))->assertRedirect();

        $rows = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(['Ute Mayershofer', 'Unbekannte GmbH', 'Klimpel Bäder GmbH'], array_column($rows, 'name'));
        $this->assertSame(12.0, $rows[0]['missing']);
        $this->assertNull($rows[1]['customer']);
        $this->assertSame(12.0, $rows[1]['surplus']);
        $this->assertSame(0, $rows[2]['open'] + $rows[2]['partial']);

        $this->actingAs($admin)->get(route('finance.resale.reconcile.index'))
            ->assertOk()->assertSee('Ute Mayershofer')->assertSee('Unbekannte GmbH')->assertDontSee('Klimpel Bäder GmbH');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'all']))
            ->assertOk()->assertSee('Klimpel Bäder GmbH');
    }

    public function test_assign_links_a_line_to_any_period_of_the_recipient_but_not_of_another_customer(): void {
        $admin = $this->orgAdmin();
        $partner = $this->customerWithContact('LDS Systems GmbH', 'c-lds');
        $kaik = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Steuerbüro Kaik']);
        $haus = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Haus 24 GmbH']);
        $subKaik = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $kaik->id, 'lexoffice_article_id' => $this->standard->id, 'starts_on' => '2025-09-12']);
        $subHaus = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'foreign_customer_id' => $haus->id, 'lexoffice_article_id' => $this->standard->id, 'starts_on' => '2025-09-12']);
        $other = $this->customerWithContact('Fremde GmbH', 'c-x');
        $subOther = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $other->id, 'lexoffice_article_id' => $this->standard->id, 'starts_on' => '2025-09-12']);
        // Zwei gleiche Positionen ohne Endkundennennung: der Vorschlagslauf kann sie nicht trennen.
        $voucher = $this->voucher('c-lds', 'RE/2025/1000', '2025-09-15', [
            ['article' => $this->standard, 'quantity' => 12, 'net' => '12.13'],
            ['article' => $this->standard, 'quantity' => 1, 'unit' => 'Jahr', 'net' => '145.56'],
        ]);
        [$lineA, $lineB] = $voucher->lines()->orderBy('position')->get()->all();
        $this->assertSame(12.0, LicenseMonths::ofLine($lineB), '„1 Jahr" = 12 Lizenzmonate');

        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $partner))->assertOk();
        $page->assertSee('Steuerbüro Kaik')->assertSee('Haus 24 GmbH')->assertSee('RE/2025/1000');

        $periodKaik = $subKaik->periods()->firstOrFail();
        $periodHaus = $subHaus->periods()->firstOrFail();
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $periodKaik->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineA->id), 'months' => '12',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner));
        // Formular denkt in Lizenzen: 1 Lizenz × 12 Monate je Lizenz = 12 Lizenzmonate.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $periodHaus->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineB->id), 'licences' => '1', 'per_licence' => '12.00', 'note' => 'laut Telefonat',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner));

        $periodKaik->refresh();
        $periodHaus->refresh();
        $this->assertSame(PeriodStatus::Billed, $periodKaik->status);
        $this->assertSame(PeriodStatus::Billed, $periodHaus->status);
        $this->assertNotNull($periodHaus->decided_at, 'manuelle Zuordnung ist eine Entscheidung');
        $link = ResalePeriodLink::query()->where('period_id', $periodHaus->id)->firstOrFail();
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertSame('12.00', $link->months);
        $this->assertSame('145.56', $link->amount?->getAmount(), 'Jahresposition: 12 Monate = 1 Stück');
        $this->assertSame('laut Telefonat', $link->note);

        // Mehr als die Position hergibt: eine 12er-Position kann nicht noch einmal 12 an eine weitere Periode geben.
        $second = ResaleSubscription::query()->create(array_merge($subKaik->only(['organization_id', 'kind', 'provider', 'label', 'foreign_customer_id', 'lexoffice_article_id', 'term_months', 'interval', 'renewal', 'status', 'currency']), ['quantity' => 1, 'starts_on' => '2026-09-12', 'external_id' => 'x2']));
        (new \App\Services\Reselling\Register\PeriodPlanner)->sync($second);
        // Review 2026-09-10 (C1): Feldfehler statt stillem Flash — der Dialog zeigt sie als 422.
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $partner))->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $second->periods()->firstOrFail()->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineA->id), 'months' => '12',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner))->assertSessionHasErrors('months');
        $this->assertSame(0, $second->periods()->firstOrFail()->links()->count());

        // Periode eines anderen Kunden: kein Bezug über fremde Empfänger.
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $partner))->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $subOther->periods()->firstOrFail()->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineA->id), 'months' => '12',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner))->assertSessionHasErrors('period_id');
        $this->assertSame(0, ResalePeriodLink::query()->where('subscription_id', $subOther->id)->count());

        // Position eines fremden Kontakts (B13): auch bei passender Periode kein Bezug über Kundengrenzen.
        $foreignVoucher = $this->voucher('c-x', 'RE/2025/2000', '2025-09-15', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $partner))->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $second->periods()->firstOrFail()->sqid, 'line_id' => $foreignVoucher->lines()->firstOrFail()->sqid, 'months' => '12',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner))->assertSessionHasErrors('line_id');
        $this->assertSame(0, $second->periods()->firstOrFail()->links()->count());

        $result = (new RecipientReconciler)->forCustomer($this->organization, $partner);
        $this->assertSame(0, $result['open']);
        $this->assertSame(0.0, $result['free']);
        $this->assertSame([], $result['periods']);
    }

    public function test_sister_company_invoice_voided_invoice_and_inbox_mentions_are_shown(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('EcoTec Service GmbH', 'c-eco');
        $sister = $this->customerWithContact('EcoTec - HLSK GmbH', 'c-hlsk');
        $this->customerWithContact('Delta Allround Service GmbH', 'c-delta');
        // Der Anbieter führt den Vertrag auf EcoTec Service, berechnet wurde er an die Schwesterfirma;
        // die zweite Rechnung wurde storniert und nie neu gestellt.
        $five = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'company_name' => 'EcoTec Service GmbH', 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2024-04-24', 'ends_on' => '2026-04-24', 'quantity' => 5, 'status' => 'ended']);
        $this->voucher('c-hlsk', 'RE/2024/0171', '2024-05-11', [['article' => $this->exchange, 'quantity' => 5, 'unit' => 'Jahr', 'net' => '47.40']]);
        $this->voucher('c-hlsk', 'RE/2025/0271', '2025-05-11', [['article' => $this->exchange, 'quantity' => 5, 'unit' => 'Jahr', 'net' => '47.40']], null, 'voided');
        // Unverwandter Empfänger mit derselben Menge, aber nicht dicht am Periodenbeginn: kein Kandidat.
        $this->voucher('c-delta', 'RE/2024/0201', '2024-10-01', [['article' => $this->exchange, 'quantity' => 60, 'net' => '3.95']]);
        // Partnerfall: Abo ohne Halter, dessen Firma im Rechnungstext an diesen Empfänger steht.
        ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'quantity' => 4, 'label' => 'Microsoft 365 Apps for business',
            'company_name' => 'Robert Kasch', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'starts_on' => '2025-02-13',
        ]);
        $this->voucher('c-eco', 'RE/2025/0800', '2025-02-13', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']], null, 'paid', 'Microsoftdienste Robert Kasch');

        $result = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        [$first, $second] = $result['periods'];
        $this->assertSame([], $first['candidates']);
        $this->assertSame(['RE/2024/0171'], array_map(static fn(array $c): string => (string) $c['row']['line']->voucher->voucher_number, $first['foreign']), 'Schwesterfirma über gemeinsames Namens-Token, Delta nicht');
        $this->assertSame('EcoTec - HLSK GmbH', $first['foreign'][0]['row']['recipient']);
        $this->assertSame([], $first['voided'], 'Storno vom Folgejahr gehört nicht zur ersten Periode');
        $this->assertSame(['RE/2025/0271'], array_map(static fn(array $c): string => (string) $c['row']['line']->voucher->voucher_number, $second['voided']));
        $this->assertSame([], $second['foreign']);
        $this->assertSame('Robert Kasch', $result['inbox'][0]['company']);
        $this->assertSame(1, $result['inbox'][0]['mentions']);
        $this->assertCount(1, $result['inbox'][0]['subscriptions']);

        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))->assertOk();
        $page->assertSee('Rechnung an EcoTec - HLSK GmbH')->assertSee('5 × 12 Mon.')->assertSee('RE/2025/0271')->assertSee('storniert')->assertSee('Robert Kasch')->assertDontSee('RE/2024/0201');
        $this->assertSame($sister->id, $first['foreign'][0]['row']['recipient_id']);

        // Zwei Firmen, nicht eine: der Halter wird auf den tatsächlichen Rechnungsempfänger gesetzt,
        // danach greift der Vorschlagslauf — kein Bezug über Kundengrenzen.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.rehome', $customer), [
            'period_id' => $first['period']->sqid, 'target_id' => $sister->sqid,
        ])->assertRedirect(route('finance.resale.reconcile.show', $sister));
        $five->refresh();
        $this->assertSame($sister->id, $five->customer_id);
        $this->assertSame(PeriodStatus::Billed, $first['period']->fresh()?->status, 'RE/2024/0171 sofort vorgeschlagen');
        $this->assertSame(PeriodStatus::Open, $second['period']->fresh()?->status, 'Storno deckt nichts');
        $sisterView = (new RecipientReconciler)->forCustomer($this->organization, $sister);
        $this->assertSame(0.0, $sisterView['free']);
        $this->assertSame(60.0, $sisterView['missing'], 'zweite Periode: 5 × 12 ohne gültige Rechnung');

        // Fremder Kunde als Ziel einer Periode, die nicht diesem Empfänger gehört: abgelehnt (Feldfehler, Review C1).
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $customer))->post(route('finance.resale.reconcile.rehome', $customer), [
            'period_id' => $second['period']->sqid, 'target_id' => $customer->sqid,
        ])->assertRedirect(route('finance.resale.reconcile.show', $customer))->assertSessionHasErrors('period_id');
        $this->assertSame($sister->id, $five->fresh()?->customer_id);
    }

    public function test_service_period_beats_invoice_date_for_assignment(): void {
        $customer = $this->customerWithContact('Marina Vulkan Werft', 'c-mv');
        $subscription = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2024-12-31', 'quantity' => 2]);
        [$p2024, $p2025] = $subscription->periods()->get()->all();
        // Rechnung erst im März 2026 gestellt, Leistungszeitraum aber das Jahr ab 31.12.2024: gehört zur ERSTEN Periode.
        // „24 Monat" bei zwölf Monaten Leistung = zwei Lizenzen × 12.
        $late = $this->voucher('c-mv', 'RE/2026/1050', '2026-03-10', [['article' => $this->exchange, 'quantity' => 24, 'net' => '3.95']], null, 'paid', '', '2024-12-31', '2025-12-30');
        $onTime = $this->voucher('c-mv', 'RE/2026/1002', '2026-01-05', [['article' => $this->exchange, 'quantity' => 2, 'unit' => 'Stück', 'net' => '47.40']], null, 'paid', '', '2025-12-31', '2026-12-30');
        $line = $late->lines()->firstOrFail()->load('voucher');
        $this->assertSame(['licences' => 2.0, 'months' => 12.0], LicenseMonths::split($line));
        $this->assertSame(['licences' => 2.0, 'months' => 12.0], LicenseMonths::split($onTime->lines()->firstOrFail()->load('voucher')), '„Stück" nimmt die Laufzeit aus dem Leistungszeitraum');

        (new LinkProposer)->propose($this->organization);
        $this->assertSame('RE/2026/1050', $p2024->fresh()?->links()->first()?->voucher_number, 'Leistungszeitraum schlägt Rechnungsdatum');
        $this->assertSame('RE/2026/1002', $p2025->fresh()?->links()->first()?->voucher_number);
        $this->assertSame(PeriodStatus::Billed, $p2024->fresh()?->status);
        $this->assertSame(PeriodStatus::Billed, $p2025->fresh()?->status);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        $this->assertSame([], $result['periods']);
        $this->assertSame(2.0, $result['lines'][0]['licences']);
        $this->actingAs($this->orgAdmin())->get(route('finance.resale.reconcile.show', $customer))->assertOk()->assertSee('Leistung 31.12.2024 – 30.12.2025')->assertSee('2 × 12 Mon.');
    }

    public function test_lines_after_the_last_period_mark_a_gap_and_prefill_a_new_subscription(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Ute Mayershofer', 'c-um');
        // Anbieter-Export kennt nur den gekündigten Vertrag bis 2024 — die Rechnungen laufen weiter.
        $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2023-08-07', 'ends_on' => '2024-08-12', 'quantity' => 3, 'status' => 'ended']);
        $this->voucher('c-um', 'RE/2023/0568', '2023-08-10', [['article' => $this->exchange, 'quantity' => 36, 'net' => '3.95']]);
        $this->voucher('c-um', 'RE/2024/0724', '2024-10-26', [['article' => $this->exchange, 'quantity' => 24, 'net' => '3.95']]);
        $this->voucher('c-um', 'RE/2025/0945', '2025-10-26', [['article' => $this->exchange, 'quantity' => 24, 'net' => '3.95']]);
        (new LinkProposer)->propose($this->organization);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $customer);
        $product = $result['products'][0];
        $this->assertSame('Exchange Online (Plan 1)', $product['label']);
        // Die 24er-Position vom Oktober 2024 deckt noch die letzte Periode (rückwirkend, Fenster verlängert);
        // erst die vom Oktober 2025 trifft keine Periode mehr — ab da fehlt der Vertrag.
        $this->assertSame('2025-10-26', $product['gap_since']?->toDateString(), 'ab der ersten Position, die keine Periode mehr trifft');
        $gaps = array_values(array_filter($result['lines'], static fn(array $l): bool => $l['gap']));
        $this->assertSame(['RE/2025/0945'], array_map(static fn(array $l): string => (string) $l['line']->voucher->voucher_number, $gaps));

        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))->assertOk();
        $page->assertSee('Position ohne Abo ab 26.10.2025')->assertSee('Abo aus Position anlegen');

        // Dialog aus der Position: Produkt, Menge (1 Lizenz — „24 Monat" ohne Zeitraum), Beginn, Jahrespreis vorbelegt.
        $line = $gaps[0]['line'];
        $dialog = $this->actingAs($admin)->get(route('finance.resale.create', ['customer' => $customer->sqid, 'line' => Sqid::encode(LexofficeVoucherLine::class, $line->id)]))->assertOk();
        $dialog->assertSee('value="Exchange Online (Plan 1)"', false)
            ->assertSee('name="quantity"', false)
            ->assertSee('value="47.40"', false)
            ->assertSee('2025-10-26');
    }

    public function test_former_account_holder_is_found_by_the_sum_of_its_lines(): void {
        // Ute Mayershofer: Delta Allround war Lizenznehmer und wurde je Jahr mit 3 × 12 berechnet,
        // Ute übernahm das Konto später — die Namen haben nichts gemeinsam.
        $admin = $this->orgAdmin();
        $ute = $this->customerWithContact('Ute Mayershofer', 'c-um');
        $delta = $this->customerWithContact('Delta Allround Service GmbH', 'c-delta');
        $this->customerWithContact('Märkische Bunker- und Service GmbH & Co. KG', 'c-mb');
        $subscription = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $ute->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2024-08-12', 'ends_on' => '2026-08-12', 'quantity' => 3, 'status' => 'ended']);
        foreach (['RE/2024/0201' => '2024-10-16', 'RE/2025/0303' => '2025-08-12'] as $number => $date) {
            $this->voucher('c-delta', $number, $date, [
                ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
                ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
                ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
            ]);
        }
        // Zufällig ebenfalls drei Lizenzen, aber weit weg vom Periodenbeginn: kein Kandidat.
        $this->voucher('c-mb', 'RE/2025/0100', '2025-02-08', [
            ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
            ['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95'],
        ]);

        $result = (new RecipientReconciler)->forCustomer($this->organization, $ute);
        [$first, $second] = $result['periods'];
        $this->assertSame(['Delta Allround Service GmbH'], array_values(array_unique(array_map(static fn(array $c): string => (string) $c['row']['recipient'], $first['foreign']))));
        $this->assertCount(3, $first['foreign'], 'alle drei Positionen der Sammelrechnung');
        $this->assertCount(3, $second['foreign']);
        $this->assertSame($delta->id, $first['foreign'][0]['row']['recipient_id']);

        $page = $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $ute))->assertOk();
        $page->assertSee('Rechnung an Delta Allround Service GmbH')->assertSee(__('resale.reconcile.action.rehome', ['customer' => 'Delta Allround Service GmbH']))->assertDontSee('RE/2025/0100');

        // Ganzer Vertrag gehörte Delta: Halter wechseln, Vorschlagslauf deckt beide Jahre.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.rehome', $ute), ['period_id' => $first['period']->sqid, 'target_id' => $delta->sqid])
            ->assertRedirect(route('finance.resale.reconcile.show', $delta));
        $this->assertSame([PeriodStatus::Billed, PeriodStatus::Billed], $subscription->periods()->get()->map(static fn($p) => $p->status)->all());
    }

    public function test_recipient_invoice_lines_and_contact_map_are_the_shared_reading_side(): void {
        // Review 2026-09-10 (E): Kontakte → gültige Rechnungen im Fenster → Klassifikator → Verbrauch je Position.
        // Ein Lexoffice-Kontakt je Kunde (Unique `extref_unique` auf Plugin/Typ/Referenz).
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $other = $this->customerWithContact('Fremde GmbH', 'c-x');
        $subscription = $this->subscription(['label' => 'Microsoft 365 Business Standard', 'customer_id' => $customer->id, 'lexoffice_article_id' => $this->standard->id, 'starts_on' => '2025-10-01']);
        $period = $subscription->periods()->firstOrFail();
        $support = $this->article('art-sup', 'Business Support', '90.00');

        $map = \App\Plugins\Lexoffice\Services\LexofficeContactMap::forOrganization($this->organization);
        $this->assertSame(['c-kl'], $map->byCustomer($customer->id));
        $this->assertSame($customer->id, $map->byContact('c-kl'));
        $this->assertNull($map->byContact('c-unknown'));
        $this->assertSame(['c-x'], \App\Plugins\Lexoffice\Services\LexofficeContactMap::forCustomer($other)->byCustomer($other->id));
        $this->assertSame([], \App\Plugins\Lexoffice\Services\LexofficeContactMap::forOrganization($this->organization, collect())->all(), 'ohne Abos keine Kontakte');
        $this->assertSame(['c-kl'], (new LinkProposer)->contactsFor($subscription), 'Delegation am Proposer bleibt');

        // Rechnungen: eine vor dem Fenster (zählt nur über ihr Leistungsende), eine stornierte,
        // eine ohne gespiegelte Positionen, eine Support-Position, eine fremde.
        $this->voucher('c-kl', 'RE/2025/0900', '2025-10-01', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);
        $this->voucher('c-kl', 'RE/2025/0901', '2025-10-02', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13'], ['article' => $support, 'quantity' => 2, 'unit' => 'Stunde', 'net' => '90.00']]);
        $this->voucher('c-kl', 'RE/2024/0100', '2024-01-01', [['article' => $this->standard, 'quantity' => 36, 'net' => '12.13']], null, 'paid', '', '2024-01-01', '2026-12-31');
        $this->voucher('c-kl', 'RE/2025/0950', '2025-10-05', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']], null, 'voided');
        $this->voucher('c-kl', 'RE/2025/0960', '2025-10-06', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']])->forceFill(['lines_synced_at' => null])->save();
        $this->voucher('c-x', 'RE/2025/0970', '2025-10-03', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);

        $lines = new \App\Plugins\Lexoffice\Services\LexofficeRecipientInvoiceLines;
        $contacts = $map->byCustomer($customer->id);
        $from = \Carbon\CarbonImmutable::parse('2025-07-03');
        $numbers = static fn(\Illuminate\Support\Collection $rows): array => $rows->map(static fn(LexofficeVoucherLine $l): string => (string) $l->voucher->voucher_number)->sort()->values()->all();
        $this->assertSame(['RE/2024/0100', 'RE/2025/0900', 'RE/2025/0901', 'RE/2025/0960'], $numbers($lines->for($this->organization, $contacts, $from)), 'nur Lizenzpositionen, gültige Rechnungen, Leistungsende zählt');
        $this->assertSame(['RE/2025/0950'], $numbers($lines->voided($this->organization, $contacts)));
        $this->assertSame([], $lines->for($this->organization, [], $from)->all(), 'leere Kontaktliste = nichts');
        $this->assertSame(5, $lines->for($this->organization, null)->count(), 'null = alle Kontakte der Organisation (auch Fremde GmbH)');
        $this->assertSame(['RE/2024/0100', 'RE/2025/0900', 'RE/2025/0901', 'RE/2025/0960'], $numbers($lines->forPeriod($this->organization, $contacts, $period)));
        $this->assertSame(1, $lines->pendingCount($this->organization, $contacts, $from));

        $vouchers = $lines->vouchers($this->organization, $contacts, $from);
        $this->assertSame(['RE/2025/0901', 'RE/2025/0900', 'RE/2024/0100'], $vouchers->pluck('voucher_number')->all(), 'neueste zuerst, nur gespiegelte');
        $flags = $vouchers->firstWhere('voucher_number', 'RE/2025/0901')?->lines->map(static fn($l): bool => (bool) $l->getAttribute('is_license'))->all();
        $this->assertSame([true, false], $flags, 'Support-Position bleibt sichtbar, aber markiert');

        (new LinkProposer)->propose($this->organization);
        $consumed = $lines->consumed($lines->for($this->organization, $contacts, $from));
        $this->assertCount(1, $consumed, 'eine Position deckt die Periode');
        $this->assertSame(12.0, array_values($consumed)[0]['months']);
        $this->assertSame([$subscription->holderLabel() . ' · ' . $subscription->identityLabel() . ' · ' . $period->label()], array_values($consumed)[0]['periods'], 'Halter · Kennung · Zeitraum');
        $this->assertSame([], $lines->consumed($lines->for($this->organization, $contacts, $from), $period), 'Bezüge an der Ausnahme-Periode zählen nicht');
    }

    public function test_overview_falls_back_to_the_voucher_customer_when_the_contact_is_not_mapped(): void {
        // Review 2026-09-10 (G): Kontakt ohne ExternalReference, aber der Spiegel kennt den Kunden (`voucher.customer_id`,
        // z. B. aus dem Belegabgleich): die Positionen zählen beim Kunden — eine Zeile mit Abos UND Rechnungen statt
        // einer Kundenzeile „nie berechnet" plus einer Kontaktzeile „ohne Abo".
        $fallback = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fallback GmbH']);
        $this->assertSame([], \App\Plugins\Lexoffice\Services\LexofficeContactMap::forCustomer($fallback)->byCustomer($fallback->id), 'kein verknüpfter Kontakt');
        $subscription = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $fallback->id, 'lexoffice_article_id' => $this->exchange->id, 'starts_on' => '2025-10-01']);
        $this->voucher('c-unmapped', 'RE/2025/0960', '2025-10-03', [['article' => $this->exchange, 'quantity' => 12, 'net' => '3.95']], $fallback);
        // Ein weiterer unbekannter Kontakt ohne Kunden-ID bleibt eine eigene Kontaktzeile (Empfängername aus dem Beleg).
        $this->voucher('c-nobody', 'RE/2025/0961', '2025-10-04', [['article' => $this->standard, 'quantity' => 12, 'net' => '12.13']]);
        (new LinkProposer)->propose($this->organization);
        $this->assertSame(PeriodStatus::Open, $subscription->periods()->firstOrFail()->status, 'ohne Kontakt kann der Lauf nichts zuordnen');

        $rows = (new RecipientReconciler)->overview($this->organization);
        $this->assertSame(['Fallback GmbH', 'Unbekannte GmbH'], array_column($rows, 'name'));
        $byName = array_column($rows, null, 'name');
        $this->assertSame($fallback->id, $byName['Fallback GmbH']['customer']?->id, 'Kunde aus voucher.customer_id');
        $this->assertSame(1, $byName['Fallback GmbH']['subscriptions']);
        $this->assertSame(1, $byName['Fallback GmbH']['lines'], 'die Position hängt am Kunden, nicht an einer Kontaktzeile');
        $this->assertSame(1, $byName['Fallback GmbH']['open']);
        $this->assertSame(12.0, $byName['Fallback GmbH']['free'], 'freie Position desselben Produkts');
        $this->assertSame(0.0, $byName['Fallback GmbH']['missing'], 'nicht „nie berechnet" — nur nicht zugeordnet');
        $this->assertSame(0.0, $byName['Fallback GmbH']['surplus']);
        $this->assertNull($byName['Unbekannte GmbH']['customer']);
        $this->assertSame(0, $byName['Unbekannte GmbH']['subscriptions']);
        $this->assertSame(12.0, $byName['Unbekannte GmbH']['surplus'], 'Kontakt ohne Kunden-ID: Positionen ohne Abo');
        $this->assertCount(2, $rows, 'kein drittes Ergebnis für c-unmapped');

        // Sobald der Kontakt verknüpft ist, gewinnt die Verknüpfung — gleiche Zeile, der Lauf deckt die Periode.
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-unmapped', 'referenceable_type' => $fallback->getMorphClass(), 'referenceable_id' => $fallback->getKey(),
        ]);
        (new LinkProposer)->propose($this->organization);
        $rows = (new RecipientReconciler)->overview($this->organization);
        $byName = array_column($rows, null, 'name');
        $this->assertSame(1, $byName['Fallback GmbH']['proposed']);
        $this->assertSame(0, $byName['Fallback GmbH']['open']);
        $this->assertSame(0.0, $byName['Fallback GmbH']['free']);
        $this->assertSame(1, $byName['Fallback GmbH']['lines']);
    }
}
