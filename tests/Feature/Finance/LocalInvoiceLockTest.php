<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LocalInvoiceLockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Invoice, Project, TimeEntry, User};
use App\Services\Finance\BillingModeLockedException;
use App\Services\Invoicing\InvoiceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Hoheits-Sperre (Feature 045): bei externer Fakturierungshoheit
 * (lexoffice/datev) ist die lokale Rechnungserstellung gesperrt; im
 * workdiary-Modus bleibt sie erlaubt.
 */
class LocalInvoiceLockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'created_by' => $this->admin->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->admin->id,
        ]);

        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->admin->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'hourly_rate' => '90.00',
        ]);
    }

    public function test_store_is_locked_when_customer_billing_mode_is_lexoffice(): void {
        $this->customer->update(['billing_mode' => 'lexoffice']);

        $response = $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_store_is_locked_when_org_default_is_datev(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'datev']]);

        $response = $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'content' => 'material',
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_store_is_allowed_in_workdiary_mode(): void {
        $response = $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_customer_override_workdiary_unlocks_despite_external_org_default(): void {
        $this->organization->update(['settings' => ['billing_mode' => 'lexoffice']]);
        $this->customer->update(['billing_mode' => 'workdiary']);

        $response = $this->actingAs($this->admin)->post(route('invoices.store'), [
            'customer_id' => $this->customer->id,
            'project_id' => $this->project->id,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_generator_throws_for_time_invoice_under_external_mode(): void {
        $this->customer->update(['billing_mode' => 'datev']);

        $this->expectException(BillingModeLockedException::class);
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer->fresh(), null);
    }

    public function test_generator_throws_for_material_invoice_under_external_mode(): void {
        $this->customer->update(['billing_mode' => 'lexoffice']);

        $this->expectException(BillingModeLockedException::class);
        app(InvoiceGenerator::class)->fromMaterialUsages($this->customer->fresh(), null);
    }
}
