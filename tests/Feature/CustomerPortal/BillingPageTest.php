<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CustomerPortal;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, Project, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, URL};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 098: Portal-Abrechnungsseite — nur eigener Kunde, 404 ohne
 * Konto-Modus-Agreement, Monatsansicht mit Abrechnungsblock, signierte
 * PDF-URL (gültig/ohne Signatur).
 */
class BillingPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $worker;

    private CustomerBillingAgreement $agreement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create([
                'organization_id' => $this->organization->id,
                'password' => Hash::make('secret-pass'),
            ]);
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'hourly_rate' => 16.50,
        ]);

        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->worker->id,
            'project_id' => $project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => '2026-06-10 10:00:00',
            'ended_at' => '2026-06-10 12:00:00',
        ]);
    }

    public function test_index_lists_monthly_statements(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.billing.index'))
            ->assertOk()
            ->assertSee(__('customer-billing.portal_title'))
            ->assertSee('33,00');
    }

    public function test_show_renders_attendance_and_balance_block(): void {
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.billing.show', ['year' => 2026, 'month' => 6]))
            ->assertOk()
            ->assertSee(__('customer-billing.attendance'))
            // 10:00–12:00 UTC = 12:00–14:00 Europe/Berlin (CEST).
            ->assertSee('12:00')
            ->assertSee('14:00')
            ->assertSee('33,00')
            ->assertSee(__('customer-billing.provisional'));
    }

    public function test_billing_pages_return_404_without_account_agreement(): void {
        $this->agreement->update(['active' => false]);

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.billing.index'))
            ->assertNotFound();
    }

    public function test_other_customers_user_cannot_see_billing(): void {
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherUser = User::factory()
            ->kunde((int) $otherCustomer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);

        // Ohne eigenes Agreement: 404 — fremde Konten sind nicht erreichbar.
        $this->actingAs($otherUser, 'customer')
            ->get(route('customer.billing.index'))
            ->assertNotFound();
    }

    public function test_pdf_requires_valid_signature(): void {
        // Statement erzeugen (Portal-Ansicht rechnet die Kette durch).
        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.billing.show', ['year' => 2026, 'month' => 6]))
            ->assertOk();
        $statement = $this->agreement->statements()->where('year', 2026)->where('month', 6)->firstOrFail();

        $this->get(route('customer.billing.pdf', ['statement' => $statement->getRouteKey()]))
            ->assertForbidden();

        $signed = URL::temporarySignedRoute('customer.billing.pdf', now()->addHour(), [
            'statement' => $statement->getRouteKey(),
        ]);
        $response = $this->get($signed);
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}
