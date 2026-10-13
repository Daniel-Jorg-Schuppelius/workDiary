<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelationsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\DocumentRelationKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Finance\{AccountingVoucher, BillingTransfer};
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Sales\Quote;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillTarget;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\LexofficeInvoiceService;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxTarget;
use App\Plugins\SevDesk\Services\{SevDeskInvoiceNumberPull, SevDeskTarget};
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\DocumentRelations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145, P1 (MVP-1157–1159): Belegverbund lokal und im Buchhaltungsprogramm. */
final class DocumentRelationsTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->admin);
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(string $number, array $attributes = []): Invoice {
        return Invoice::query()->create($attributes + [
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => $number,
            'status' => InvoiceStatus::Issued,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'issued_on' => now()->toDateString(),
        ]);
    }

    /** @return array<string, DocumentRelationKind> Nummer => Bezug */
    private function relationsOf(\Illuminate\Database\Eloquent\Model $document): array {
        $result = [];
        foreach (app(DocumentRelations::class)->of($document) as $relation) {
            $result[(string) $relation['node']->number] = $relation['kind'];
        }

        return $result;
    }

    public function test_quote_invoice_correction_and_down_payment_form_one_group(): void {
        $quote = Quote::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id, 'number' => 'AN-1']);
        $downPayment = $this->invoice('R-1', ['type' => Invoice::TYPE_DOWN_PAYMENT, 'quote_id' => $quote->id]);
        $final = $this->invoice('R-2', ['type' => Invoice::TYPE_FINAL, 'quote_id' => $quote->id]);
        InvoiceItem::query()->create(['organization_id' => $this->org->id, 'invoice_id' => $final->id, 'description' => 'Abschlag', 'quantity' => '1', 'unit_price' => '-100', 'settled_invoice_id' => $downPayment->id, 'position' => 1]);
        $this->invoice('S-1', ['type' => Invoice::TYPE_CANCELLATION, 'parent_invoice_id' => $final->id, 'status' => InvoiceStatus::Draft]);

        $this->assertEquals([
            'AN-1' => DocumentRelationKind::Source,
            'R-1' => DocumentRelationKind::Settles,
            'S-1' => DocumentRelationKind::CorrectedBy,
        ], array_intersect_key($this->relationsOf($final), ['AN-1' => 0, 'R-1' => 0, 'S-1' => 0]));
        $this->assertSame(DocumentRelationKind::SettledBy, $this->relationsOf($downPayment)['R-2']);
        $this->assertSame(['R-1', 'R-2', 'S-1'], array_values(array_intersect(array_keys($this->relationsOf($quote)), ['R-1', 'R-2', 'S-1'])));
    }

    public function test_other_organizations_are_never_reached(): void {
        $other = Organization::factory()->create();
        $original = $this->invoice('R-10');
        // Fremde Gutschrift mit derselben Ursprungs-ID darf nicht auftauchen.
        Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $other->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $other->id])->id,
            'number' => 'FREMD-1',
            'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'parent_invoice_id' => $original->id,
            'currency' => 'EUR',
        ]);

        $this->assertArrayNotHasKey('FREMD-1', $this->relationsOf($original));
    }

    public function test_lexoffice_counterpart_and_its_related_down_payment(): void {
        $invoice = $this->invoice('RE-100');
        $this->reference(LexofficePlugin::ID, LexofficeInvoiceService::EXT_TYPE_INVOICE, $invoice, 'lex-1');
        $this->voucher('lex-1', 'invoice', 'RE-100', ['lex-2']);
        $this->voucher('lex-2', 'downpaymentinvoice', 'AR-7');

        $byNumber = [];
        foreach (app(DocumentRelations::class)->of($invoice) as $relation) {
            $byNumber[(string) $relation['node']->number] = $relation;
        }
        $this->assertArrayHasKey('RE-100', $byNumber);
        $this->assertArrayHasKey('AR-7', $byNumber);

        $this->assertSame(DocumentRelationKind::Counterpart, $byNumber['RE-100']['kind']);
        $this->assertSame('Lexoffice', $byNumber['RE-100']['node']->system);
        $this->assertTrue($byNumber['RE-100']['node']->dialog);
        $this->assertSame(DocumentRelationKind::Related, $byNumber['AR-7']['kind']);
        $this->assertSame('RE-100', $byNumber['AR-7']['via']?->number);
    }

    public function test_transfer_counterparts_of_easybill_sevdesk_and_orgamax(): void {
        $transfer = BillingTransfer::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        $this->reference(EasybillPlugin::ID, EasybillTarget::EXT_TYPE_INVOICE, $transfer, 'eb-1', payload: ['document' => ['number' => 'EB-55', 'is_draft' => false]]);
        $this->reference(SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE, $transfer, 'sd-1', number: 'RE-SD-9', payload: ['status' => '200']);
        $this->reference(OrgaMaxPlugin::ID, OrgaMaxTarget::EXT_TYPE_ORDER, $transfer, 'om-7');
        ExternalReference::query()->create([
            'organization_id' => $this->org->id, 'plugin_id' => OrgaMaxPlugin::ID, 'external_type' => 'orgamax_converted_invoice',
            'referenceable_type' => $transfer->getMorphClass(), 'referenceable_id' => $transfer->id,
            'external_id' => 'order:om-7', 'payload' => ['invoice_id' => '501', 'invoice_number' => 'RE-OM-501'],
        ]);

        $relations = $this->relationsOf($transfer);

        $this->assertSame(DocumentRelationKind::Counterpart, $relations['EB-55']);
        $this->assertSame(DocumentRelationKind::Counterpart, $relations['RE-SD-9']);
        $this->assertSame(DocumentRelationKind::FollowUp, $relations['RE-OM-501']);
    }

    public function test_easybill_cancellation_in_the_mirror(): void {
        $transfer = BillingTransfer::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        $this->reference(EasybillPlugin::ID, EasybillTarget::EXT_TYPE_INVOICE, $transfer, 'eb-2');
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_id' => 'eb-2', 'voucher_number' => 'EB-60', 'document_kind' => 'invoice']);
        AccountingVoucher::factory()->create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_id' => 'eb-3', 'voucher_number' => 'ST-60', 'document_kind' => 'cancellation', 'is_cancellation' => true, 'cancels_external_id' => 'eb-2']);

        $relations = $this->relationsOf($transfer);

        $this->assertSame(DocumentRelationKind::Counterpart, $relations['EB-60']);
        $this->assertSame(DocumentRelationKind::CorrectedBy, $relations['ST-60']);
    }

    public function test_invoice_page_shows_the_related_documents(): void {
        $original = $this->invoice('R-20');
        $this->invoice('G-20', ['type' => Invoice::TYPE_CREDIT_NOTE, 'parent_invoice_id' => $original->id]);

        $this->get(route('invoices.show', $original))
            ->assertOk()
            ->assertSee(__('billing.relations.title', ['count' => 1]))
            ->assertSee('G-20');
    }

    public function test_sevdesk_pull_fills_number_and_status(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => SevDeskPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        $transfer = BillingTransfer::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $this->customer->id]);
        $reference = $this->reference(SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE, $transfer, '9001');
        FakePluginHttp::fake([
            'https://my.sevdesk.de/api/v1/Invoice/9001' => FakePluginHttp::response(['objects' => [['id' => '9001', 'invoiceNumber' => 'RE-1043', 'status' => '200']]]),
        ]);

        $this->assertSame(1, app(SevDeskInvoiceNumberPull::class)->pull($this->org->id));

        $reference->refresh();
        $this->assertSame('RE-1043', $reference->external_number);
        $this->assertSame('200', $reference->payload['status'] ?? null);
    }

    /** @param array<string, mixed> $payload */
    private function reference(string $pluginId, string $type, \Illuminate\Database\Eloquent\Model $model, string $externalId, ?string $number = null, array $payload = []): ExternalReference {
        return ExternalReference::query()->create([
            'organization_id' => $this->org->id,
            'plugin_id' => $pluginId,
            'external_type' => $type,
            'referenceable_type' => $model->getMorphClass(),
            'referenceable_id' => $model->getKey(),
            'external_id' => $externalId,
            'external_number' => $number,
            'payload' => $payload,
            'synced_at' => now(),
        ]);
    }

    /** @param list<string> $related */
    private function voucher(string $externalId, string $type, string $number, array $related = []): LexofficeVoucher {
        return LexofficeVoucher::query()->create([
            'organization_id' => $this->org->id,
            'external_id' => $externalId,
            'voucher_type' => $type,
            'voucher_status' => 'open',
            'voucher_number' => $number,
            'voucher_date' => now()->toDateString(),
            'archived' => false,
            'related_voucher_ids' => $related !== [] ? $related : null,
        ]);
    }
}
