<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingVoucherPartyDocumentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\DocumentOrigin;
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Supplier\Supplier;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\{OrgaMaxConnection, OrgaMaxInvoice};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Dto\PartyDocument;
use App\Services\Billing\PartyDocumentSources;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegliste der Kunden- und Lieferantenakte aus allen Spiegeln (MVP-1496):
 * der allgemeine Belegspiegel je Programm (orgaMAX seit MVP-1501),
 * Original-PDF über die Link-Quelle des Programms.
 */
final class AccountingVoucherPartyDocumentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->travelTo('2026-09-15');
    }

    private function enable(string $pluginId): void {
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k-' . $pluginId]]);
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

    /** @return array<string, string> */
    private function range(): array {
        return ['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-01-01', 'ui.daterange.to' => '2026-12-31'];
    }

    public function test_customer_file_lists_mirrored_sevdesk_invoices_with_type_status_and_original_pdf(): void {
        $this->enable(SevDeskPlugin::ID);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Möwenblick Ferienhof']);
        $paid = $this->mirrored(DocumentOrigin::SevDesk, ['external_id' => 'invoice:31', 'customer_id' => $customer->id, 'voucher_number' => 'SD-2026-31', 'voucher_state' => 'paid', 'total_amount' => '714.00']);
        $this->mirrored(DocumentOrigin::SevDesk, ['external_id' => 'invoice:32', 'customer_id' => $customer->id, 'voucher_number' => 'SD-2026-32', 'due_date' => '2026-08-24', 'total_amount' => '119.00']);
        $this->mirrored(DocumentOrigin::SevDesk, ['customer_id' => $customer->id, 'document_kind' => 'credit_note', 'voucher_number' => 'SD-GS-3', 'total_amount' => '-20.00']);
        // Serienvorlage, gelöschter Beleg, fremder Kunde und Zeitraum fallen heraus.
        $this->mirrored(DocumentOrigin::SevDesk, ['customer_id' => $customer->id, 'document_kind' => 'recurring_template', 'direction' => 'neutral', 'voucher_number' => 'SD-SERIE']);
        $this->mirrored(DocumentOrigin::SevDesk, ['customer_id' => $customer->id, 'voucher_number' => 'SD-GELOESCHT', 'source_deleted_at' => now()]);
        $this->mirrored(DocumentOrigin::SevDesk, ['voucher_number' => 'SD-ANDERER']);
        $this->mirrored(DocumentOrigin::SevDesk, ['customer_id' => $customer->id, 'voucher_number' => 'SD-2025', 'voucher_date' => '2025-03-01']);

        $this->actingAs($this->admin);
        $lists = app(PartyDocumentSources::class)->forParty($customer, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $this->assertCount(1, $lists, 'nur das eingerichtete Programm liefert eine Liste');
        $this->assertSame('sevDesk', $lists[0]->source);
        $this->assertFalse($lists[0]->linked);
        /** @var array<string, array{0: string, 1: ?string}> $documents */
        $documents = [];
        foreach ($lists[0]->documents as $document) {
            $documents[(string) $document->number] = [$document->type, $document->status];
        }
        ksort($documents);
        $this->assertSame([
            'SD-2026-31' => ['salesinvoice', 'paid'],
            'SD-2026-32' => ['salesinvoice', 'overdue'],
            'SD-GS-3' => ['salescreditnote', 'open'],
        ], $documents, 'Art und Status je Beleg; offen und Fälligkeit überschritten ist überfällig');

        $response = $this->withSession($this->range())->get(route('customers.show', $customer))->assertOk();
        $response->assertSee('SD-2026-31')->assertSee('Rechnungen &amp; Belege', false)->assertSee('sevDesk')->assertSee('Überfällig')->assertDontSee('SD-SERIE')->assertDontSee('SD-ANDERER');
        $response->assertSee(route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $paid->id)), false);
    }

    public function test_supplier_file_lists_incoming_vouchers_and_the_list_stays_hidden_without_an_active_program(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Tauwerk Nord GmbH']);
        $this->mirrored(DocumentOrigin::Akaunting, ['supplier_id' => $supplier->id, 'direction' => 'incoming', 'voucher_type' => 'bill', 'voucher_number' => 'AK-BILL-7', 'total_amount' => '58.31']);
        $this->mirrored(DocumentOrigin::SevDesk, ['supplier_id' => $supplier->id, 'direction' => 'incoming', 'document_kind' => 'other', 'voucher_type' => 'C', 'voucher_number' => 'SD-BELEG-9', 'total_amount' => '12.50']);

        $this->actingAs($this->admin);
        $withoutProgram = app(PartyDocumentSources::class)->forParty($supplier, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $this->assertSame([], $withoutProgram, 'ohne eingerichtetes Programm keine Liste');

        $this->enable(SevDeskPlugin::ID);
        ExternalReference::query()->withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => SevDeskPlugin::ID, 'external_type' => 'contact',
            'referenceable_type' => $supplier->getMorphClass(), 'referenceable_id' => $supplier->id, 'external_id' => 'sd-contact-9',
        ]);
        $lists = app(PartyDocumentSources::class)->forParty($supplier, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $this->assertCount(1, $lists);
        $this->assertTrue($lists[0]->linked);
        $this->assertSame(['purchasevoucher'], array_map(static fn (PartyDocument $d): string => $d->type, $lists[0]->documents));

        $this->withSession($this->range())->get(route('suppliers.show', $supplier))->assertOk()
            ->assertSee('SD-BELEG-9')->assertSee('Eingangsbeleg')->assertDontSee('AK-BILL-7');
    }

    public function test_unlinked_program_without_documents_names_the_program_in_the_empty_state(): void {
        $this->enable(EasybillPlugin::ID);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ankerplatz Café']);

        $this->actingAs($this->admin)->withSession($this->range())->get(route('customers.show', $customer))->assertOk()
            ->assertSee('Kein easybill-Kontakt verknüpft');
    }

    public function test_orgamax_files_list_mirrored_invoices_and_expenses_while_the_connection_is_active(): void {
        // orgaMAX spiegelt seit MVP-1501 in den allgemeinen Belegspiegel; das PDF bleibt am Rechnungsspiegel.
        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => OrgaMaxPlugin::ID, 'enabled' => true, 'settings' => []]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Deichgraf Hotel']);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Leuchtturm Getränke']);
        ExternalReference::link($this->organization->id, OrgaMaxPlugin::ID, 'customer', $customer, '7');
        $connection = OrgaMaxConnection::query()->create([
            'organization_id' => $this->organization->id, 'mode' => OrgaMaxConnection::MODE_PRIVATE, 'api_key' => 'k', 'api_secret' => 's', 'ownership_id' => 'own-1',
            'bearer_token' => 'token', 'token_expires_at' => Carbon::now()->addHour(), 'status' => OrgaMaxConnectionStatus::Active,
            'capabilities' => ['billing' => ['enabled' => true, 'leader' => 'orgamax'], 'expenses' => ['enabled' => true, 'leader' => 'orgamax']],
        ]);
        $invoice = OrgaMaxInvoice::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => '41', 'customer_id' => $customer->id, 'invoice_type' => 'invoice',
            'invoice_status' => 'locked', 'invoice_number' => 'OM-2026-41', 'invoice_date' => '2026-07-01', 'due_on' => '2026-07-15', 'total_gross' => '476.00', 'currency' => 'EUR',
        ]);
        $this->mirrored(DocumentOrigin::OrgaMax, ['external_id' => '41', 'customer_id' => $customer->id, 'voucher_number' => 'OM-2026-41', 'voucher_date' => '2026-07-01', 'due_date' => '2026-07-15', 'total_amount' => '476.00']);
        $this->mirrored(DocumentOrigin::OrgaMax, ['external_id' => '42', 'customer_id' => $customer->id, 'voucher_number' => 'OM-VORLAGE', 'direction' => 'neutral', 'document_kind' => 'recurring_template', 'voucher_state' => 'draft']);
        $this->mirrored(DocumentOrigin::OrgaMax, ['external_id' => 'expense:9', 'supplier_id' => $supplier->id, 'voucher_number' => 'ER-77', 'direction' => 'incoming', 'document_kind' => 'expense', 'voucher_state' => 'paid', 'total_amount' => '59.50']);

        $this->actingAs($this->admin);
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2026-12-31');
        $lists = app(PartyDocumentSources::class)->forParty($customer, $from, $to);
        $this->assertSame(['orgaMAX'], array_map(static fn ($list): string => $list->source, $lists));
        $this->assertTrue($lists[0]->linked, 'Die Importreferenz `customer` zählt als Verknüpfung.');
        $this->assertSame([['OM-2026-41', 'salesinvoice', 'overdue', 476.0]], array_map(static fn (PartyDocument $d): array => [$d->number, $d->type, $d->status, $d->amount], $lists[0]->documents));
        $supplierLists = app(PartyDocumentSources::class)->forParty($supplier, $from, $to);
        $this->assertSame([['ER-77', 'purchasevoucher', 'paid']], array_map(static fn (PartyDocument $d): array => [$d->number, $d->type, $d->status], $supplierLists[0]->documents));

        $this->withSession($this->range())->get(route('customers.show', $customer))->assertOk()
            ->assertSee('OM-2026-41')->assertDontSee('OM-VORLAGE')
            ->assertSee(route('admin.orgamax.invoices.mirror-pdf', Sqid::encode(OrgaMaxInvoice::class, (int) $invoice->id)), false);

        $connection->forceFill(['status' => OrgaMaxConnectionStatus::Disconnected])->save();
        $this->assertSame([], app(PartyDocumentSources::class)->forParty($customer, $from, $to));
    }
}
