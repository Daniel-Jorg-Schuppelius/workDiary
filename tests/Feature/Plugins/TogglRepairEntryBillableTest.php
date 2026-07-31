<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglRepairEntryBillableTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Timesheet\TimesheetStatus;
use App\Models\{Customer, ExternalReference, Invoice, InvoiceItem, PluginSetting, Project, TimeEntry, Timesheet, User};
use App\Plugins\Toggl\TogglPlugin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * toggl:repair-entry-billable — setzt Toggl-importierte Zeiten mit hartem
 * billable=false auf die effektive Projekt-Abrechenbarkeit zurück (Toggl Free
 * lieferte nie ein echtes Signal). Skip-Regeln: exportiert, abgerechnet,
 * signiert, Projekt nicht abrechenbar; kein Writeback trotz aktivierter
 * Rückrichtung.
 */
class TogglRepairEntryBillableTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'billable' => true,
            'hourly_rate' => 100.0,
        ]);
        $this->enableToggl();
    }

    private function enableToggl(array $settings = []): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge(['api_token' => 'test-token'], $settings),
        ]);
    }

    private function togglLinkedEntry(array $attributes = []): TimeEntry {
        $entry = TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => '2026-07-10',
            'started_at' => CarbonImmutable::parse('2026-07-10 09:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-10 10:00'),
            'minutes' => 60,
            'billable' => false,
            'exported' => false,
        ], $attributes));

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'toggl:' . $entry->getKey(),
            'payload' => [],
            'synced_at' => now(),
        ]);

        return $entry;
    }

    public function test_dry_run_changes_nothing_and_apply_repairs(): void {
        $entry = $this->togglLinkedEntry();

        $this->artisan('toggl:repair-entry-billable')->assertSuccessful();
        $this->assertFalse($entry->fresh()->billable);

        $this->artisan('toggl:repair-entry-billable', ['--apply' => true])->assertSuccessful();

        $fresh = $entry->fresh();
        $this->assertTrue($fresh->billable);
        // Eloquent-Save rechnet den Satz-Snapshot neu (vorher 0, da nicht abrechenbar).
        $this->assertSame(100.0, $fresh->rate?->toFloat());
    }

    public function test_skip_rules_leave_entries_untouched(): void {
        $exported = $this->togglLinkedEntry(['exported' => true, 'started_at' => CarbonImmutable::parse('2026-07-10 11:00'), 'ended_at' => CarbonImmutable::parse('2026-07-10 12:00')]);

        $invoiced = $this->togglLinkedEntry(['started_at' => CarbonImmutable::parse('2026-07-10 13:00'), 'ended_at' => CarbonImmutable::parse('2026-07-10 14:00')]);
        $invoice = Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
            'number' => 'RE-2026-001',
            'status' => 'issued',
            'type' => 'invoice',
            'issued_on' => '2026-07-15',
            'currency' => 'EUR',
            'subtotal' => 100.00,
            'tax_rate' => 19.00,
            'tax_amount' => 19.00,
            'total' => 119.00,
        ]);
        $item = InvoiceItem::query()->create([
            'organization_id' => $this->organization->id,
            'invoice_id' => $invoice->id,
            'description' => 'Sammelposition',
            'quantity' => 1,
            'unit' => 'Std',
            'unit_price' => 100.00,
            'amount' => 100.00,
            'position' => 1,
        ]);
        DB::table('invoice_item_time_entries')->insert([
            'organization_id' => $this->organization->id,
            'invoice_item_id' => $item->id,
            'time_entry_id' => $invoiced->getKey(),
        ]);

        $timesheet = Timesheet::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-07-10',
            'status' => TimesheetStatus::Signed->value,
        ]);
        $signed = $this->togglLinkedEntry(['timesheet_id' => $timesheet->id, 'started_at' => CarbonImmutable::parse('2026-07-10 15:00'), 'ended_at' => CarbonImmutable::parse('2026-07-10 16:00')]);

        $nonBillableProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'billable' => false,
        ]);
        $inNonBillable = $this->togglLinkedEntry(['project_id' => $nonBillableProject->id, 'started_at' => CarbonImmutable::parse('2026-07-10 17:00'), 'ended_at' => CarbonImmutable::parse('2026-07-10 18:00')]);

        $this->artisan('toggl:repair-entry-billable', ['--apply' => true])->assertSuccessful();

        $this->assertFalse($exported->fresh()->billable);
        $this->assertFalse($invoiced->fresh()->billable);
        $this->assertFalse($signed->fresh()->billable);
        $this->assertFalse($inNonBillable->fresh()->billable);
    }

    public function test_org_with_default_billable_off_is_skipped(): void {
        PluginSetting::query()->where('plugin_id', TogglPlugin::ID)->delete();
        $this->enableToggl(['default_billable' => false]);
        $entry = $this->togglLinkedEntry();

        $this->artisan('toggl:repair-entry-billable', ['--apply' => true])->assertSuccessful();

        $this->assertFalse($entry->fresh()->billable);
    }

    public function test_repair_does_not_enqueue_writeback(): void {
        PluginSetting::query()->where('plugin_id', TogglPlugin::ID)->delete();
        $this->enableToggl(['writeback' => true]);
        $this->togglLinkedEntry();

        $this->artisan('toggl:repair-entry-billable', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseMissing('integration_outbox', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
        ]);
    }

    public function test_non_toggl_entry_stays_untouched(): void {
        $plain = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'date' => '2026-07-11',
            'started_at' => CarbonImmutable::parse('2026-07-11 09:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-11 10:00'),
            'minutes' => 60,
            'billable' => false,
        ]);

        $this->artisan('toggl:repair-entry-billable', ['--apply' => true])->assertSuccessful();

        $this->assertFalse($plain->fresh()->billable);
    }
}
