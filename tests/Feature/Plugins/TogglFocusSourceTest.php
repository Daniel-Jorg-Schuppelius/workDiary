<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglFocusSourceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem, IntegrationOutboxEntry};
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Support\{MatchingTimeImportService, RemoteTimeFingerprint, TimeWritebackDispatcher};
use App\Plugins\Toggl\Api\{TogglApiClient, TogglClientFactory, TogglFocusApiClient};
use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\Services\{TogglExportService, TogglImportService, TogglOutboxDispatcher};
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Quellenwahl des Toggl-Plugins (Feature 178): genau ein Produkt wird gelesen
 * und beschrieben, und ein Wechsel von Track auf das neue Toggl übernimmt den
 * Bestand, statt ihn doppelt anzulegen oder zu löschen.
 */
class TogglFocusSourceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const FOCUS = 'https://focus.toggl.com/api';

    private const ORG = 900;

    private const WS = 77;

    private const ENTRIES_URL = self::FOCUS . '/organizations/' . self::ORG . '/workspaces/' . self::WS . '/time-entries';

    private User $dev;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Queue::fake();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
        $this->dev = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'dev@example.com']);
    }

    /** @return array<string, mixed> */
    private function enable(array $settings = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'source' => 'focus',
                'api_token' => 'track-token',
                'focus_api_key' => 'toggl_sk_test',
                'focus_organization_id' => (string) self::ORG,
                'focus_workspace_id' => (string) self::WS,
            ], $settings),
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    private function project(string $customer = 'Acme', string $name = 'Website'): Project {
        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $customer])->id,
            'name' => $name,
            'is_default' => false,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function row(int $id, string $start, ?int $duration, array $overrides = []): array {
        return array_merge([
            'time_entry_id' => $id,
            'description' => 'Bugfix',
            'start_utc' => $start,
            'duration' => $duration,
            'effective_billable' => true,
            'user_account_id' => 501,
            'project_id' => 31,
            'project_name' => 'Website',
            'client_id' => 41,
            'client_name' => 'Acme',
            'tag_names' => ['Support'],
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $extra
     */
    private function fakeFocus(array $rows, array $extra = []): FakePluginHttp {
        return FakePluginHttp::fake($extra + [
            self::FOCUS . '/organizations/' . self::ORG . '/users*' => FakePluginHttp::response([
                ['user_account_id' => 501, 'email' => 'dev@example.com', 'name' => 'Dev'],
            ]),
            self::FOCUS . '/reports/workspaces/' . self::WS . '/query*' => FakePluginHttp::response([
                'data_json_row' => $rows,
                'total_count' => count($rows),
            ]),
        ]);
    }

    private function import(array $config): array {
        return (new TogglImportService)->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-10-19'),
            CarbonImmutable::parse('2026-10-21'),
        );
    }

    private function importedEntry(Project $project, string $key, string $start, string $end, array $payload = []): TimeEntry {
        $entry = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->dev->id,
            'date' => substr($start, 0, 10),
            'started_at' => CarbonImmutable::parse($start),
            'ended_at' => CarbonImmutable::parse($end),
            'kind' => TimeEntryKind::Work,
            'description' => 'Bugfix',
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->id,
            'external_id' => $key,
            'payload' => $payload + ['source' => 'api', 'workspace_id' => 1, 'project_id' => 9],
            'synced_at' => now(),
        ]);

        return $entry;
    }

    private function idReference(string $type, string $id, Project $project): void {
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => $type,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => $id,
            'synced_at' => now(),
        ]);
    }

    public function test_factory_builds_only_the_client_of_the_selected_source(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'track-token', 'focus_api_key' => 'toggl_sk_test'],
        ]);
        $this->assertInstanceOf(TogglApiClient::class, app(TogglClientFactory::class)->for($this->organization->id), 'Bestand ohne Auswahl bleibt bei Track');

        PluginSetting::query()->where('organization_id', $this->organization->id)->firstOrFail()
            ->forceFill(['settings' => ['api_token' => 'track-token', 'focus_api_key' => 'toggl_sk_test', 'source' => 'focus']])->save();
        $client = app(TogglClientFactory::class)->for($this->organization->id);
        $this->assertInstanceOf(TogglFocusApiClient::class, $client);
        $this->assertSame(TogglSource::Focus, $client->source());
    }

    public function test_focus_import_books_entries_under_focus_keys_and_id_types(): void {
        $config = $this->enable();
        $project = $this->project();
        $fake = $this->fakeFocus([
            $this->row(9001, '2026-10-20T09:00:00Z', 2700),
            // Laufender Timer: noch keine Dauer.
            $this->row(9002, '2026-10-20T11:00:00Z', null),
        ]);

        $result = $this->import($config);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->sole();
        $this->assertSame(45, $entry->minutes);
        $this->assertSame($this->dev->id, $entry->user_id);
        $this->assertDatabaseHas('external_references', [
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'external_id' => 'focus:9001',
            'referenceable_id' => $entry->id,
        ]);
        $this->assertDatabaseHas('external_references', ['external_type' => 'focus_project_id', 'external_id' => '31', 'referenceable_id' => $project->id]);
        $this->assertDatabaseMissing('external_references', ['external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID, 'external_id' => '31']);

        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return str_contains((string) $request->getUri(), '/reports/workspaces/77/query')
                && $request->getHeaderLine('Authorization') === 'Bearer toggl_sk_test'
                && ($body['period'] ?? null) === ['from' => '2026-10-19', 'to' => '2026-10-21']
                && ($body['pagination']['page'] ?? null) === 1;
        });
        $fake->assertNotSent(fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), 'api.track.toggl.com'));
    }

    public function test_workspaces_come_from_the_account_service_when_not_configured(): void {
        $fake = FakePluginHttp::fake([
            'https://accounts.toggl.com/org/api/organizations/me' => FakePluginHttp::response([
                ['id' => self::ORG, 'name' => 'Firma', 'workspaces' => [
                    ['id' => self::WS, 'name' => 'Neues Toggl', 'toggl_products' => ['focus']],
                    ['id' => 78, 'name' => 'Nur Track', 'toggl_products' => ['track']],
                ]],
            ]),
        ]);

        $client = new TogglFocusApiClient('toggl_sk_test');

        $this->assertSame([['id' => self::WS, 'name' => 'Neues Toggl']], $client->workspaces());
        $this->assertTrue($client->isFetchComplete());
        $fake->assertSent(fn (RequestInterface $request): bool => $request->getHeaderLine('Authorization') === 'Bearer toggl_sk_test');
    }

    public function test_switch_adopts_track_entries_instead_of_booking_them_twice(): void {
        $config = $this->enable();
        $project = $this->project();
        $entry = $this->importedEntry($project, 'toggl:111', '2026-10-20 09:00:00', '2026-10-20 09:45:00');
        $this->idReference(MatchingTimeImportService::EXT_TYPE_PROJECT_ID, '9', $project);
        // Die Live-Synchronisation verschiebt Sekunden — innerhalb einer Minute ist es derselbe Eintrag.
        $this->fakeFocus([$this->row(9001, '2026-10-20T09:00:20Z', 2680)]);

        $result = $this->import($config);

        $this->assertSame(1, $result['adopted']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, TimeEntry::query()->count());
        $reference = ExternalReference::query()->where('referenceable_id', $entry->id)->where('external_type', MatchingTimeImportService::EXT_TYPE_ENTRY)->sole();
        $this->assertSame('focus:9001', $reference->external_id);
        $this->assertSame('toggl:111', $reference->payload['previous_key']);
        $this->assertSame(self::WS, $reference->payload['workspace_id']);
        $this->assertSame(31, $reference->payload['project_id']);
        $this->assertDatabaseHas('external_references', ['external_type' => 'focus_project_id', 'external_id' => '31', 'referenceable_id' => $project->id]);
        // Die Track-Zuordnung bleibt für einen Rückwechsel.
        $this->assertDatabaseHas('external_references', ['external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID, 'external_id' => '9']);

        $second = $this->import($config);
        $this->assertSame(0, $second['adopted']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(0, $second['updated'], 'Der Fingerabdruck steht nach der Übernahme auf dem neuen Fremdstand');
    }

    public function test_deletion_detection_only_sees_keys_of_the_read_source(): void {
        $config = $this->enable();
        $project = $this->project();
        $trackOnly = $this->importedEntry($project, 'toggl:222', '2026-10-20 13:00:00', '2026-10-20 14:00:00');
        $goneInFocus = $this->importedEntry($project, 'focus:9100', '2026-10-20 15:00:00', '2026-10-20 16:00:00', ['workspace_id' => self::WS]);
        $this->fakeFocus([$this->row(9001, '2026-10-20T09:00:00Z', 2700)]);

        $result = $this->import($config);

        $this->assertSame(1, $result['removed']);
        $this->assertNotNull(TimeEntry::query()->find($trackOnly->id), 'Ein Track-Eintrag ist kein Löschsignal des neuen Toggl');
        $this->assertNull(TimeEntry::query()->find($goneInFocus->id));
    }

    public function test_open_track_case_is_replaced_by_the_focus_case(): void {
        $config = $this->enable();
        $old = IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'toggl:333',
            'dedupe_key' => 'entry:toggl:333',
            'group_key' => 'ws1|nobody|unknown',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxStatus::Open,
            'remote_snapshot' => [
                'entry_key' => 'toggl:333',
                'started_at' => '2026-10-20T11:00:00+00:00',
                'ended_at' => '2026-10-20T12:00:00+00:00',
                'user_email' => 'dev@example.com',
            ],
            'display_title' => 'Unknown',
            'occurred_at' => CarbonImmutable::parse('2026-10-20 11:00:00'),
        ]);
        $this->fakeFocus([$this->row(9003, '2026-10-20T11:00:00Z', 3600, ['project_id' => 32, 'project_name' => 'Unknown', 'client_id' => 42, 'client_name' => 'Nobody'])]);

        $this->import($config);

        $this->assertSame(IntegrationInboxStatus::Dismissed, $old->fresh()->status);
        $open = IntegrationInboxItem::query()->where('status', IntegrationInboxStatus::Open)->sole();
        $this->assertSame('entry:focus:9003', $open->dedupe_key);
    }

    public function test_booking_a_track_case_after_the_switch_keeps_track_id_types(): void {
        $this->enable();
        $project = $this->project();
        IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'toggl:777',
            'dedupe_key' => 'entry:toggl:777',
            'group_key' => 'acme|website',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxStatus::Open,
            'remote_snapshot' => [
                'source' => 'api',
                'entry_key' => 'toggl:777',
                'client_name' => 'Acme',
                'project_name' => 'Website',
                'started_at' => '2026-10-20T08:00:00+00:00',
                'ended_at' => '2026-10-20T09:00:00+00:00',
                'user_email' => 'dev@example.com',
                'client_id' => 5,
                'project_id' => 9,
            ],
            'display_title' => 'Website',
            'occurred_at' => CarbonImmutable::parse('2026-10-20 08:00:00'),
        ]);

        (new TogglImportService)->bookInboxGroup($this->organization, 'acme|website', $project->customer, $project, $this->dev->id);

        $this->assertDatabaseHas('external_references', ['external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID, 'external_id' => '9', 'referenceable_id' => $project->id]);
        $this->assertDatabaseMissing('external_references', ['external_type' => 'focus_project_id', 'external_id' => '9']);
        $this->assertDatabaseHas('external_references', ['external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY, 'external_id' => 'toggl:777']);
    }

    public function test_writeback_patches_focus_entries_and_skips_track_keys(): void {
        $this->enable(['writeback' => true]);
        $project = $this->project();
        $start = CarbonImmutable::parse('2026-10-20 09:00:00');
        $focusEntry = $this->importedEntry($project, 'focus:9001', '2026-10-20 09:00:00', '2026-10-20 10:00:00', [
            'workspace_id' => self::WS,
            'project_id' => null,
            'fingerprint' => RemoteTimeFingerprint::fromParts($start, $start->addHour(), 'Bugfix', null, false),
        ]);
        $trackEntry = $this->importedEntry($project, 'toggl:555', '2026-10-20 11:00:00', '2026-10-20 12:00:00');
        $fake = FakePluginHttp::fake([
            self::ENTRIES_URL . '/9001' => fn (RequestInterface $request): Psr7Response => $request->getMethod() === 'GET'
                ? FakePluginHttp::response(['id' => 9001, 'start' => '2026-10-20T09:00:00Z', 'duration' => 3600, 'description' => 'Bugfix', 'billable' => false])
                : new Psr7Response(204),
        ]);

        $focusEntry->update(['description' => 'Korrigiert']);
        $trackEntry->update(['description' => 'Auch korrigiert']);
        $operation = TimeWritebackDispatcher::updateOperation(TogglPlugin::ID);
        foreach (IntegrationOutboxEntry::query()->where('operation', $operation)->get() as $outbox) {
            $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($outbox));
        }

        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'PATCH'
                && (string) $request->getUri() === self::ENTRIES_URL . '/9001'
                && ($body['description'] ?? null) === 'Korrigiert'
                && ($body['duration'] ?? null) === 3600;
        });
        $fake->assertNotSent(fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), '555'));
    }

    public function test_export_creates_focus_entries_with_focus_keys(): void {
        $config = $this->enable(['export_enabled' => true]);
        $project = $this->project();
        $this->idReference('focus_project_id', '31', $project);
        // Eine Track-Zuordnung allein macht das Projekt für das neue Toggl nicht exportierbar.
        $trackOnly = $this->project('Beta', 'Nur Track');
        $this->idReference(MatchingTimeImportService::EXT_TYPE_PROJECT_ID, '9', $trackOnly);
        foreach ([$project, $trackOnly] as $target) {
            TimeEntry::query()->create([
                'organization_id' => $this->organization->id,
                'project_id' => $target->id,
                'user_id' => $this->dev->id,
                'date' => '2026-10-20',
                'started_at' => CarbonImmutable::parse('2026-10-20 10:00:00'),
                'ended_at' => CarbonImmutable::parse('2026-10-20 11:00:00'),
                'kind' => TimeEntryKind::Work,
                'description' => 'Fernwartung',
            ]);
        }
        $fake = FakePluginHttp::fake([
            self::ENTRIES_URL => FakePluginHttp::response(['id' => 9500, 'start' => '2026-10-20T10:00:00Z', 'duration' => 3600, 'description' => 'Fernwartung', 'project_id' => 31, 'billable' => false], 201),
        ]);

        $result = (new TogglExportService)->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $reference = ExternalReference::query()->where('external_type', MatchingTimeImportService::EXT_TYPE_ENTRY)->sole();
        $this->assertSame('focus:9500', $reference->external_id);
        $this->assertSame(self::WS, $reference->payload['workspace_id']);
        $this->assertNotSame('', $reference->payload['fingerprint']);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && ($body['duration'] ?? null) === 3600
                && ($body['type'] ?? null) === 'activity'
                && ($body['project_id'] ?? null) === 31;
        });
    }
}
