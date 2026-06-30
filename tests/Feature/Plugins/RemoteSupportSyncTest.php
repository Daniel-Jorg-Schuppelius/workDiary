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
use App\Models\{Asset, Customer, PluginSetting, RemotePendingSession, TimeEntry, User};
use App\Plugins\RemoteSupport\Providers\TeamViewerClient;
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin, RemoteSupportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
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
        Http::fake([
            'https://webapi.teamviewer.com/api/v1/reports/connections*' => Http::response([
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
}
