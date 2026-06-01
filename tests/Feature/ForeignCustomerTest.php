<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ForeignCustomerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ForeignCustomer, Project, TimeEntry, User};
use App\Services\Invoicing\InvoiceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ForeignCustomerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function customer(string $name = 'LDS Systems GmbH'): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name]);
    }

    public function test_non_admin_cannot_create_foreign_customer(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = $this->customer();

        // user-Rolle darf laut Policy zwar anlegen; ein Gast/Support nicht — hier
        // prüfen wir, dass ein reiner Lese-Nutzer (callcenter) abgelehnt wird.
        $callcenter = User::factory()->create(['organization_id' => $this->organization->id]);
        $callcenter->assignRole(\App\Enums\User\UserRole::Callcenter->value);

        $this->actingAs($callcenter)
            ->post(route('foreign-customers.store'), [
                'customer_id' => $customer->sqid,
                'name' => 'Axel Tücks GmbH',
            ])
            ->assertForbidden();
    }

    public function test_admin_creates_foreign_customer_under_customer(): void {
        $customer = $this->customer();

        $this->actingAs($this->admin)
            ->post(route('foreign-customers.store'), [
                'customer_id' => $customer->sqid,
                'name' => 'Axel Tücks GmbH',
                'email' => 'info@tuecks.example',
            ])
            ->assertRedirect();

        $fc = ForeignCustomer::query()->where('name', 'Axel Tücks GmbH')->first();
        $this->assertNotNull($fc);
        $this->assertSame($customer->id, $fc->customer_id);
        $this->assertSame($this->admin->id, $fc->created_by);
    }

    public function test_archive_and_restore(): void {
        $fc = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer()->id,
        ]);

        $this->actingAs($this->admin)->post(route('foreign-customers.archive', $fc))->assertRedirect();
        $this->assertNotNull($fc->fresh()->archived_at);

        $this->actingAs($this->admin)->post(route('foreign-customers.restore', $fc))->assertRedirect();
        $this->assertNull($fc->fresh()->archived_at);
    }

    public function test_promote_creates_customer_and_reparents_projects(): void {
        $firm = $this->customer();
        $fc = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $firm->id,
            'name' => 'Axel Tücks GmbH',
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $firm->id,
            'foreign_customer_id' => $fc->id,
            'name' => 'DATEV',
            'is_default' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('foreign-customers.promote', $fc))
            ->assertRedirect();

        // Neuer eigenständiger Kunde aus dem Fremdkunden.
        $promoted = Customer::query()->where('name', 'Axel Tücks GmbH')->first();
        $this->assertNotNull($promoted);
        $this->assertNotSame($firm->id, $promoted->id);

        // Projekt umgehängt, Endkunden-Bezug geleert.
        $project->refresh();
        $this->assertSame($promoted->id, $project->customer_id);
        $this->assertNull($project->foreign_customer_id);

        // Fremdkunde archiviert.
        $this->assertNotNull($fc->fresh()->archived_at);
    }

    public function test_invoice_filtered_by_foreign_customer(): void {
        $firm = $this->customer();
        $fcA = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $firm->id, 'name' => 'Kunde A']);
        $fcB = ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $firm->id, 'name' => 'Kunde B']);

        $projectA = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $firm->id, 'foreign_customer_id' => $fcA->id, 'is_default' => false]);
        $projectB = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $firm->id, 'foreign_customer_id' => $fcB->id, 'is_default' => false]);

        foreach ([$projectA, $projectB] as $p) {
            TimeEntry::query()->create([
                'organization_id' => $this->organization->id,
                'project_id' => $p->id,
                'user_id' => $this->admin->id,
                'date' => '2026-03-01',
                'started_at' => CarbonImmutable::parse('2026-03-01 09:00:00'),
                'ended_at' => CarbonImmutable::parse('2026-03-01 10:00:00'),
                'kind' => TimeEntryKind::Work,
                'billable' => true,
                'exported' => false,
            ]);
        }

        $invoice = app(InvoiceGenerator::class)->fromTimeEntries($firm, null, [], $fcA);

        // Nur die Position des Endkunden A.
        $this->assertSame($fcA->id, $invoice->foreign_customer_id);
        $this->assertSame(1, $invoice->items()->count());
    }
}
