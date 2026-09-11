<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DraftTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\PeriodStatus;
use App\Models\{Customer, ExternalReference, Invoice, InvoiceItem, LexofficeVoucher};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeInvoiceDraftTarget;
use App\Services\Reselling\Draft\{InvoiceDraftTargets, LocalInvoiceDraftTarget};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleInvoiceDraftService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Entwurfsziele des Reselling-Registers (Feature 152, Review 2026-09-11):
 * Registry mit lokalem Ziel und Plugin-Ziel, Weiche über die
 * Rechnungshoheit, externe Hoheit ohne Ziel als übersetzter 422-Text,
 * Leistungszeitraum je lokaler Position, „Entwurf wurde Rechnung" über den
 * Belegspiegel (lokal: Nummer ausgestellt, Lexoffice: Beleg gespiegelt).
 */
class DraftTargetTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(Customer $customer, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_registry_holds_the_local_target_and_the_plugin_target(): void {
        $targets = app(InvoiceDraftTargets::class);
        $this->assertInstanceOf(LocalInvoiceDraftTarget::class, $targets->local());
        $this->assertInstanceOf(LexofficeInvoiceDraftTarget::class, $targets->get(LexofficeInvoiceDraftTarget::KEY), 'Plugin registriert sein Ziel beim Boot');

        $local = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Workdiary]);
        $lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Lexoffice]);
        $datev = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Datev]);
        $this->assertTrue($targets->local()?->supports($local) ?? false);
        $this->assertNull($targets->externalFor($local), 'lokale Hoheit hat kein externes Ziel');
        $this->assertSame(LexofficeInvoiceDraftTarget::KEY, $targets->externalFor($lexoffice)?->key());
        $this->assertNull($targets->externalFor($datev), 'ohne Plugin-Ziel für DATEV');

        $service = app(ResaleInvoiceDraftService::class);
        $this->assertSame(LocalInvoiceDraftTarget::KEY, $service->targetFor($local)->key());
        $this->assertSame(LexofficeInvoiceDraftTarget::KEY, $service->targetFor($lexoffice)->key());
    }

    public function test_local_billing_drafts_locally_with_service_period_and_the_mirror_reports_the_issued_invoice(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $subscription = $this->subscription($customer);

        $result = app(ResaleInvoiceDraftService::class)->draft($this->organization, $customer, $admin);

        $this->assertSame(LocalInvoiceDraftTarget::KEY, $result['target']);
        $this->assertTrue($result['local']);
        $this->assertSame(2, $result['lines']);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($invoice->number, $result['draft_id']);
        $this->assertSame(route('invoices.show', $invoice), $result['url']);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        // Leistungszeitraum je Position = Abo-Periode; Leistungsdatum bleibt der Beginn.
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertSame(['2025-08-05', '2026-08-05'], $items->map(static fn(InvoiceItem $i): ?string => $i->service_from?->toDateString())->all());
        $this->assertSame(['2026-08-04', '2027-08-04'], $items->map(static fn(InvoiceItem $i): ?string => $i->service_to?->toDateString())->all());
        $this->assertSame('2025-08-05', $items->first()?->service_date?->toDateString());
        $this->assertStringContainsString('05.08.2025 – 04.08.2026', (string) $items->first()?->description, 'Zeitraum steht auch im Positionstext');

        // Kern stempelt und verknüpft: Bezug auf die Position, Periode „berechnet", Bemerkung ergänzt.
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $this->assertSame(PeriodStatus::Billed, $period->status);
        $this->assertSame($invoice->number, $period->draft_reference);
        $link = ResalePeriodLink::query()->where('period_id', $period->id)->firstOrFail();
        $this->assertSame((new InvoiceItem)->getMorphClass(), $link->linkable_type);
        $this->assertSame((int) $items->first()?->id, (int) $link->linkable_id);
        $this->assertSame((string) __('resale.draft.local_note', ['number' => $invoice->number]), $period->note);

        // Belegspiegel: Entwurf ist keine Rechnung; ausgestellt (Nummer vergeben) schon — ohne entschiedenen Bezug.
        $period->load('links');
        $this->assertFalse($period->draftIsInvoiced());
        Invoice::query()->whereKey($invoice->id)->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => '2026-09-04']);
        $this->assertTrue($period->fresh()?->load('links')->draftIsInvoiced() ?? false, 'Rechnungsnummer ausgestellt = Entwurf wurde Rechnung');
    }

    public function test_lexoffice_billing_drafts_via_the_plugin_target_and_the_mirrored_voucher_closes_the_stamp(): void {
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'LDS Systems GmbH', 'billing_mode' => BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-lds', 'referenceable_type' => $partner->getMorphClass(), 'referenceable_id' => $partner->getKey(),
        ]);
        $subscription = $this->subscription($partner);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices' => static fn() => FakePluginHttp::response(['id' => 'draft-77', 'resourceUri' => 'https://api.lexoffice.io/v1/invoices/draft-77'], 201),
        ]);

        $result = app(ResaleInvoiceDraftService::class)->draft($this->organization, $partner, $admin);

        $this->assertSame(LexofficeInvoiceDraftTarget::KEY, $result['target']);
        $this->assertFalse($result['local']);
        $this->assertSame('draft-77', $result['draft_id']);
        $this->assertNull($result['url']);
        $this->assertSame(0, Invoice::query()->count(), 'kein lokaler Entwurf');
        $this->assertSame(0, ResalePeriodLink::query()->count(), 'Lexoffice-Entwürfe werden nicht gespiegelt — kein Bezug');
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $this->assertSame(PeriodStatus::Open, $period->status, 'Entwurf entscheidet nichts');
        $this->assertSame('draft-77', $period->draft_reference);
        $this->assertStringContainsString('draft-77', (string) $period->note);

        // Spiegel: derselbe Beleg als Entwurf gespiegelt → noch keine Rechnung; abgeschlossen → Stempel darf fallen.
        $period->load('links');
        $this->assertFalse($period->draftIsInvoiced());
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'draft-77', 'contact_external_id' => 'c-lds', 'voucher_type' => 'invoice',
            'voucher_status' => 'draft', 'voucher_number' => null, 'voucher_date' => '2026-09-04', 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $this->assertFalse($period->fresh()?->load('links')->draftIsInvoiced() ?? true, 'gespiegelter Entwurf ist keine Rechnung');
        $voucher->forceFill(['voucher_status' => 'open', 'voucher_number' => 'RE/2026/0900'])->save();
        $this->assertTrue($period->fresh()?->load('links')->draftIsInvoiced() ?? false, 'abgeschlossener Beleg mit der Entwurfs-ID');
    }

    public function test_external_billing_without_a_registered_target_is_a_translated_error(): void {
        $admin = $this->orgAdmin();
        $datev = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'DATEV-Kunde', 'billing_mode' => BillingMode::Datev]);
        $subscription = $this->subscription($datev);
        $expected = (string) __('resale.draft.no_target', ['mode' => BillingMode::Datev->label()]);

        try {
            app(ResaleInvoiceDraftService::class)->draft($this->organization, $datev, $admin);
            $this->fail('ohne Ziel kein Entwurf');
        } catch (\RuntimeException $e) {
            $this->assertSame($expected, $e->getMessage());
        }
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(0, $subscription->periods()->whereNotNull('draft_reference')->count(), 'nichts gestempelt');

        // Im Dialog: 422 mit demselben Text (Review 2026-09-10, C1).
        $this->actingAs($admin)->postJson(route('finance.resale.periods.draft.store'), ['customer_id' => $datev->sqid])
            ->assertStatus(422)
            ->assertJsonPath('errors.customer_id.0', $expected);
    }

    public function test_lexoffice_target_reports_an_inactive_plugin_before_building_lines(): void {
        $admin = $this->orgAdmin();
        // Plugin nicht aktiviert, Empfänger ohne Perioden: die Vorbedingung des Ziels kommt vor „nichts offen".
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Lexoffice]);

        try {
            app(ResaleInvoiceDraftService::class)->draft($this->organization, $partner, $admin);
            $this->fail('Plugin inaktiv');
        } catch (\RuntimeException $e) {
            $this->assertSame((string) __('resale.draft.error.lexoffice'), $e->getMessage());
        }
    }
}
