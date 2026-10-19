<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-1019: Suche im Kundenportal — nur freigegebene Bereiche, keine Entwürfe, nur eigene Daten. */
final class PortalSearchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function invoice(Customer $customer, string $number, InvoiceStatus|string $status): void {
        Invoice::create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => $number, 'status' => $status,
            'currency' => 'EUR', 'subtotal' => '100.00', 'tax_rate' => '19.00', 'tax_amount' => '19.00', 'total' => '119.00']);
    }

    public function test_search_respects_capabilities_drafts_and_customer_boundaries(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer, ['invoices']);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $this->invoice($customer, 'RE-2026-SUCH-1', InvoiceStatus::Issued);
        $this->invoice($customer, 'RE-2026-SUCH-ENTWURF', InvoiceStatus::Draft);
        $this->invoice($other, 'RE-2026-SUCH-FREMD', InvoiceStatus::Issued);
        DiaryEntry::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'title' => 'SUCH-Auftrag']);

        $this->actingAs($portalUser, 'customer');
        $this->get(route('customer.search', ['q' => 'SUCH']))->assertOk()
            ->assertSee('RE-2026-SUCH-1')
            ->assertDontSee('RE-2026-SUCH-ENTWURF')
            ->assertDontSee('RE-2026-SUCH-FREMD')
            ->assertDontSee('SUCH-Auftrag');
        $this->get(route('customer.invoices.index'))->assertOk()->assertDontSee('RE-2026-SUCH-ENTWURF');

        $this->allowPortal($customer, ['invoices', 'diary']);
        $this->actingAs($portalUser->fresh(), 'customer');
        $this->get(route('customer.search', ['q' => 'SUCH']))->assertOk()->assertSee('SUCH-Auftrag');
        $this->get(route('customer.search', ['q' => 'x']))->assertOk()->assertDontSee('RE-2026-SUCH-1');
    }

    /** Befund 2026-10-19: ein Kunde ohne lokale Rechnungen fand seine Belegnummern aus dem Buchhaltungsprogramm nicht. */
    public function test_search_finds_invoices_of_the_accounting_program(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer, ['invoices']);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);

        $external = static fn (string $number, int $customerId): ExternalInvoice => new ExternalInvoice(
            source: 'lexoffice', key: 'key-' . $number, number: $number, customerId: $customerId, contactName: null, type: 'invoice',
            status: InvoiceStatus::Issued, issuedOn: CarbonImmutable::parse('2026-09-30'), dueOn: null, paidOn: null,
            gross: 119.0, net: 100.0, open: 0.0, currency: 'EUR', url: null,
        );
        $this->app->instance(ExternalRevenue::class, new class($external((string) 'RE-1086', (int) $customer->id), $external('RE-2000', (int) $customer->id)) implements ExternalRevenue {
            /** @var list<ExternalInvoice> */
            private array $invoices;

            public function __construct(ExternalInvoice ...$invoices) { $this->invoices = array_values($invoices); }

            public function invoices(ExternalInvoiceQuery $query): array { return array_values(array_filter($this->invoices, fn (ExternalInvoice $i): bool => $i->customerId === $query->customerId)); }

            public function invoicesByNumber(int $organizationId, array $numbers): array { return []; }

            public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array { return []; }

            public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array { return []; }

            public function overdueCount(int $organizationId, string $today): int { return 0; }

            public function productRevenue(CarbonInterface $from, CarbonInterface $to): array { return []; }

            public function customerIdsWithDocuments(int $organizationId): array { return []; }

            public function file(string $source, int $organizationId, string $key): ?ExternalInvoiceFile { return null; }

            public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile { return null; }
        });

        $this->actingAs($portalUser, 'customer');
        $this->get(route('customer.search', ['q' => '1086']))->assertOk()
            ->assertSee('RE-1086')
            ->assertDontSee('RE-2000');
        $this->get(route('customer.search', ['q' => '9999']))->assertOk()->assertDontSee('RE-1086');
    }
}
