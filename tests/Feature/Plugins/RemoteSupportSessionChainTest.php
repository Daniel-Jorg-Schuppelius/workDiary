<?php
/*
 * Created on   : Thu Nov 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportSessionChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, ExternalReferenceAlias};
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\RemoteSupport\Api\AnyDeskClient;
use App\Plugins\RemoteSupport\Enums\RemotePendingSessionStatus;
use App\Plugins\RemoteSupport\Models\RemotePendingSession;
use App\Plugins\RemoteSupport\Providers\RemoteSession;
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin};
use App\Plugins\RemoteSupport\Services\{RemoteDeviceRegistry, RemotePendingAssignmentService, RemoteSessionImporter};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Verkettung von Fernwartungs-Verbindungen (Befund 2026-11-05): Anbieter
 * liefern je Verbindung einen Datensatz — Reconnects und Wartezeiten
 * während eines Updates ergaben lauter Einzelbuchungen, Verbindungen unter
 * einer Minute standen mit 0 Minuten in der Liste.
 */
class RemoteSupportSessionChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string REMOTE_ID = '139711290';

    private User $owner;

    private Customer $customer;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $this->customer->id,
            'name' => 'DATEV',
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($this->asset, AnyDeskClient::ID, self::REMOTE_ID);
    }

    private function remoteSession(string $start, string $end, ?string $note = null, string $remoteId = self::REMOTE_ID): RemoteSession {
        return new RemoteSession(
            provider: AnyDeskClient::ID,
            sessionId: 'sess-' . $remoteId . '-' . str_replace([' ', ':'], '', $start),
            remoteId: $remoteId,
            startedAt: CarbonImmutable::parse($start, 'UTC'),
            endedAt: CarbonImmutable::parse($end, 'UTC'),
            note: $note,
        );
    }

    /**
     * @param  array<int, RemoteSession>  $sessions
     * @param  array<string, mixed>  $config
     * @return array{created: int, linked: int, skipped: int, unmatched: int, pending: int}
     */
    private function import(array $sessions, array $config = []): array {
        return (new RemoteSessionImporter)->importSessions(
            $this->organization,
            $config + ['default_billable' => true, 'default_user_id' => null],
            $sessions,
        );
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, TimeEntry> */
    private function entries(): \Illuminate\Database\Eloquent\Collection {
        return TimeEntry::query()->withoutGlobalScopes()->orderBy('started_at')->orderBy('id')->get();
    }

    private function aliasExists(RemoteSession $session, TimeEntry $entry): bool {
        return ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->where('plugin_id', RemoteSupportPlugin::ID)
            ->where('external_type', RemoteSessionImporter::EXT_TYPE_SESSION)
            ->where('external_id', AnyDeskClient::ID . ':' . $session->sessionId)
            ->where('referenceable_id', $entry->id)
            ->exists();
    }

    public function test_reconnect_within_tolerance_extends_entry(): void {
        $first = $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00');
        $second = $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00');

        $result = $this->import([$first, $second]);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['linked']);
        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame('2026-07-20 10:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 11:00:00', $entry->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(60, $entry->minutes);
        $this->assertTrue($this->aliasExists($second, $entry));
    }

    public function test_sessions_delivered_newest_first_are_chained_in_order(): void {
        $first = $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00');
        $second = $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00');

        $this->import([$second, $first]);

        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('2026-07-20 10:00:00', $entries->first()->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 11:00:00', $entries->first()->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_earlier_session_booked_after_its_follow_up_pulls_start_forward(): void {
        // CSV-Export neueste zuerst, zeilenweise gebucht wie im Import-Wizard.
        $importer = new RemoteSessionImporter;
        $config = ['default_billable' => true];
        $later = $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00');
        $earlier = $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00', 'Update eingespielt');

        $this->assertSame('created', $importer->bookSession($this->organization, $config, $later, $this->owner->id));
        $this->assertSame('linked', $importer->bookSession($this->organization, $config, $earlier, $this->owner->id));

        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame('2026-07-20 10:00:00', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 11:00:00', $entry->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(60, $entry->minutes);
        $this->assertSame('2026-07-20', $entry->date?->format('Y-m-d'));
        $this->assertSame('DATEV (Update eingespielt)', $entry->description);
        $this->assertTrue($this->aliasExists($earlier, $entry));
    }

    public function test_follow_up_in_later_sync_extends_existing_entry(): void {
        $this->import([$this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00')]);

        $result = $this->import([$this->remoteSession('2026-07-20 10:55:00', '2026-07-20 11:20:00')]); // Lücke 25 min

        $this->assertSame(1, $result['linked']);
        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('2026-07-20 11:20:00', $entries->first()->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_gap_beyond_tolerance_creates_own_entry(): void {
        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 11:01:00', '2026-07-20 11:30:00'), // Lücke 31 min > 30
        ]);

        $this->assertCount(2, $this->entries());
    }

    public function test_organization_setting_overrides_tolerance(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'enabled' => true,
            'settings' => ['merge_gap_minutes' => '5'],
        ]);
        $config = RemoteSupportConfig::resolve($this->organization->id);
        $this->assertSame(5, $config['merge_gap_minutes']);

        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 10:40:00', '2026-07-20 11:00:00'), // Lücke 10 min > 5
        ], $config);

        $this->assertCount(2, $this->entries());
    }

    public function test_zero_tolerance_switches_chaining_off(): void {
        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 10:30:05', '2026-07-20 11:00:00'),
        ], ['merge_gap_minutes' => 0]);

        $this->assertCount(2, $this->entries());
    }

    public function test_note_of_follow_up_lands_in_description(): void {
        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 10:31:00', '2026-07-20 10:50:00', 'Update eingespielt'),
            $this->remoteSession('2026-07-20 10:52:00', '2026-07-20 11:00:00', 'Neustart'),
            $this->remoteSession('2026-07-20 11:02:00', '2026-07-20 11:10:00', 'neustart'),
        ]);

        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('DATEV (Update eingespielt · Neustart)', $entries->first()->description);
    }

    public function test_note_with_keyword_for_other_project_stays_separate(): void {
        $datev = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'DATEV-Umstellung',
            'is_default' => false,
        ]);

        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00', 'DATEV-Umstellung Hotfix'),
        ]);

        $entries = $this->entries();
        $this->assertCount(2, $entries);
        $this->assertSame($this->customer->defaultProjectOrCreate()->id, $entries->first()->project_id);
        $this->assertSame($datev->id, $entries->last()->project_id);
    }

    public function test_follow_up_without_note_joins_keyword_entry(): void {
        // Prod-Muster: Notiz an der 57-Sekunden-Verbindung, die Arbeit in der Folgeverbindung ohne Notiz.
        $datev = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'DATEV-Umstellung',
            'is_default' => false,
        ]);

        $this->import([
            $this->remoteSession('2026-07-20 11:25:30', '2026-07-20 11:26:27', 'DATEV-Umstellung Update'),
            $this->remoteSession('2026-07-20 11:26:36', '2026-07-20 12:00:34'),
            $this->remoteSession('2026-07-20 12:25:31', '2026-07-20 13:00:34'),
        ]);

        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame($datev->id, $entry->project_id);
        $this->assertSame('2026-07-20 11:25:30', $entry->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 13:00:34', $entry->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame('DATEV (DATEV-Umstellung Update)', $entry->description);
    }

    public function test_exported_predecessor_is_not_extended(): void {
        $this->import([$this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00')]);
        TimeEntry::query()->withoutGlobalScopes()->update(['exported' => true]);

        $result = $this->import([$this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00')]);

        $this->assertSame(1, $result['created']);
        $entries = $this->entries();
        $this->assertCount(2, $entries);
        $this->assertSame('2026-07-20 10:30:00', $entries->first()->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_other_device_of_same_customer_stays_separate(): void {
        $server = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $this->customer->id,
            'name' => 'SERVER',
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($server, AnyDeskClient::ID, '555000111');

        $this->import([
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00'),
            $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00', null, '555000111'),
        ]);

        $this->assertCount(2, $this->entries());
    }

    public function test_short_session_is_booked_as_one_minute(): void {
        $this->import([$this->remoteSession('2026-07-20 20:08:24', '2026-07-20 20:09:17', 'Abschluss')]);

        $entry = $this->entries()->first();
        $this->assertSame('2026-07-20 20:09:24', $entry->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(1, $entry->minutes);
    }

    public function test_three_snippets_become_one_entry(): void {
        $this->import([
            $this->remoteSession('2026-07-20 09:35:49', '2026-07-20 09:36:04'),
            $this->remoteSession('2026-07-20 09:32:32', '2026-07-20 09:32:44'),
            $this->remoteSession('2026-07-20 09:31:56', '2026-07-20 09:32:10'),
        ]);

        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('2026-07-20 09:31:56', $entries->first()->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 09:36:04', $entries->first()->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(4, $entries->first()->minutes);
    }

    public function test_pending_assignment_chains_parked_sessions(): void {
        $unknown = '777888999';
        $this->import([
            $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00', null, $unknown),
            $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00', null, $unknown),
        ]);
        $this->assertSame(2, RemotePendingSession::query()->where('status', RemotePendingSessionStatus::Open)->count());

        $device = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $this->customer->id,
            'name' => 'NB-01',
        ]);
        $result = (new RemotePendingAssignmentService(new RemoteDeviceRegistry, new RemoteSessionImporter))
            ->assignPending($this->organization, AnyDeskClient::ID, $unknown, $device);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['linked']);
        $entries = $this->entries();
        $this->assertCount(1, $entries);
        $this->assertSame('2026-07-20 10:00:00', $entries->first()->started_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-20 11:00:00', $entries->first()->ended_at?->format('Y-m-d H:i:s'));
    }

    public function test_merge_command_chains_existing_entries_and_lifts_short_ones(): void {
        $first = $this->remoteSession('2026-07-20 10:00:00', '2026-07-20 10:30:00');
        $second = $this->remoteSession('2026-07-20 10:35:00', '2026-07-20 11:00:00', 'Update eingespielt');
        $this->import([$first, $second], ['merge_gap_minutes' => 0]);
        $exportedA = $this->remoteSession('2026-07-21 09:00:00', '2026-07-21 09:30:00');
        $exportedB = $this->remoteSession('2026-07-21 09:40:00', '2026-07-21 10:00:00');
        $this->import([$exportedA, $exportedB], ['merge_gap_minutes' => 0]);
        $lonely = $this->remoteSession('2026-07-22 20:08:24', '2026-07-22 20:09:17', 'Abschluss');
        $this->import([$lonely], ['merge_gap_minutes' => 0]);

        $entries = $this->entries();
        $this->assertCount(5, $entries);
        [$head, $follower, $expA, $expB, $short] = $entries->all();
        $expA->forceFill(['exported' => true])->save();
        // Bestand vor der Mindestminute: 53 Sekunden, 0 Minuten.
        $short->forceFill(['ended_at' => '2026-07-22 20:09:17'])->save();
        $this->assertSame(0, $short->fresh()->minutes);

        // Ein Versuch, der dem Folgeeintrag zugeordnet ist, wandert mit.
        $attempt = RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'provider' => AnyDeskClient::ID,
            'remote_id' => self::REMOTE_ID,
            'session_id' => 'att-1',
            'started_at' => CarbonImmutable::parse('2026-07-20 10:34:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 10:34:00'),
            'status' => RemotePendingSessionStatus::Imported,
            'time_entry_id' => $follower->id,
        ]);

        $this->artisan('remote:merge-sessions', ['--days' => 365, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();
        $this->assertCount(5, $this->entries());

        $this->artisan('remote:merge-sessions', ['--days' => 365])
            ->expectsOutputToContain('1 Ketten, 1 Einträge zusammengeführt, 1 auf eine Minute gesetzt, 1 übersprungen')
            ->assertSuccessful();

        $entries = $this->entries();
        $this->assertCount(4, $entries);
        $merged = $entries->first();
        $this->assertSame($head->id, $merged->id);
        $this->assertSame('2026-07-20 11:00:00', $merged->ended_at?->format('Y-m-d H:i:s'));
        $this->assertSame(60, $merged->minutes);
        $this->assertSame('DATEV (Update eingespielt)', $merged->description);
        $this->assertNull(TimeEntry::query()->withoutGlobalScopes()->find($follower->id));
        $this->assertTrue($this->aliasExists($second, $merged));
        $this->assertFalse(ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('external_id', AnyDeskClient::ID . ':' . $second->sessionId)
            ->exists());
        $this->assertSame($head->id, $attempt->fresh()->time_entry_id);

        // Exportierter Kopf bleibt, sein Nachfolger auch.
        $this->assertSame('2026-07-21 09:30:00', $expA->fresh()->ended_at?->format('Y-m-d H:i:s'));
        $this->assertNotNull(TimeEntry::query()->withoutGlobalScopes()->find($expB->id));

        // Einzelne Kurzverbindung: eine Minute.
        $this->assertSame(1, $short->fresh()->minutes);
        $this->assertSame('2026-07-22 20:09:24', $short->fresh()->ended_at?->format('Y-m-d H:i:s'));
    }
}
