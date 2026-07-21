<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\{Asset, Customer, ForeignCustomer, PluginSetting, Project, RemotePendingSession, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\{RemoteSession, TeamViewerClient};
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class RemoteSupportSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    private function service(): RemoteSupportService {
        return new RemoteSupportService;
    }

    private function enableTeamViewer(): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'enabled' => true,
            'settings' => [
                'teamviewer_enabled' => true,
                'teamviewer_api_key' => 'test-token',
            ],
        ]);

        return RemoteSupportConfig::resolve($this->organization->id);
    }

    private function deviceAssetWithCustomer(string $teamviewerId): Asset {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
        ]);
        $this->service()->setRemoteId($asset, TeamViewerClient::ID, $teamviewerId);

        return $asset;
    }

    private function fakeConnections(array $records): void {
        FakePluginHttp::fake([
            'https://webapi.teamviewer.com/api/v1/reports/connections*' => FakePluginHttp::response([
                'records' => $records,
                'next_offset' => null,
            ], 200),
        ]);
    }

    public function test_set_remote_id_persists_external_reference(): void {
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
        ]);

        $this->service()->setRemoteId($asset, TeamViewerClient::ID, '123456789');

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => 'teamviewer_id',
            'referenceable_id' => $asset->id,
            'external_id' => '123456789',
        ]);
        $this->assertSame('123456789', $this->service()->remoteId($asset, TeamViewerClient::ID));
    }

    public function test_import_creates_time_entry_in_customer_default_project(): void {
        $config = $this->enableTeamViewer();
        $asset = $this->deviceAssetWithCustomer('123456789');

        $this->fakeConnections([[
            'id' => 'tv-session-1',
            'deviceid' => '123456789',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:45:00')->toIso8601String(),
            'username' => 'Techniker',
        ]]);

        $result = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $project = $asset->customer->defaultProject();
        $this->assertNotNull($project);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(45, $entry->minutes);
        $this->assertTrue($entry->billable);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => RemoteSupportService::EXT_TYPE_SESSION,
            'external_id' => 'teamviewer:tv-session-1',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_overlapping_same_customer_time_links_session_instead_of_double_booking(): void {
        $config = $this->enableTeamViewer();
        $asset = $this->deviceAssetWithCustomer('123456789');
        $ownerId = (int) $this->organization->owner_id;

        // Vorhandene (z. B. aus Toggl importierte) Zeit desselben Kunden, die
        // die Sitzung zeitlich abdeckt.
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $asset->customer_id,
            'name' => 'Toggl-Zeit',
            'is_default' => false,
        ]);
        $existing = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $ownerId,
            'date' => '2026-05-26',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work,
            'description' => 'Toggl: Support',
            'billable' => true,
        ]);

        $this->fakeConnections([[
            'id' => 'tv-covered-1',
            'deviceid' => '123456789',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:05:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:35:00')->toIso8601String(),
            'username' => 'Techniker',
        ]]);

        $result = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        // Keine Doppelbuchung: Sitzung wird als Nachweis an die vorhandene Zeit gekoppelt.
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => RemoteSupportPlugin::ID,
            'external_type' => RemoteSupportService::EXT_TYPE_SESSION,
            'external_id' => 'teamviewer:tv-covered-1',
            'referenceable_id' => $existing->id,
        ]);

        // Folgesync: Sitzung gilt als verarbeitet.
        $second = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );
        $this->assertSame(0, $second['linked']);
        $this->assertSame(1, $second['skipped']);
    }

    public function test_overlapping_time_of_other_customer_still_books_session(): void {
        $config = $this->enableTeamViewer();
        $asset = $this->deviceAssetWithCustomer('123456789');
        $ownerId = (int) $this->organization->owner_id;

        // Parallelarbeit: laufende Zeit gehört zu einem ANDEREN Kunden →
        // die Sitzung ist eigene, ungetrackte Arbeit und wird gebucht.
        $otherCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $otherProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $otherCustomer->id,
            'name' => 'Anderer Kunde',
            'is_default' => false,
        ]);
        TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $otherProject->id,
            'user_id' => $ownerId,
            'date' => '2026-05-26',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work,
            'description' => 'Toggl: anderer Kunde',
            'billable' => true,
        ]);

        $this->fakeConnections([[
            'id' => 'tv-parallel-1',
            'deviceid' => '123456789',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:05:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:35:00')->toIso8601String(),
            'username' => 'Techniker',
        ]]);

        $result = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['linked']);
        $this->assertSame(2, TimeEntry::query()->count());
    }

    public function test_import_is_idempotent(): void {
        $config = $this->enableTeamViewer();
        $this->deviceAssetWithCustomer('123456789');

        $this->fakeConnections([[
            'id' => 'tv-session-1',
            'deviceid' => '123456789',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:30:00')->toIso8601String(),
        ]]);

        $from = CarbonImmutable::parse('2026-05-25');
        $to = CarbonImmutable::parse('2026-05-27');

        $first = $this->service()->import($this->organization, $config, $from, $to);
        $second = $this->service()->import($this->organization, $config, $from, $to);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_session_without_known_device_is_unmatched_and_recorded(): void {
        $config = $this->enableTeamViewer();

        $this->fakeConnections([[
            'id' => 'tv-session-x',
            'deviceid' => 'unknown-id',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:15:00')->toIso8601String(),
        ]]);

        $result = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertSame(0, TimeEntry::query()->count());

        $this->assertDatabaseHas('remote_pending_sessions', [
            'organization_id' => $this->organization->id,
            'provider' => 'teamviewer',
            'remote_id' => 'unknown-id',
            'session_id' => 'tv-session-x',
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    public function test_recording_pending_is_idempotent_across_syncs(): void {
        $config = $this->enableTeamViewer();

        $this->fakeConnections([[
            'id' => 'tv-session-x',
            'deviceid' => 'unknown-id',
            'start_date' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 10:15:00')->toIso8601String(),
        ]]);

        $from = CarbonImmutable::parse('2026-05-25');
        $to = CarbonImmutable::parse('2026-05-27');
        $this->service()->import($this->organization, $config, $from, $to);
        $this->service()->import($this->organization, $config, $from, $to);

        $this->assertSame(1, RemotePendingSession::query()->count());
    }

    public function test_assign_pending_to_existing_asset_materializes_time_entry(): void {
        $this->enableTeamViewer();

        // Eine offene Pending-Session ohne zugeordnetes Gerät.
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => 'teamviewer',
            'remote_id' => 'unknown-id',
            'session_id' => 'tv-session-x',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:20:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
        ]);

        $result = $this->service()->assignPending($this->organization, 'teamviewer', 'unknown-id', $asset);

        $this->assertSame(1, $result['created']);
        $this->assertSame('unknown-id', $this->service()->remoteId($asset, TeamViewerClient::ID));

        $project = $asset->customer->defaultProject();
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(20, $entry->minutes);

        $this->assertDatabaseHas('remote_pending_sessions', [
            'session_id' => 'tv-session-x',
            'status' => RemotePendingSession::STATUS_IMPORTED,
            'time_entry_id' => $entry->id,
        ]);
    }

    public function test_inbox_group_booker_binds_asset_and_books(): void {
        // MVP-103: Auflösung der unbekannt-Geräte-Gruppe über die universelle
        // Zuordnungs-Inbox (Booker) — delegiert an assignPending.
        $this->enableTeamViewer();

        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => 'teamviewer',
            'remote_id' => 'inbox-dev',
            'session_id' => 'tv-inbox-1',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
        ]);

        $booker = app(\App\Plugins\RemoteSupport\RemoteSupportGroupBooker::class);

        $groups = $booker->groups($this->organization);
        $this->assertTrue($groups->contains(fn(array $g): bool => $g['group_key'] === 'teamviewer|inbox-dev'));

        $result = $booker->book($this->organization, 'teamviewer|inbox-dev', ['asset' => $asset->sqid]);

        $this->assertSame(1, $result['created']);
        $this->assertSame('inbox-dev', $this->service()->remoteId($asset, TeamViewerClient::ID), 'Geräte-ID gebunden');

        $entry = TimeEntry::query()->where('project_id', $asset->customer->defaultProject()->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(30, $entry->minutes);
    }

    public function test_panel_renders_only_for_remote_capable_categories(): void {
        config(['plugins.remote-support.enabled' => true]);
        $plugin = new RemoteSupportPlugin;

        $notebook = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'notebook',
        ]);
        $printer = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'printer',
        ]);
        $uncategorized = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => null,
        ]);

        $this->assertNotNull($plugin->renderActions('asset-show.aside', $notebook));
        $this->assertNull($plugin->renderActions('asset-show.aside', $printer));
        $this->assertNull($plugin->renderActions('asset-show.aside', $uncategorized));
    }

    public function test_dismiss_pending_marks_group_dismissed(): void {
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => 'anydesk',
            'remote_id' => '999',
            'session_id' => 'ad-1',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 10:05:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $count = $this->service()->dismissPending($this->organization, 'anydesk', '999');

        $this->assertSame(1, $count);
        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertDatabaseHas('remote_pending_sessions', [
            'session_id' => 'ad-1',
            'status' => RemotePendingSession::STATUS_DISMISSED,
        ]);
    }

    public function test_shared_remote_asset_records_pending_instead_of_booking(): void {
        $config = $this->enableTeamViewer();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
            'shared_remote' => true,
        ]);
        $this->service()->setRemoteId($asset, TeamViewerClient::ID, '555');

        $this->fakeConnections([[
            'id' => 'tv-shared-1',
            'deviceid' => '555',
            'start_date' => CarbonImmutable::parse('2026-05-26 09:00:00')->toIso8601String(),
            'end_date' => CarbonImmutable::parse('2026-05-26 09:30:00')->toIso8601String(),
        ]]);

        $result = $this->service()->import(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, TimeEntry::query()->count());

        $this->assertDatabaseHas('remote_pending_sessions', [
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'session_id' => 'tv-shared-1',
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    public function test_assign_shared_session_books_to_chosen_customer_default_project(): void {
        $this->enableTeamViewer();

        $sharedCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $sharedCustomer->id,
            'shared_remote' => true,
        ]);

        $target = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $row = RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => 'teamviewer',
            'remote_id' => '555',
            'session_id' => 'tv-shared-1',
            'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 09:40:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $result = $this->service()->assignSharedSessions($this->organization, collect([$row]), $target);

        $this->assertSame(1, $result['created']);

        $project = $target->defaultProject();
        $this->assertNotNull($project);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(40, $entry->minutes);

        $this->assertDatabaseHas('remote_pending_sessions', [
            'id' => $row->id,
            'status' => RemotePendingSession::STATUS_IMPORTED,
            'time_entry_id' => $entry->id,
        ]);
    }

    public function test_assign_shared_session_uses_explicit_project_when_given(): void {
        $this->enableTeamViewer();

        $sharedCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $sharedCustomer->id,
            'shared_remote' => true,
        ]);

        $target = Customer::factory()->create(['organization_id' => $this->organization->id]);
        /** @var \App\Models\Project $project */
        $project = $target->projects()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Sonderprojekt',
            'status' => \App\Enums\Project\ProjectStatus::Active->value,
        ]);

        $row = RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => 'teamviewer',
            'remote_id' => '555',
            'session_id' => 'tv-shared-2',
            'started_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:15:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $result = $this->service()->assignSharedSessions($this->organization, collect([$row]), $target, $project);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(15, $entry->minutes);
    }

    public function test_open_shared_sessions_excluded_from_unknown_groups(): void {
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'shared_remote' => true,
        ]);

        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => 'teamviewer',
            'remote_id' => '555',
            'session_id' => 'tv-shared-1',
            'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 09:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $this->assertTrue($this->service()->openPendingGroups($this->organization)->isEmpty());
        $this->assertSame(1, $this->service()->openSharedSessions($this->organization)->count());
    }

    public function test_session_for_customerless_asset_books_internal_maintenance_project(): void {
        $config = $this->enableTeamViewer();

        // Eigenes Gerät ohne Kunden und ohne Mehrkunden-Flag: interne Wartung.
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
        ]);
        $this->service()->setRemoteId($asset, TeamViewerClient::ID, '555000111');

        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: 'tv-own-1',
            remoteId: '555000111',
            startedAt: CarbonImmutable::parse('2026-07-20 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 09:30:00'),
        );

        $result = $this->service()->importSessions($this->organization, $config, [$session]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['pending']);
        $entry = TimeEntry::query()->firstOrFail();
        $project = Project::query()->findOrFail($entry->project_id);
        $this->assertNull($project->customer_id);
        $this->assertSame('Interne Wartung', $project->name);
        $this->assertDatabaseMissing('remote_pending_sessions', ['session_id' => 'tv-own-1']);
    }

    public function test_assign_pending_to_shared_asset_parks_sessions_instead_of_booking(): void {
        $config = $this->enableTeamViewer();

        // Unbekannte ID → Sitzung wartet ohne Asset in der Inbox.
        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: 'tv-shared-park-1',
            remoteId: '777000222',
            startedAt: CarbonImmutable::parse('2026-07-20 10:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 10:45:00'),
        );
        $this->service()->importSessions($this->organization, $config, [$session]);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
            'shared_remote' => true,
        ]);

        $result = $this->service()->assignPending($this->organization, TeamViewerClient::ID, '777000222', $asset);

        $this->assertSame(1, $result['pending']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertDatabaseHas('remote_pending_sessions', [
            'session_id' => 'tv-shared-park-1',
            'asset_id' => $asset->id,
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
        $this->assertSame(1, $this->service()->openSharedSessions($this->organization)->count());
    }

    public function test_assign_new_without_customer_creates_company_device_and_parks_sessions(): void {
        $config = $this->enableTeamViewer();

        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: 'tv-company-1',
            remoteId: '333222111',
            startedAt: CarbonImmutable::parse('2026-07-20 11:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 11:20:00'),
        );
        $this->service()->importSessions($this->organization, $config, [$session]);

        $response = $this->actingAs($this->orgAdmin())->post(route('admin.remote-support.pending.assign-new'), [
            'provider' => TeamViewerClient::ID,
            'remote_id' => '333222111',
            'name' => 'Büro-PC Empfang',
            'category_code' => 'workstation',
            'shared_remote' => '1',
        ]);

        $response->assertRedirect();
        $asset = Asset::query()->where('name', 'Büro-PC Empfang')->firstOrFail();
        $this->assertNull($asset->customer_id);
        $this->assertSame(\App\Enums\Asset\AssetOwnership::Organization, $asset->owned_by);
        $this->assertTrue($asset->shared_remote);
        $this->assertSame(0, TimeEntry::query()->count());
        $this->assertDatabaseHas('remote_pending_sessions', [
            'session_id' => 'tv-company-1',
            'asset_id' => $asset->id,
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
    }

    public function test_auto_booking_targets_foreign_customer_project(): void {
        $config = $this->enableTeamViewer();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $foreign->id,
        ]);
        $this->service()->setRemoteId($asset, TeamViewerClient::ID, '444555666');

        $session = new RemoteSession(
            provider: TeamViewerClient::ID,
            sessionId: 'tv-foreign-1',
            remoteId: '444555666',
            startedAt: CarbonImmutable::parse('2026-07-20 13:00:00'),
            endedAt: CarbonImmutable::parse('2026-07-20 13:30:00'),
        );

        $result = $this->service()->importSessions($this->organization, $config, [$session]);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->firstOrFail();
        $project = Project::query()->findOrFail($entry->project_id);
        $this->assertSame($foreign->id, (int) $project->foreign_customer_id);
        $this->assertSame($customer->id, (int) $project->customer_id);
    }

    public function test_assign_shared_sessions_with_foreign_customer_books_on_foreign_project(): void {
        $this->enableTeamViewer();

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
            'shared_remote' => true,
        ]);

        $row = RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '888999000',
            'session_id' => 'tv-foreign-shared-1',
            'started_at' => CarbonImmutable::parse('2026-07-20 14:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 14:40:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $result = $this->service()->assignSharedSessions($this->organization, [$row], $customer, null, null, $foreign);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->firstOrFail();
        $project = Project::query()->findOrFail($entry->project_id);
        $this->assertSame($foreign->id, (int) $project->foreign_customer_id);
        $this->assertDatabaseHas('remote_pending_sessions', [
            'id' => $row->id,
            'status' => RemotePendingSession::STATUS_IMPORTED,
        ]);
    }

    public function test_assign_shared_sessions_without_customer_books_internal_project(): void {
        $this->enableTeamViewer();

        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'shared_remote' => true,
        ]);

        $row = RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '121212121',
            'session_id' => 'tv-internal-1',
            'started_at' => CarbonImmutable::parse('2026-07-20 15:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 15:25:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->orgAdmin())->post(route('admin.remote-support.pending.assign-internal'), [
            'pending_ids' => [(string) $row->id],
        ]);

        $response->assertRedirect();
        $entry = TimeEntry::query()->firstOrFail();
        $project = Project::query()->findOrFail($entry->project_id);
        $this->assertNull($project->customer_id);
        $this->assertSame('Interne Wartung', $project->name);
        $this->assertDatabaseHas('remote_pending_sessions', [
            'id' => $row->id,
            'status' => RemotePendingSession::STATUS_IMPORTED,
            'time_entry_id' => $entry->id,
        ]);
    }

    public function test_open_pending_groups_search_filters_by_alias_id_and_note(): void {
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '111000111',
            'session_id' => 'tv-search-1',
            'alias' => 'buero-mueller',
            'note' => 'Druckertreiber',
            'started_at' => CarbonImmutable::parse('2026-07-20 08:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 08:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '222000222',
            'session_id' => 'tv-search-2',
            'started_at' => CarbonImmutable::parse('2026-07-20 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 09:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $service = $this->service();
        $this->assertSame(2, $service->openPendingGroups($this->organization)->count());
        $this->assertSame(1, $service->openPendingGroups($this->organization, 'MUELLER')->count());
        $this->assertSame(1, $service->openPendingGroups($this->organization, '222000')->count());
        $this->assertSame(1, $service->openPendingGroups($this->organization, 'drucker')->count());
        $this->assertSame(0, $service->openPendingGroups($this->organization, 'nix-da')->count());
    }

    public function test_open_shared_sessions_search_matches_asset_and_note(): void {
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'shared_remote' => true,
            'name' => 'Kanzlei-PC',
        ]);
        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $asset->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '333000333',
            'session_id' => 'tv-search-3',
            'note' => 'Jahresabschluss',
            'started_at' => CarbonImmutable::parse('2026-07-20 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 10:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $service = $this->service();
        $this->assertSame(1, $service->openSharedSessions($this->organization, 'kanzlei')->count());
        $this->assertSame(1, $service->openSharedSessions($this->organization, 'jahresabschluss')->count());
        $this->assertSame(0, $service->openSharedSessions($this->organization, 'unbekannt')->count());
    }

    public function test_pending_index_renders_with_search_and_pagination(): void {
        $this->enableTeamViewer();

        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => TeamViewerClient::ID,
            'remote_id' => '444000444',
            'session_id' => 'tv-search-4',
            'started_at' => CarbonImmutable::parse('2026-07-20 11:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 11:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->get(route('admin.remote-support.pending.index', ['q' => '444000']))
            ->assertOk()
            ->assertSee('444000444');

        $this->actingAs($admin)
            ->get(route('admin.remote-support.pending.index', ['q' => 'gibt-es-nicht']))
            ->assertOk()
            ->assertSee(__('Keine Treffer für die Suche.'));
    }
}
