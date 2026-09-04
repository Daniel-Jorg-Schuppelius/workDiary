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
use App\Models\{Customer, ExternalReference, ForeignCustomer, LexofficeArticle};
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

        $this->actingAs($admin)->get(route('finance.resale.prices'))
            ->assertOk()
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('228,72 €')
            ->assertSee(__('resale.prices.flag.contract_above_catalog'), false)
            ->assertSee('126,00 €')
            ->assertDontSee(__('resale.prices.flag.below_purchase'), false);
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
        $this->assertSame('98880', (string) $invoice->subtotal?->getMinorAmount(), '2 Perioden × 2 × 247,20');
        foreach ($subscription->periods()->get() as $period) {
            $this->assertSame(PeriodStatus::Billed, $period->status);
            $this->assertTrue($period->isProposedOnly(), 'Bezug auf die lokale Rechnungsposition ist ein Vorschlag');
            $this->assertSame(\App\Models\InvoiceItem::class, $period->links()->first()?->linkable_type);
        }
        // Der Vorschlagslauf lässt lokale Rechnungsbezüge stehen.
        app(\App\Services\Reselling\Register\LinkProposer::class)->propose($this->organization);
        $this->assertSame(2, \App\Models\Reselling\ResalePeriodLink::query()->count());
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

        $period = $subscription->periods()->first();
        $this->assertSame(PeriodStatus::Open, $period?->status, 'Entwurf entscheidet nichts');
        $this->assertStringContainsString('draft-42', (string) $period?->note);

        // Ohne Lexoffice: verständliche Fehlermeldung statt Absturz.
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ohne Kontakt', 'billing_mode' => \App\Enums\Finance\BillingMode::Lexoffice]);
        $this->actingAs($admin)->post(route('finance.resale.periods.draft.store'), ['customer_id' => $other->sqid])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHas('error');
    }
}
