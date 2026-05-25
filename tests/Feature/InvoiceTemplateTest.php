<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, Invoice, InvoiceTemplate, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class InvoiceTemplateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_requires_authorization(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('invoice-templates.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('invoice-templates.index'))->assertOk();
    }

    public function test_store_creates_template_and_unsets_other_defaults(): void {
        $existing = InvoiceTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Alt',
            'slug' => 'alt',
            'is_default' => true,
        ]);

        $this->actingAs($this->admin)->post(route('invoice-templates.store'), [
            'name' => 'Neu',
            'slug' => 'neu',
            'accent_color' => '#2563eb',
            'header_text' => 'Header',
            'footer_text' => 'Footer',
            'is_default' => '1',
        ])->assertRedirect(route('invoice-templates.index'));

        $this->assertFalse($existing->fresh()?->is_default);
        $new = InvoiceTemplate::where('slug', 'neu')->firstOrFail();
        $this->assertTrue($new->is_default);
        $this->assertSame('#2563eb', $new->accent_color);
    }

    public function test_pdf_uses_customer_template_then_org_default(): void {
        $orgDefault = InvoiceTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Org Standard',
            'slug' => 'org-default',
            'footer_text' => 'ORG_DEFAULT_FOOTER_MARKER',
            'is_default' => true,
        ]);

        $customerTpl = InvoiceTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Kunde',
            'slug' => 'kunde-tpl',
            'footer_text' => 'CUSTOMER_TEMPLATE_FOOTER_MARKER',
            'is_default' => false,
        ]);

        $customerA = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'A',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
            'invoice_template_id' => $customerTpl->id,
        ]);
        $customerB = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'B',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);

        $invoiceA = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerA->id,
            'number' => 'R-A',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoiceB = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerB->id,
            'number' => 'R-B',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $resA = $this->actingAs($this->admin)->get(route('invoices.pdf', $invoiceA));
        $resA->assertOk();
        $resB = $this->actingAs($this->admin)->get(route('invoices.pdf', $invoiceB));
        $resB->assertOk();

        $this->assertNotSame($orgDefault->id, $customerTpl->id);
    }

    public function test_destroy_removes_template(): void {
        $template = InvoiceTemplate::create([
            'organization_id' => $this->organization->id,
            'name' => 'X',
            'slug' => 'x',
        ]);

        $this->actingAs($this->admin)->delete(route('invoice-templates.destroy', $template))
            ->assertRedirect(route('invoice-templates.index'));
        $this->assertNull(InvoiceTemplate::find($template->id));
    }
}
