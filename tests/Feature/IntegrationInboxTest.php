<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\UserRole;
use App\Models\{Customer, ExternalReference, IntegrationInboxItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class IntegrationInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function item(array $overrides = []): IntegrationInboxItem {
        return IntegrationInboxItem::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'source' => 'api',
            'target_type' => (new Customer)->getMorphClass(),
            'external_type' => 'client',
            'external_id' => 'tg-1',
            'dedupe_key' => 'client:tg-1',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => ['client' => 'Neu AG'],
            'mapped_snapshot' => ['name' => 'Neu AG', 'vat_id' => 'DE123'],
            'display_title' => 'Neu AG',
        ], $overrides));
    }

    public function test_index_renders(): void {
        $this->item();
        $this->actingAs($this->admin)->get(route('admin.integration.inbox'))->assertOk();
    }

    public function test_index_shows_plugin_tabs(): void {
        $this->item();
        $this->item(['plugin_id' => 'fritzbox', 'dedupe_key' => 'client:fb-1']);

        $this->actingAs($this->admin)
            ->get(route('admin.integration.inbox'))
            ->assertOk()
            ->assertSee(__('Alle Quellen'))
            ->assertSee(route('admin.integration.inbox', ['plugin' => 'toggl']), escape: false)
            ->assertSee(route('admin.integration.inbox', ['plugin' => 'fritzbox']), escape: false);
    }

    public function test_conflict_item_shows_translated_title_and_time_entry_context(): void {
        // Outbox-Fehlschlag wie aus IntegrationOutboxDeliveryJob::compensateEntry():
        // technischer Operations-Key als Titel, betroffener Zeiteintrag als referenceable.
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Semso Multimedia']);
        $project = \App\Models\Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Fernwartung',
        ]);
        $entry = \App\Models\TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => '2026-07-31',
            'started_at' => '2026-07-31 11:28:00',
            'ended_at' => '2026-07-31 11:32:00',
            'minutes' => 4,
            'description' => 'PC-SEMSO (Starten der Maschine)',
        ]);
        $this->item([
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'target_type' => (new \App\Models\TimeEntry)->getMorphClass(),
            'external_type' => 'toggl.time_entry.update',
            'dedupe_key' => 'outbox-failed:toggl-entry-update:toggl:1:1',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->id,
            'display_title' => 'toggl.time_entry.update',
            'display_subtitle' => 'extern nicht bestätigt',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.integration.inbox'))
            ->assertOk()
            // Technischer Key wird übersetzt statt roh angezeigt …
            ->assertSee(__('Zeit-Änderung nicht nach :plugin übertragen', ['plugin' => 'Toggl']))
            ->assertDontSee('toggl.time_entry.update')
            // … und der betroffene Zeiteintrag ist erkennbar.
            ->assertSee('31.07.2026')
            ->assertSee('PC-SEMSO (Starten der Maschine)')
            ->assertSee('Fernwartung')
            ->assertSee('Semso Multimedia');
    }

    /** Konflikt-Item mit Zeiteintrag + Toggl-Plugin-Setup für inspectConflict. */
    private function togglConflictItem(): array {
        \App\Models\PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl',
            'enabled' => true,
            'settings' => ['api_token' => 'test-token'],
        ]);
        $entry = \App\Models\TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'date' => '2026-07-31',
            'started_at' => '2026-07-31 11:00:00',
            'ended_at' => '2026-07-31 11:30:00',
            'minutes' => 30,
            'description' => 'Lokale Beschreibung',
        ]);
        $item = $this->item([
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'target_type' => (new \App\Models\TimeEntry)->getMorphClass(),
            'external_type' => 'toggl.time_entry.update',
            'external_id' => '55',
            'dedupe_key' => 'outbox-failed:toggl-entry-update:toggl:123:1785666039',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->id,
            'display_title' => 'toggl.time_entry.update',
        ]);

        return [$item, $entry];
    }

    public function test_toggl_conflict_inspect_loads_remote_state(): void {
        [$item] = $this->togglConflictItem();
        \Tests\Support\FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries/123' => \Tests\Support\FakePluginHttp::response([
                'id' => 123,
                'start' => '2026-07-31T09:00:00+00:00',
                'stop' => '2026-07-31T09:45:00+00:00',
                'duration' => 2700,
                'description' => 'Remote-Beschreibung',
                'billable' => true,
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.conflict.inspect', $item))
            ->assertRedirect()
            ->assertSessionHas('status');

        $snapshot = $item->refresh()->remote_snapshot;
        $this->assertSame('Remote-Beschreibung', $snapshot['remote']['description']);
        $this->assertSame(45, $snapshot['remote']['minutes']);
        $this->assertFalse($snapshot['remote_missing']);
        $this->assertSame('Lokale Beschreibung', $snapshot['local']['description']);
    }

    public function test_toggl_conflict_inspect_marks_deleted_remote(): void {
        [$item] = $this->togglConflictItem();
        \Tests\Support\FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries/123' => \Tests\Support\FakePluginHttp::response(null, 404),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.conflict.inspect', $item))
            ->assertRedirect()
            ->assertSessionHas('status');

        $snapshot = $item->refresh()->remote_snapshot;
        $this->assertTrue($snapshot['remote_missing']);
        $this->assertNull($snapshot['remote']);
    }

    public function test_conflict_item_with_deleted_time_entry_shows_fallback(): void {
        $this->item([
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'target_type' => (new \App\Models\TimeEntry)->getMorphClass(),
            'dedupe_key' => 'outbox-failed:toggl-entry-update:toggl:2:2',
            'referenceable_type' => (new \App\Models\TimeEntry)->getMorphClass(),
            'referenceable_id' => 424242,
            'display_title' => 'toggl.time_entry.delete',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.integration.inbox'))
            ->assertOk()
            ->assertSee(__('Zeit-Löschung nicht nach :plugin übertragen', ['plugin' => 'Toggl']))
            ->assertSee(__('Zeiteintrag #:id existiert nicht mehr', ['id' => 424242]));
    }

    public function test_assign_links_to_existing_and_writes_reference(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Bestand GmbH']);
        $item = $this->item();

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.assign', $item), ['target' => $customer->sqid])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LINKED, $item->status);
        $this->assertSame($customer->id, $item->resolved_to_id);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'toggl', 'external_type' => 'client', 'external_id' => 'tg-1',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_create_makes_new_record(): void {
        $item = $this->item();

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.create', $item))
            ->assertRedirect();

        $created = Customer::query()->where('name', 'Neu AG')->first();
        $this->assertNotNull($created);
        $this->assertSame('DE123', $created->vat_id);
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $item->fresh()->status);
        $this->assertDatabaseHas('external_references', ['external_id' => 'tg-1', 'referenceable_id' => $created->id]);
    }

    public function test_accept_remote_updates_local(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'old@x.test']);
        $item = $this->item([
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'plugin_id' => 'lexoffice', 'external_type' => 'contact', 'external_id' => 'lx-1', 'dedupe_key' => 'contact:lx-1',
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id,
            'mapped_snapshot' => ['email' => 'new@x.test'],
            'local_snapshot' => ['email' => 'old@x.test'],
            'diff_fields' => ['email'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.accept-remote', $item))
            ->assertRedirect();

        $this->assertSame('new@x.test', $customer->fresh()->email);
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_REMOTE, $item->fresh()->status);
    }

    public function test_keep_local_closes_without_change(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'old@x.test']);
        $item = $this->item([
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id,
            'mapped_snapshot' => ['email' => 'new@x.test'], 'local_snapshot' => ['email' => 'old@x.test'], 'diff_fields' => ['email'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.keep-local', $item))
            ->assertRedirect();

        $this->assertSame('old@x.test', $customer->fresh()->email);
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $item->fresh()->status);
    }

    public function test_dismiss(): void {
        $item = $this->item();
        $this->actingAs($this->admin)->post(route('admin.integration.inbox.dismiss', $item))->assertRedirect();
        $this->assertSame(IntegrationInboxItem::STATUS_DISMISSED, $item->fresh()->status);
    }

    public function test_non_billing_user_forbidden(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $user->assignRole(UserRole::Callcenter->value);
        $item = $this->item();

        $this->actingAs($user)
            ->post(route('admin.integration.inbox.dismiss', $item))
            ->assertForbidden();

        $this->assertTrue($item->fresh()->isOpen());
    }

    public function test_mappings_index_and_unlink(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $ref = ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'toggl', 'external_type' => 'client',
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id,
            'external_id' => 'tg-9',
        ]);

        $this->actingAs($this->admin)->get(route('admin.integration.mappings.index'))->assertOk();

        $this->actingAs($this->admin)
            ->delete(route('admin.integration.mappings.destroy', $ref))
            ->assertRedirect();

        $this->assertNull(ExternalReference::query()->find($ref->id));
    }
}
