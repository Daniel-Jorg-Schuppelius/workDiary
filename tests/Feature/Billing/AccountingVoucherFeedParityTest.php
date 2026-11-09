<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherFeedParityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\DocumentOrigin;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{PluginSetting, User};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillTarget;
use App\Plugins\SevDesk\Services\SevDeskTarget;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\{DocumentFeedFilters, DocumentFeedQuery};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Buchhaltungs-Parität im Belegfluss (Phase 173): Dublettenregel 1 für die
 * Programme des allgemeinen Belegspiegels (MVP-1495) und das Original-PDF
 * gespiegelter sevDesk- und easybill-Belege (MVP-1499).
 */
final class AccountingVoucherFeedParityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Leuchtturm Segelschule']);
    }

    private function invoice(string $number, string $total, ?Customer $customer = null): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE,
            'category' => Invoice::CATEGORY_SERVICE,
            'issued_on' => '2026-08-10',
            'currency' => 'EUR',
            'total' => $total,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function mirrored(DocumentOrigin $origin, array $attributes): AccountingVoucher {
        return AccountingVoucher::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'plugin_id' => $origin->value,
            'external_id' => uniqid($origin->value . '-', true),
            'direction' => 'outgoing',
            'document_kind' => 'invoice',
            'voucher_type' => 'invoice',
            'voucher_state' => 'open',
            'voucher_date' => '2026-08-10',
            'currency' => 'EUR',
            'archived' => false,
        ]);
    }

    private function transferred(Invoice $invoice, string $pluginId, string $type, string $externalId): void {
        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_type' => $type,
            'referenceable_type' => $invoice->getMorphClass(), 'referenceable_id' => $invoice->id, 'external_id' => $externalId,
        ]);
    }

    private function filters(): DocumentFeedFilters {
        return new DocumentFeedFilters(
            organizationId: (int) $this->organization->id,
            userId: (int) $this->admin->id,
            from: CarbonImmutable::parse('2026-01-01')->startOfDay(),
            to: CarbonImmutable::parse('2026-12-31')->endOfDay(),
            allExpenses: true,
            sources: ['invoice' => true, 'quote' => true, 'voucher' => true, 'incoming_einvoice' => true, 'expense' => true],
        );
    }

    /** @return list<string> Zeilenart je Belegnummer */
    private function rowsFor(string $number): array {
        $types = [];
        foreach ((new DocumentFeedQuery($this->filters()))->paginate(50, 'date', 'desc')->items() as $row) {
            if ($row instanceof \stdClass && $row->number === $number) {
                $types[] = (string) $row->source_type;
            }
        }

        return $types;
    }

    private function revenue(): float {
        return (float) ((new DocumentFeedQuery($this->filters()))->totals()[0]['revenue'] ?? 0.0);
    }

    public function test_invoice_numbered_by_sevdesk_appears_once_even_before_the_contact_is_resolved(): void {
        $invoice = $this->invoice('RE-2026-0412', '595.00');
        $this->transferred($invoice, SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE, '9001');
        // Ohne Kontaktverknüpfung kennt der Spiegel den Kunden noch nicht.
        $this->mirrored(DocumentOrigin::SevDesk, ['external_id' => 'invoice:9001', 'voucher_number' => 'RE-2026-0412', 'total_amount' => '595.00']);

        $this->assertSame(['voucher'], $this->rowsFor('RE-2026-0412'), 'Extern führt: die übertragene Rechnung steht nur als Spiegelbeleg.');
        $this->assertEqualsWithDelta(595.0, $this->revenue(), 0.001, 'Umsatz einmal, nicht doppelt.');
    }

    public function test_invoice_numbered_by_easybill_appears_once(): void {
        $invoice = $this->invoice('EB-1007', '238.00');
        $this->transferred($invoice, EasybillPlugin::ID, EasybillTarget::EXT_TYPE_INVOICE, '77001');
        $this->mirrored(DocumentOrigin::Easybill, ['external_id' => '77001', 'customer_id' => $this->customer->id, 'voucher_number' => 'EB-1007', 'total_amount' => '238.00']);

        $this->assertSame(['voucher'], $this->rowsFor('EB-1007'));
        $this->assertEqualsWithDelta(238.0, $this->revenue(), 0.001);
    }

    public function test_same_number_for_the_same_customer_is_one_invoice_in_every_mirrored_program(): void {
        // Eine Rechnungsnummer gibt es je Unternehmer nur einmal (§ 14 UStG); importierte Altrechnung mit Programmnummer.
        $invoice = $this->invoice('RE/2026/0099', '120.00');
        $invoice->forceFill(['external_number' => 'AK-2026-31'])->save();
        $this->mirrored(DocumentOrigin::Akaunting, ['customer_id' => $this->customer->id, 'voucher_number' => 'AK-2026-31', 'total_amount' => '120.00']);

        $this->assertSame([], $this->rowsFor('RE/2026/0099'), 'Die lokale Rechnung tritt hinter den Spiegelbeleg zurück.');
        $this->assertSame(['voucher'], $this->rowsFor('AK-2026-31'));
        $this->assertEqualsWithDelta(120.0, $this->revenue(), 0.001);
    }

    public function test_foreign_numbers_never_displace_a_local_invoice(): void {
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kranich Bootsbau']);
        $this->invoice('RE-500', '100.00');
        // Gleiche Nummer, aber anderer Kunde und keine Übertragung: zwei verschiedene Rechnungen.
        $this->mirrored(DocumentOrigin::JtlWawi, ['customer_id' => $other->id, 'voucher_number' => 'RE-500', 'total_amount' => '40.00']);
        // Unbekannter Kunde ohne Übertragung: ebenfalls kein Beweis.
        $this->mirrored(DocumentOrigin::InvoiceNinja, ['voucher_number' => 'RE-500', 'total_amount' => '7.00']);
        // Eingangsbeleg und gelöschter Beleg mit gleicher Nummer verdrängen nie.
        $this->mirrored(DocumentOrigin::SevDesk, ['customer_id' => $this->customer->id, 'direction' => 'incoming', 'voucher_number' => 'RE-500', 'total_amount' => '3.00']);
        $this->mirrored(DocumentOrigin::Easybill, ['customer_id' => $this->customer->id, 'voucher_number' => 'RE-500', 'total_amount' => '5.00', 'source_deleted_at' => now()]);

        $this->assertContains('invoice', $this->rowsFor('RE-500'), 'Die lokale Rechnung bleibt sichtbar.');
        $this->assertEqualsWithDelta(147.0, $this->revenue(), 0.001, 'Lokal 100 + JTL 40 + Invoice Ninja 7; Eingang und gelöschter Beleg zählen nicht als Umsatz.');
    }

    public function test_mirrored_vouchers_sum_by_kind_like_lexoffice_and_skip_drafts(): void {
        $this->mirrored(DocumentOrigin::Akaunting, ['direction' => 'incoming', 'voucher_number' => 'AK-BILL-1', 'total_amount' => '200.00']);
        $this->mirrored(DocumentOrigin::Akaunting, ['direction' => 'incoming', 'document_kind' => 'credit_note', 'voucher_number' => 'AK-GS-1', 'total_amount' => '-50.00']);
        $this->mirrored(DocumentOrigin::Akaunting, ['direction' => 'incoming', 'voucher_state' => 'draft', 'voucher_number' => 'AK-ENTWURF', 'total_amount' => '999.00']);
        $this->mirrored(DocumentOrigin::SevDesk, ['voucher_number' => 'SD-RE-1', 'total_amount' => '300.00']);
        $this->mirrored(DocumentOrigin::SevDesk, ['voucher_state' => 'cancelled', 'voucher_number' => 'SD-RE-2', 'total_amount' => '80.00']);

        $totals = (new DocumentFeedQuery($this->filters()))->totals()[0];

        $this->assertEqualsWithDelta(150.0, $totals['expense'], 0.001, 'Eingangsrechnung zählt positiv, Gutschrift mindert, Entwurf zählt nicht');
        $this->assertEqualsWithDelta(300.0, $totals['revenue'], 0.001, 'storniert zählt nicht');
        $this->assertEqualsWithDelta(150.0, $totals['balance'], 0.001);
    }

    public function test_sevdesk_invoice_and_easybill_document_offer_the_original_pdf_in_the_feed(): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'sd-key']]);
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'eb-key']]);
        $sevInvoice = $this->mirrored(DocumentOrigin::SevDesk, ['external_id' => 'invoice:4711', 'voucher_number' => 'SD-RE-4711', 'total_amount' => '10.00']);
        $sevVoucher = $this->mirrored(DocumentOrigin::SevDesk, ['external_id' => '815', 'direction' => 'incoming', 'document_kind' => 'other', 'voucher_type' => 'C', 'voucher_number' => 'SD-BELEG-815', 'total_amount' => '12.00']);
        $easybill = $this->mirrored(DocumentOrigin::Easybill, ['external_id' => '66001', 'voucher_number' => 'EB-66001', 'total_amount' => '14.00']);

        $content = (string) $this->actingAs($this->admin)->withSession($this->range())->get(route('billing.feed'))->assertOk()->getContent();

        $this->assertStringContainsString(route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $sevInvoice->id)), $content);
        $this->assertStringContainsString(route('finance.easybill.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $easybill->id)), $content);
        $this->assertStringNotContainsString(route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $sevVoucher->id)), $content, 'sevDesk-Belege aus /Voucher haben keinen belegten PDF-Abruf.');
        $this->assertStringContainsString('Original-PDF', $content);

        // Ohne Belegrecht keine Aktion.
        $clerk = $this->orgUser();
        $this->assertFalse($clerk->can('voucher.viewAny'));
        $links = app(\App\Services\Billing\Feed\DocumentFeedSourceRegistry::class)->voucherLinksFor(SevDeskPlugin::ID);
        $this->assertNotNull($links);
        $this->assertSame([], $links->actionsFor($sevInvoice, $clerk));
    }

    /** @return array<string, string> */
    private function range(): array {
        return ['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-01-01', 'ui.daterange.to' => '2026-12-31'];
    }
}
