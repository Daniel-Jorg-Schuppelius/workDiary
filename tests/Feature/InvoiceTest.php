<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\Project\ProjectStatus;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
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
    }

    public function test_index_requires_billing_role(): void
    {
        $regular = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($regular)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('invoices.index'))->assertOk();
    }

    public function test_create_invoice_from_time_entries(): void
    {
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

        $this->actingAs($this->admin)
            ->post(route('invoices.store'), [
                'customer_id' => $this->customer->id,
                'project_id' => $this->project->id,
            ])
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(1, $invoice->items()->count());
        $this->assertSame('180.00', $invoice->subtotal);
        $this->assertSame('34.20', $invoice->tax_amount);
        $this->assertSame('214.20', $invoice->total);
    }

    public function test_issue_and_pay_workflow(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0001',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('invoices.issue', $invoice))->assertRedirect();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->fresh()?->status);

        $this->actingAs($this->admin)
            ->post(route('invoices.pay', $invoice))->assertRedirect();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()?->status);
    }

    public function test_pdf_export(): void
    {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0002',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'h',
            'unit_price' => '90.00',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
