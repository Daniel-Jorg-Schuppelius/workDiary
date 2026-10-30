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

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Reselling\PeriodStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\BillingTransfer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Reselling\Draft\{InvoiceDraftTargets, LocalInvoiceDraftTarget};
use App\Services\Reselling\Register\{PeriodPlanner, ResaleInvoiceDraftService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Entwurfsziele des Reselling-Registers (Feature 152, Review 2026-09-11;
 * Feature 185, MVP-1423): lokal der Rechnungsentwurf, bei externer Hoheit
 * eine Übergabe (Feature 045) an das Faktura-Ziel des Programms — angelegt,
 * bestätigt, übertragen; externe Hoheit ohne Ziel als übersetzter 422-Text,
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

    public function test_registry_holds_only_the_local_target_and_external_billing_resolves_a_transfer_target(): void {
        $targets = app(InvoiceDraftTargets::class);
        $this->assertInstanceOf(LocalInvoiceDraftTarget::class, $targets->local());
        $this->assertCount(1, $targets->all(), 'kein Plugin-Entwurfsziel mehr — extern läuft über die Übergabe (MVP-1423)');

        $service = app(ResaleInvoiceDraftService::class);
        $lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Lexoffice]);
        $datev = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Datev]);
        $this->assertSame(TransferTarget::Lexoffice, $service->transferTargetFor($lexoffice));
        $this->assertSame(TransferTarget::File, $service->transferTargetFor($datev), 'DATEV: Datei-Paket wie in den Finanzen');
        $this->assertSame(TransferChannel::Resale, app(\App\Services\Reselling\Register\ResalePeriodTransferSource::class)->channel());
        $this->assertSame(\App\Enums\User\Permission::ResellingInvoice, TransferChannel::Resale->permission());
    }

    public function test_local_billing_drafts_locally_with_service_period_and_the_mirror_reports_the_issued_invoice(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
        $subscription = $this->subscription($customer);

        $result = app(ResaleInvoiceDraftService::class)->draft($this->organization, $customer, $admin);

        $this->assertSame(LocalInvoiceDraftTarget::KEY, $result['target']);
        $this->assertTrue($result['local']);
        $this->assertSame(2, $result['lines']);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame($invoice->number, $result['draft_id']);
        $this->assertSame(route('invoices.show', $invoice), $result['url']);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        // Leistungszeitraum je Position = Abo-Periode; Leistungsdatum bleibt der Beginn.
        $items = $invoice->items()->orderBy('position')->get();
        $this->assertSame(['2025-08-05', '2026-08-05'], $items->map(static fn(InvoiceItem $i): ?string => $i->service_from?->toDateString())->all());
        $this->assertSame(['2026-08-04', '2027-08-04'], $items->map(static fn(InvoiceItem $i): ?string => $i->service_to?->toDateString())->all());
        $this->assertSame('2025-08-05', $items->first()?->service_date?->toDateString());
        // Zeitraum nur in den Spalten — auf Beleg und Seite als eigene Zeile, nicht mehr im Positionstext (Review 2026-09-11, Kleinigkeiten UI).
        $this->assertStringNotContainsString('05.08.2025', (string) $items->first()?->description, 'Positionstext ohne Zeitraum');
        $this->assertSame('05.08.2025 – 04.08.2026', $items->first()?->servicePeriodLabel());

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
        Invoice::query()->whereKey($invoice->id)->update(['status' => InvoiceStatus::Issued, 'issued_on' => '2026-09-04']);
        $this->assertTrue($period->fresh()?->load('links')->draftIsInvoiced() ?? false, 'Rechnungsnummer ausgestellt = Entwurf wurde Rechnung');
    }

    public function test_lexoffice_billing_transfers_via_the_facturation_target_and_the_mirrored_voucher_closes_the_stamp(): void {
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH', 'billing_mode' => BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => '7c6b5a49-3827-4e16-9f05-e4d3c2b1a098', 'referenceable_type' => $partner->getMorphClass(), 'referenceable_id' => $partner->getKey(),
        ]);
        $subscription = $this->subscription($partner);
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices*' => static fn() => FakePluginHttp::response(['id' => 'draft-77', 'resourceUri' => 'https://api.lexware.io/v1/invoices/draft-77'], 201),
        ]);

        $result = app(ResaleInvoiceDraftService::class)->draft($this->organization, $partner, $admin);

        // Übergabe (Feature 045) statt Plugin-Entwurf: angelegt, bestätigt, übertragen; Positionen eingefroren.
        $this->assertSame(TransferTarget::Lexoffice->value, $result['target']);
        $this->assertFalse($result['local']);
        $this->assertSame('draft-77', $result['draft_id'], 'Stempel = Belegkennung in Lexoffice');
        $transfer = BillingTransfer::query()->where('customer_id', $partner->id)->firstOrFail();
        $this->assertSame(route('finance.transfers.show', $transfer), $result['url']);
        $this->assertSame(TransferChannel::Resale, $transfer->channel);
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertSame(2, $transfer->positions()->count(), 'eine Position je Periode');
        $this->assertSame(2, $result['lines']);
        $this->assertSame('draft-77', $transfer->externalReference?->external_id);
        $this->assertSame(0, Invoice::query()->count(), 'kein lokaler Entwurf');
        $this->assertSame(0, ResalePeriodLink::query()->count(), 'Übergaben werden nicht gespiegelt — kein Bezug');
        $period = $subscription->periods()->orderBy('starts_on')->firstOrFail();
        $this->assertSame(PeriodStatus::Open, $period->status, 'Übergabe entscheidet nichts');
        $this->assertSame('draft-77', $period->draft_reference);
        $this->assertStringContainsString('draft-77', (string) $period->note);

        // Spiegel: derselbe Beleg als Entwurf gespiegelt → noch keine Rechnung; abgeschlossen → Stempel darf fallen.
        $period->load('links');
        $this->assertFalse($period->draftIsInvoiced());
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'draft-77', 'contact_external_id' => '7c6b5a49-3827-4e16-9f05-e4d3c2b1a098', 'voucher_type' => 'invoice',
            'voucher_status' => 'draft', 'voucher_number' => null, 'voucher_date' => '2026-09-04', 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $this->assertFalse($period->fresh()?->load('links')->draftIsInvoiced() ?? true, 'gespiegelter Entwurf ist keine Rechnung');
        $voucher->forceFill(['voucher_status' => 'open', 'voucher_number' => 'RE/2026/0900'])->save();
        $this->assertTrue($period->fresh()?->load('links')->draftIsInvoiced() ?? false, 'abgeschlossener Beleg mit der Entwurfs-ID');

        // Storno der Übergabe nimmt den Stempel zurück; die Perioden sind wieder entwurfsfähig.
        app(\App\Services\Finance\BillingTransferService::class)->cancel($transfer, 'Test', $admin);
        $this->assertNull($period->fresh()?->draft_reference);
    }

    public function test_lexoffice_draft_lines_carry_the_article_id_when_the_subscription_has_a_catalog_article(): void {
        // Feature 185 (MVP-1408): mit Artikel als `service` + Artikel-ID, damit die fertige Rechnung als Lizenzposition zurückkommt.
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH', 'billing_mode' => BillingMode::Lexoffice]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => '1b2c3d4e-5f60-4718-8a9b-0c1d2e3f4a5b', 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);
        $article = \App\Plugins\Lexoffice\Models\LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => '9d6e1f32-5b8a-4c7d-9e0f-1a2b3c4d5e6f', 'name' => 'Microsoft 365 Business Premium', 'article_number' => 'BP',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '20.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->subscription($customer, ['article_ref' => 'lex:' . $article->id]);
        $payloads = [];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices*' => static function (\Psr\Http\Message\RequestInterface $request) use (&$payloads) {
                $payloads[] = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);

                return FakePluginHttp::response(['id' => 'draft-78', 'resourceUri' => 'https://api.lexware.io/v1/invoices/draft-78'], 201);
            },
        ]);

        app(ResaleInvoiceDraftService::class)->draft($this->organization, $customer, $admin);

        $this->assertCount(1, $payloads);
        $items = $payloads[0]['lineItems'] ?? [];
        $this->assertNotEmpty($items);
        $this->assertSame('service', $items[0]['type']);
        $this->assertSame('9d6e1f32-5b8a-4c7d-9e0f-1a2b3c4d5e6f', $items[0]['id']);
        $this->assertSame('Monat', $items[0]['unitName']);
    }

    public function test_datev_billing_transfers_as_a_file_package_and_stamps_the_periods(): void {
        \Illuminate\Support\Facades\Storage::fake(\App\Services\Finance\Targets\FileTarget::DISK);
        $admin = $this->orgAdmin();
        $datev = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'DATEV-Kunde', 'billing_mode' => BillingMode::Datev]);
        $subscription = $this->subscription($datev);

        $result = app(ResaleInvoiceDraftService::class)->draft($this->organization, $datev, $admin);

        $transfer = BillingTransfer::query()->where('customer_id', $datev->id)->firstOrFail();
        $this->assertSame(TransferTarget::File->value, $result['target']);
        $this->assertSame('transfer:' . $transfer->id, $result['draft_id'], 'ohne Fremdreferenz stempelt die Übergabe selbst');
        $this->assertSame(TransferStatus::Transferred, $transfer->status);
        $this->assertNotNull($transfer->file_path);
        \Illuminate\Support\Facades\Storage::disk(\App\Services\Finance\Targets\FileTarget::DISK)->assertExists((string) $transfer->file_path);
        $this->assertStringContainsString('Microsoft 365 Business Premium', (string) \Illuminate\Support\Facades\Storage::disk(\App\Services\Finance\Targets\FileTarget::DISK)->get((string) $transfer->file_path));
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame(2, $subscription->periods()->where('draft_reference', 'transfer:' . $transfer->id)->count(), 'beide Perioden gestempelt');

        // Im Dialog: Erfolg mit dem Ziel der Übergabe.
        $this->actingAs($admin)->from(route('finance.resale.periods.index'))->post(route('finance.resale.periods.draft.store'), ['customer_id' => $datev->sqid])
            ->assertRedirect(route('finance.resale.periods.index'))
            ->assertSessionHasErrors(['customer_id']); // alles schon im Entwurf: Hinweis auf die ausstehende Übergabe
    }

    public function test_an_inactive_plugin_leaves_a_failed_transfer_and_an_unstamped_period(): void {
        $admin = $this->orgAdmin();
        // Plugin nicht aktiviert: das Ziel scheitert, der Nachweis bleibt „fehlgeschlagen" (erneut übertragbar), kein Stempel.
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Lexoffice]);
        $subscription = $this->subscription($partner);

        try {
            app(ResaleInvoiceDraftService::class)->draft($this->organization, $partner, $admin);
            $this->fail('Plugin inaktiv');
        } catch (\RuntimeException $e) {
            $prefix = (string) __('resale.draft.transfer_failed', ['target' => TransferTarget::Lexoffice->label(), 'error' => '']);
            $this->assertStringStartsWith(rtrim($prefix), $e->getMessage());
        }
        $transfer = BillingTransfer::query()->where('customer_id', $partner->id)->firstOrFail();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertNotNull($transfer->failure_reason);
        $this->assertSame(0, $subscription->periods()->whereNotNull('draft_reference')->count(), 'nichts gestempelt');
    }
}
