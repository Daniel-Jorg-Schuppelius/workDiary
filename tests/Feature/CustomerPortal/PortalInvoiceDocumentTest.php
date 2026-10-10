<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalInvoiceDocumentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1097: Rechnungsdokument im Portal, Rechnungskachel ohne Entwürfe und
 * Menüeinträge „Termin anfragen“ und „Schulungen“.
 */
class PortalInvoiceDocumentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private static int $number = 0;

    private Customer $customer;

    private User $portalUser;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'WorkDiary GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE123456789',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@workdiary.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'WorkDiary GmbH',
            'payment_terms_days' => 14,
        ]]]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@acme.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '54321',
            'address_city' => 'Hamburg',
            'country' => 'DE',
        ]);
        $this->allowPortal($this->customer, ['invoices']);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create();
    }

    protected function tearDown(): void {
        config(['license.feature_overrides' => []]);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function invoice(InvoiceStatus $status = InvoiceStatus::Issued, ?Customer $customer = null, string $deliveryFormat = 'pdf'): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'number' => 'RE-2026-' . str_pad((string) ++self::$number, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'issued_on' => '2026-09-01',
            'due_on' => '2026-09-15',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'delivery_format' => $deliveryFormat,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Wartung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh();
    }

    public function test_issued_invoice_downloads_as_pdf_and_is_linked_in_the_list(): void {
        $invoice = $this->invoice();

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.invoices.index'))
            ->assertOk()
            ->assertSee(route('customer.invoices.pdf', $invoice));

        $response = $this->actingAs($this->portalUser, 'customer')->get(route('customer.invoices.pdf', $invoice));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('rechnung-' . $invoice->number . '.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_zugferd_customers_receive_the_zugferd_document(): void {
        $invoice = $this->invoice(deliveryFormat: 'zugferd');

        $response = $this->actingAs($this->portalUser, 'customer')->get(route('customer.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('ZUGFeRD_' . $invoice->number . '.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_drafts_foreign_invoices_and_missing_capability_answer_404(): void {
        $draft = $this->invoice(InvoiceStatus::Draft);
        $foreign = $this->invoice(customer: Customer::factory()->create(['organization_id' => $this->organization->id]));
        $own = $this->invoice();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.invoices.pdf', $draft))->assertNotFound();
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.invoices.pdf', $foreign))->assertNotFound();

        // Frischer Nutzer: die geladene Kundenrelation trüge sonst die alte Freigabe.
        $this->allowPortal($this->customer, ['diary']);
        $this->actingAs($this->portalUser->fresh(), 'customer')->get(route('customer.invoices.pdf', $own))->assertNotFound();
    }

    public function test_externally_led_invoice_has_no_portal_document(): void {
        $this->assertTrue(Route::has('invoices.lexoffice.pdf'));
        $invoice = $this->invoice();
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'external_type' => 'invoice',
            'referenceable_type' => MorphMap::alias(Invoice::class),
            'referenceable_id' => $invoice->id,
            'external_id' => 'lx-4711',
        ]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertDontSee(route('customer.invoices.pdf', $invoice));
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.invoices.pdf', $invoice))->assertNotFound();
    }

    public function test_dashboard_tile_counts_issued_invoices_only(): void {
        $this->invoice();
        $this->invoice(InvoiceStatus::Paid);
        $this->invoice(InvoiceStatus::Draft);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', static fn (array $stats): bool => $stats['invoices'] === 2);
    }

    public function test_menu_shows_appointments_only_with_the_capability(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertDontSee(route('customer.appointments.index'));

        $this->allowPortal($this->customer, ['invoices', 'appointments']);
        $this->actingAs($this->portalUser->fresh(), 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee(route('customer.appointments.index'))
            ->assertSee(__('Termin anfragen'));
    }

    public function test_menu_shows_training_only_with_the_learning_module(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee(route('customer.learning.index'))
            ->assertSee(__('learning.title.portal'));

        config(['license.feature_overrides' => ['module.lms' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertDontSee(route('customer.learning.index'));
        // MVP-1100: auch die Seiten selbst, kundensicher mit 404 statt 423.
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.learning.index'))->assertNotFound();
    }
}
