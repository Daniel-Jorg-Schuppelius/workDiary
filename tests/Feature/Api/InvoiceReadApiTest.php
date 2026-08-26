<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Customer, Invoice, Organization, User};
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Rechnungen read-only mit Sqids, Filtern und PDF-Download-Link. */
final class InvoiceReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private static int $number = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'country' => 'DE']);
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(array $overrides = []): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-' . str_pad((string) ++self::$number, 4, '0', STR_PAD_LEFT),
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'issued_on' => '2026-08-01',
            ...$overrides,
        ]);
    }

    public function test_missing_ability_is_forbidden(): void {
        $invoice = $this->invoice();
        Sanctum::actingAs($this->accountant, ['customers:read']);

        $this->getJson(route('api.invoices.index'))->assertForbidden();
        $this->getJson(route('api.invoices.pdf', $invoice))->assertForbidden();
    }

    public function test_index_filters_status_customer_and_paginates(): void {
        $this->invoice();
        $paid = $this->invoice(['status' => Invoice::STATUS_PAID, 'paid_on' => '2026-08-10']);
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->invoice(['customer_id' => $otherCustomer->id]);
        Sanctum::actingAs($this->accountant, ['invoices:read']);

        $page = $this->getJson(route('api.invoices.index', ['per_page' => 2]))->assertOk();
        $this->assertCount(2, $page->json('data'));
        $this->assertSame(3, $page->json('meta.total'));

        $byStatus = $this->getJson(route('api.invoices.index', ['status' => 'paid']))->assertOk();
        $this->assertCount(1, $byStatus->json('data'));
        $this->assertSame($paid->sqid, $byStatus->json('data.0.id'));

        $this->assertCount(2, $this->getJson(route('api.invoices.index', ['customer' => $this->customer->sqid]))->json('data'));
        $this->getJson(route('api.invoices.index', ['customer' => 'nix']))->assertNotFound();
    }

    public function test_show_has_sqids_and_pdf_link(): void {
        $invoice = $this->invoice();
        Sanctum::actingAs($this->accountant, ['invoices:read']);

        $response = $this->getJson(route('api.invoices.show', $invoice))->assertOk();
        $response->assertJsonPath('data.id', $invoice->sqid)
            ->assertJsonPath('data.customer.id', $this->customer->sqid)
            ->assertJsonPath('data.pdf_url', route('api.invoices.pdf', $invoice));
        $this->assertStringContainsString('/api/v1/invoices/' . $invoice->sqid . '/pdf', (string) $response->json('data.pdf_url'));
    }

    public function test_pdf_download_streams_pdf(): void {
        $invoice = $this->invoice();
        $this->mock(InvoicePdfRenderer::class)->shouldReceive('output')->once()->andReturn('%PDF-1.4 fake');
        Sanctum::actingAs($this->accountant, ['invoices:read']);

        $response = $this->get(route('api.invoices.pdf', $invoice))->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('rechnung-' . $invoice->number . '.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 fake', $response->getContent());
    }

    public function test_foreign_organization_invoice_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = $this->invoice([
            'organization_id' => $other->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $other->id])->id,
        ]);
        Sanctum::actingAs($this->accountant, ['invoices:read']);

        $this->getJson(route('api.invoices.show', $foreign))->assertNotFound();
        $this->getJson(route('api.invoices.pdf', $foreign))->assertNotFound();
    }
}
