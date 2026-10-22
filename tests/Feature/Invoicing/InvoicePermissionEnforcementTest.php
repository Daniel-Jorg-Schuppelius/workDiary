<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePermissionEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\User\{Permission, UserRole};
use App\Models\Customer\Customer;
use App\Models\Invoicing\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Rechnungen prüfen ihre Rechte (lk-06): lesen mit invoice.view(Any) —
 * damit auch Geschäftsführung und Support —, löschen, stellen und bezahlt
 * setzen mit dem jeweiligen Recht; der Status gilt auch für Admins.
 */
class InvoicePermissionEnforcementTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_reading_invoices_follows_invoice_view(): void {
        $invoice = $this->invoice(InvoiceStatus::Draft);

        foreach ([UserRole::Buchhaltung, UserRole::Geschaeftsfuehrung, UserRole::Support] as $role) {
            $user = $this->userWithRole($role->value);
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Invoice::class), $role->value);
            $this->assertTrue(Gate::forUser($user)->allows('view', $invoice), $role->value);
        }
        $this->assertFalse(Gate::forUser($this->userWithRole(UserRole::Teamleitung->value))->allows('view', $invoice));
    }

    public function test_issue_pay_and_delete_follow_their_rights(): void {
        $draft = $this->invoice(InvoiceStatus::Draft);
        $issued = $this->invoice(InvoiceStatus::Issued);
        $accounting = $this->userWithRole(UserRole::Buchhaltung->value);
        $management = $this->userWithRole(UserRole::Geschaeftsfuehrung->value);
        $admin = $this->orgAdmin();

        $this->assertTrue(Gate::forUser($accounting)->allows('issue', $draft));
        $this->assertTrue(Gate::forUser($accounting)->allows('delete', $draft));
        $this->assertTrue(Gate::forUser($accounting)->allows('pay', $issued));
        $this->assertFalse(Gate::forUser($management)->allows('issue', $draft));
        $this->assertFalse(Gate::forUser($management)->allows('pay', $issued));
        $this->assertTrue(Gate::forUser($admin)->allows('issue', $draft));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $issued), 'gestellt bleibt gestellt');

        $payer = $this->actorIn($this->organization, [Permission::InvoicePay]);
        $this->assertTrue(Gate::forUser($payer)->allows('pay', $issued));
        $this->assertFalse(Gate::forUser($payer)->allows('issue', $draft));
    }

    private function invoice(InvoiceStatus $status): Invoice {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => $status,
        ]);
        $this->actAsTeam($this->organization);

        return $invoice;
    }
}
