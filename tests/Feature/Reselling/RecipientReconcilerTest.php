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
    private function voucher(string $contactId, string $number, string $date, array $lines, ?Customer $customer = null, string $status = 'paid', string $text = ''): LexofficeVoucher {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'customer_id' => $customer?->id,
            'voucher_type' => 'invoice', 'voucher_status' => $status, 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'voucher_text' => $text,
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
        $this->assertStringContainsString('EcoTec Service GmbH · 10.04.2024 – 09.04.2025', $first['taken'][0]['row']['periods'][0]);

        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $customer))
            ->assertOk()
            ->assertSee('120 Monate nie abgerechnet')
            ->assertSee('12 Monate ohne Periode')
            ->assertSee('RE/2024/0630')
            ->assertSee('vergeben an EcoTec Service GmbH · 10.04.2024 – 09.04.2025');
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
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $periodHaus->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineB->id), 'months' => '12', 'note' => 'laut Telefonat',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner));

        $periodKaik->refresh();
        $periodHaus->refresh();
        $this->assertSame(PeriodStatus::Billed, $periodKaik->status);
        $this->assertSame(PeriodStatus::Billed, $periodHaus->status);
        $this->assertNotNull($periodHaus->decided_at, 'manuelle Zuordnung ist eine Entscheidung');
        $link = ResalePeriodLink::query()->where('period_id', $periodHaus->id)->firstOrFail();
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->assertSame('145.56', $link->amount?->getAmount(), 'Jahresposition: 12 Monate = 1 Stück');
        $this->assertSame('laut Telefonat', $link->note);

        // Periode eines anderen Kunden: kein Bezug über fremde Empfänger.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $partner), [
            'period_id' => $subOther->periods()->firstOrFail()->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $lineA->id), 'months' => '12',
        ])->assertRedirect(route('finance.resale.reconcile.show', $partner))->assertSessionHas('error');
        $this->assertSame(0, ResalePeriodLink::query()->where('subscription_id', $subOther->id)->count());

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
        $page->assertSee('Rechnung an EcoTec - HLSK GmbH')->assertSee('RE/2025/0271')->assertSee('storniert')->assertSee('Robert Kasch')->assertDontSee('RE/2024/0201');

        // Zuordnung der Schwesterfirmen-Rechnung: erlaubt, mit Vermerk, deckt die erste Periode.
        $line = LexofficeVoucher::query()->where('voucher_number', 'RE/2024/0171')->firstOrFail()->lines()->firstOrFail();
        $this->actingAs($admin)->post(route('finance.resale.reconcile.assign', $customer), [
            'period_id' => $first['period']->sqid, 'line_id' => Sqid::encode(LexofficeVoucherLine::class, $line->id), 'months' => '60', 'note' => 'Rechnung ging an EcoTec - HLSK GmbH',
        ])->assertRedirect(route('finance.resale.reconcile.show', $customer));
        $this->assertSame(PeriodStatus::Billed, $first['period']->fresh()?->status);
        $this->assertSame('Rechnung ging an EcoTec - HLSK GmbH', ResalePeriodLink::query()->where('period_id', $first['period']->id)->firstOrFail()->note);
        // Bei der Schwesterfirma ist die Position nun vergeben — mit dem Halter als Ziel.
        $sisterView = (new RecipientReconciler)->forCustomer($this->organization, $sister);
        $this->assertSame(0.0, $sisterView['free']);
        $this->assertStringContainsString('EcoTec Service GmbH', $sisterView['lines'][0]['periods'][0]);
    }
}
