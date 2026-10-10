<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollectiveContactTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Stammdaten;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind, InvoiceStatus};
use App\Exceptions\CollectiveContactException;
use App\Models\Document\Document;
use App\Models\Invoicing\{IncomingEInvoice, Invoice, InvoiceSenderRule};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Billing\Sepa\PaymentProposalService;
use App\Services\CustomerPortal\PortalAccessService;
use App\Services\Integration\Match\EntityMatcher;
use App\Services\Integration\Profiles\SupplierMatchProfile;
use App\Services\Invoicing\EInvoice\IncomingInvoiceMatcher;
use App\Services\Invoicing\{InvoiceIssueException, InvoiceIssueService};
use App\Services\Stammdaten\{CollectiveContacts, SupplierMergeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Feature 163, MVP-1109: Sammellieferant und Sammelkunde. */
final class CollectiveContactTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => ['vat_id' => 'DE811907980']]]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_there_is_exactly_one_collective_supplier_and_customer_per_organization(): void {
        $contacts = app(CollectiveContacts::class);

        $supplier = $contacts->supplier($this->organization);
        $customer = $contacts->customer($this->organization);

        $this->assertTrue($supplier->is($contacts->supplier($this->organization)));
        $this->assertTrue($customer->is($contacts->customer($this->organization)));
        $this->assertTrue($supplier->is_collective);
        $this->assertSame(__('Sammellieferant'), $supplier->name);
        $this->assertSame(1, Supplier::query()->where('is_collective', true)->count());
    }

    public function test_collective_contacts_never_match_by_identifiers_or_in_suggestion_profiles(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $collective->forceFill(['vat_id' => 'DE111111111'])->save();

        $incoming = $this->incoming(['seller_vat_id' => 'DE111111111']);

        $this->assertFalse(app(IncomingInvoiceMatcher::class)->autoAssign($incoming));
        $this->assertSame([], app(EntityMatcher::class)->match($this->organization, app(SupplierMatchProfile::class), ['name' => $collective->name])->candidates());
    }

    public function test_sender_rule_can_point_to_the_collective_supplier_but_not_for_reverse_charge(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        InvoiceSenderRule::query()->create([
            'organization_id' => $this->organization->id, 'email' => 'shop@baumarkt.example',
            'direction' => DocumentDirection::Incoming, 'supplier_id' => $collective->id, 'created_by' => $this->admin->id,
        ]);

        $domestic = $this->incoming(['sender_email' => 'shop@baumarkt.example']);
        $this->assertTrue(app(IncomingInvoiceMatcher::class)->autoAssign($domestic));
        $this->assertSame($collective->id, $domestic->refresh()->supplier_id);

        $reverseCharge = $this->incoming(['sender_email' => 'shop@baumarkt.example'], ['tax_breakdown' => [['category' => 'AE', 'percent' => 0.0, 'net' => '100.00', 'tax' => '0.00']]]);
        $this->assertFalse(app(IncomingInvoiceMatcher::class)->autoAssign($reverseCharge));
        $this->expectException(CollectiveContactException::class);
        app(IncomingInvoiceMatcher::class)->assign($reverseCharge, $collective, IncomingInvoiceMatchKind::Manual, $this->admin);
    }

    public function test_collective_contacts_are_never_pushed_merged_invited_or_invoiced(): void {
        $contacts = app(CollectiveContacts::class);
        $supplier = $contacts->supplier($this->organization);
        $customer = $contacts->customer($this->organization);

        $this->assertThrows(fn () => app(LexofficePlugin::class)->pushSupplierContact($supplier), CollectiveContactException::class);
        $this->assertThrows(fn () => app(LexofficePlugin::class)->pushContact($customer), CollectiveContactException::class);

        $other = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Echter Lieferant']);
        $this->assertThrows(fn () => app(SupplierMergeService::class)->merge($supplier, $other), InvalidArgumentException::class);

        $this->assertThrows(fn () => app(PortalAccessService::class)->invite($customer, 'Jemand', 'jemand@example.test', $this->admin), ValidationException::class);

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id,
            'status' => InvoiceStatus::Draft, 'created_by' => $this->admin->id,
        ]);
        $this->assertThrows(fn () => app(InvoiceIssueService::class)->issue($invoice), InvoiceIssueException::class);
    }

    public function test_payment_to_the_collective_supplier_always_needs_a_confirmed_iban(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $incoming = $this->incoming(['supplier_id' => $collective->id, 'creditor_iban' => 'DE89370400440532013000']);
        $proposals = app(PaymentProposalService::class);

        $this->assertTrue($proposals->ibanDiffersFromMaster($incoming, $collective));
        $incoming->forceFill(['creditor_iban_confirmed_at' => now()])->save();
        $this->assertFalse($proposals->ibanDiffersFromMaster($incoming->refresh(), $collective));
    }

    public function test_a_collective_contact_with_documents_cannot_be_deleted(): void {
        $collective = app(CollectiveContacts::class)->supplier($this->organization);
        $this->incoming(['supplier_id' => $collective->id]);

        $this->delete(route('suppliers.destroy', $collective))
            ->assertSessionHas('error', __('Der Sammelkontakt hat Belege und lässt sich nicht löschen.'));
        $this->assertModelExists($collective);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $summary
     */
    private function incoming(array $attributes, array $summary = []): IncomingEInvoice {
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', uniqid('inv', true)),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received,
            'invoice_number' => 'RE-' . uniqid(), 'seller_name' => 'Baumarkt Filiale 12', 'currency' => 'EUR', 'amount_gross' => '119.00',
            'summary' => $summary,
            ...$attributes,
        ]);
    }
}
