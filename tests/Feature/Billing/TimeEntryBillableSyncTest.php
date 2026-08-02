<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryBillableSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Models\{Customer, Project, TimeEntry, User};
use App\Services\Billing\TimeEntryBillableSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abrechenbar-Durchgriff: der billable-Schalter an Kunde/Projekt zieht auf
 * bestehende OFFENE Zeiteinträge durch (billable ist am Eintrag ein Snapshot).
 * Explizite Projekt-Übersteuerungen und verbrauchte/gesicherte Einträge
 * (exported, rechnungsverknüpft, signiert) bleiben unberührt.
 */
class TimeEntryBillableSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billable' => true,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'billable' => null,
        ]);
    }

    private function openEntry(array $attributes = []): TimeEntry {
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        return TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $worker->id,
            'billable' => true,
            'exported' => false,
            'minutes' => 60,
            'date' => now()->subDays(3)->toDateString(),
        ], $attributes));
    }

    public function test_customer_toggle_updates_open_entries_via_http(): void {
        $open = $this->openEntry();
        $exported = $this->openEntry(['exported' => true]);

        // Checkbox nicht gesendet → prepareForValidation setzt billable=false.
        $response = $this->actingAs($this->admin)
            ->put(route('customers.update', $this->customer), [
                'name' => $this->customer->name,
            ]);

        $response->assertRedirect(route('customers.show', $this->customer));
        $this->assertStringContainsString('Abrechenbarkeit angepasst', (string) session('success'));

        $this->assertFalse((bool) $this->customer->refresh()->billable);
        $this->assertFalse((bool) $open->refresh()->billable);
        $this->assertTrue((bool) $exported->refresh()->billable, 'exportierte Einträge bleiben unberührt');
    }

    public function test_customer_toggle_skips_projects_with_override(): void {
        $overrideProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'billable' => true,
        ]);
        $inheriting = $this->openEntry();
        $overridden = $this->openEntry(['project_id' => $overrideProject->id]);

        $this->customer->update(['billable' => false]);
        $synced = app(TimeEntryBillableSyncService::class)->syncCustomer($this->customer);

        $this->assertSame(1, $synced);
        $this->assertFalse((bool) $inheriting->refresh()->billable);
        $this->assertTrue((bool) $overridden->refresh()->billable, 'Projekt-Override gewinnt gegen den Kunden-Schalter');
    }

    public function test_project_toggle_updates_own_and_inheriting_child_entries(): void {
        $inheritingChild = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'parent_id' => $this->project->id,
            'billable' => null,
        ]);
        $overrideChild = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'parent_id' => $this->project->id,
            'billable' => true,
        ]);
        $own = $this->openEntry();
        $child = $this->openEntry(['project_id' => $inheritingChild->id]);
        $overridden = $this->openEntry(['project_id' => $overrideChild->id]);

        $this->project->update(['billable' => false]);
        $synced = app(TimeEntryBillableSyncService::class)->syncProject($this->project->fresh(['parent', 'customer']));

        $this->assertSame(2, $synced);
        $this->assertFalse((bool) $own->refresh()->billable);
        $this->assertFalse((bool) $child->refresh()->billable);
        $this->assertTrue((bool) $overridden->refresh()->billable);
    }

    public function test_sync_recalculates_rate_snapshot(): void {
        $this->customer->update(['hourly_rate' => '100.00']);
        $entry = $this->openEntry(['minutes' => 60]);
        $this->assertGreaterThan(0, (float) ($entry->refresh()->rate?->toFloat() ?? 0.0));

        $this->customer->update(['billable' => false]);
        app(TimeEntryBillableSyncService::class)->syncCustomer($this->customer);

        $entry->refresh();
        $this->assertFalse((bool) $entry->billable);
        $this->assertSame(0.0, (float) ($entry->rate?->toFloat() ?? 0.0), 'Satz-Snapshot wird beim Sync neu berechnet');
    }

    public function test_sync_skips_invoice_linked_entries(): void {
        $entry = $this->openEntry();

        $invoice = \App\Models\Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-1001',
            'status' => \App\Models\Invoice::STATUS_ISSUED,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Leistung',
            'quantity' => '1.000',
            'unit_price' => '100.0000',
            'tax_rate' => '19.00',
            'position' => 1,
            'time_entry_id' => $entry->id,
        ]);

        $this->customer->update(['billable' => false]);
        $synced = app(TimeEntryBillableSyncService::class)->syncCustomer($this->customer);

        $this->assertSame(0, $synced);
        $this->assertTrue((bool) $entry->refresh()->billable, 'rechnungsverknüpfte Einträge bleiben unberührt');
    }

    public function test_project_update_via_http_reports_synced_entries(): void {
        $entry = $this->openEntry();

        $response = $this->actingAs($this->admin)
            ->put(route('projects.update', $this->project), [
                'name' => $this->project->name,
                'status' => \App\Enums\Project\ProjectStatus::Active->value,
                'customer_id' => $this->customer->sqid,
                'billable' => '0',
            ]);

        $response->assertRedirect(route('projects.show', $this->project));
        $this->assertStringContainsString('Abrechenbarkeit angepasst', (string) session('success'));
        $this->assertFalse((bool) $entry->refresh()->billable);
    }
}
