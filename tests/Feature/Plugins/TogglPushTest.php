<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, PluginSetting, Project, Tag, TimeEntry, User};
use App\Plugins\Support\MatchingTimeImportService;
use App\Plugins\Toggl\{TogglConfig, TogglExportService, TogglImportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Spiegelung workDiary → Toggl ({@see TogglExportService}): lokal erfasste
 * Zeiten gemappter Projekte werden in Toggl angelegt, bleiben aber lokal
 * abrechenbar (`exported` bleibt false). Duplikat-Schutz gegen das
 * Import-Echo über die zusätzliche `entry`-Reference mit Response-Fingerprint.
 */
class TogglPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CREATE_URL = 'https://api.track.toggl.com/api/v9/workspaces/4711/time_entries';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->user->id])->save();
    }

    private function service(): TogglExportService {
        return new TogglExportService;
    }

    /** @return array<string, mixed> */
    private function config(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'api_token' => 'test-token',
                'workspace_id' => '4711',
                'export_enabled' => true,
            ], $extra),
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    private function mappedProject(string $togglProjectId = '9'): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Fernwartung',
            'is_default' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => $togglProjectId,
            'synced_at' => now(),
        ]);

        return $project;
    }

    private function timeEntry(Project $project, array $attributes = []): TimeEntry {
        return TimeEntry::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-26',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'kind' => TimeEntryKind::Work,
            'description' => 'Fernwartung AnyDesk',
            'exported' => false,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function createdResponse(int $id = 9001): array {
        return [
            'id' => $id,
            'workspace_id' => 4711,
            'project_id' => 9,
            'start' => '2026-05-26T10:00:00+00:00',
            'stop' => '2026-05-26T11:00:00+00:00',
            'description' => 'Fernwartung AnyDesk',
            'billable' => false,
        ];
    }

    public function test_push_creates_remote_entry_and_keeps_entry_billable_locally(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);
        $tag = Tag::create(['name' => 'AnyDesk', 'organization_id' => $this->organization->id]);
        $entry->tags()->sync([$tag->id]);

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame([], $result['errors']);

        $fake->assertSent(function ($request): bool {
            if ((string) $request->getUri() !== self::CREATE_URL || $request->getMethod() !== 'POST') {
                return false;
            }
            $body = (array) json_decode((string) $request->getBody(), true);

            return ($body['created_with'] ?? null) === 'workDiary'
                && ($body['project_id'] ?? null) === 9
                && ($body['workspace_id'] ?? null) === 4711
                && ($body['tags'] ?? null) === ['AnyDesk']
                && str_starts_with((string) ($body['start'] ?? ''), '2026-05-26T10:00:00')
                && str_starts_with((string) ($body['stop'] ?? ''), '2026-05-26T11:00:00');
        });

        // Beide Referenzen: Push-Idempotenz + Import-Echo-Schutz.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'pushed_entry',
            'referenceable_id' => $entry->id,
            'external_id' => '9001',
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_id' => $entry->id,
            'external_id' => 'toggl:9001',
        ]);
        $entryRef = ExternalReference::query()
            ->forPlugin($this->organization->id, TogglPlugin::ID, 'entry')
            ->forExternalId('toggl:9001')
            ->firstOrFail();
        $this->assertNotSame('', (string) ($entryRef->payload['fingerprint'] ?? ''));

        // Spiegelung, kein Rückbuchen: lokal bleibt der Eintrag abrechenbar.
        $this->assertFalse((bool) $entry->fresh()->exported);
    }

    public function test_push_is_idempotent_via_query_exclusion(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $first = $this->service()->exportPending($this->organization, $config);
        $second = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $first['pushed']);
        $this->assertSame(0, $second['pushed']);
        // Gepushte Einträge fallen aus der Kandidaten-Query (nicht nur skip) —
        // sonst wüchse `skipped` mit jedem Lauf, weil exported false bleibt.
        $this->assertSame(0, $second['skipped']);

        $posts = array_filter($fake->recorded(), fn (array $r): bool => $r['request']->getMethod() === 'POST');
        $this->assertCount(1, $posts);
    }

    public function test_unmapped_project_entries_are_not_candidates(): void {
        $config = $this->config();
        $unmapped = Project::factory()->create(['organization_id' => $this->organization->id, 'is_default' => false]);
        $this->timeEntry($unmapped);
        $this->mappedProject(); // Mapping existiert, aber ohne Einträge

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    public function test_export_disabled_reports_error_and_sends_nothing(): void {
        $config = $this->config(['export_enabled' => false]);
        $this->timeEntry($this->mappedProject());

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertNotSame([], $result['errors']);
        $fake->assertNothingSent();
    }

    public function test_imported_entries_are_never_pushed_back(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $entry = $this->timeEntry($project);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'referenceable_type' => $entry->getMorphClass(),
            'referenceable_id' => $entry->getKey(),
            'external_id' => 'toggl:111',
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    /** Der Kern-Integrationstest: Push → nächster Import erzeugt KEIN Duplikat. */
    public function test_pushed_entry_survives_next_import_without_duplicate(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);

        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);
        $this->service()->exportPending($this->organization, $config);

        // Nächster stündlicher Sync liefert den soeben gepushten Eintrag zurück.
        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response([$this->createdResponse()], 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response([
                'email' => 'tech@example.com',
                'clients' => [['id' => 5, 'name' => 'Acme']],
                'projects' => [['id' => 9, 'name' => 'Fernwartung', 'client_id' => 5, 'workspace_id' => 4711]],
            ], 200),
        ]);

        $result = (new TogglImportService)->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_running_and_zero_minute_entries_are_not_candidates(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-26 12:00:00'), 'ended_at' => null, 'minutes' => 30]);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-26 13:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-26 13:00:00')]);

        $fake = FakePluginHttp::fake();

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    public function test_api_error_counts_as_failed_and_rate_limit_aborts(): void {
        $config = $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project);
        $this->timeEntry($project, ['started_at' => CarbonImmutable::parse('2026-05-27 10:00:00'), 'ended_at' => CarbonImmutable::parse('2026-05-27 11:00:00'), 'date' => '2026-05-27']);

        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response(['error' => 'bad request'], 400),
        ]);
        $result = $this->service()->exportPending($this->organization, $config);
        $this->assertSame(2, $result['failed']);
        $this->assertSame(0, $result['pushed']);

        // 429 bricht den Lauf ab — der zweite Eintrag bleibt unversucht.
        FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response([], 429),
        ]);
        $result = $this->service()->exportPending($this->organization, $config);
        $this->assertSame(0, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotSame([], $result['errors']);
    }

    public function test_single_workspace_is_resolved_when_not_configured(): void {
        $config = $this->config(['workspace_id' => null]);
        $project = $this->mappedProject();
        $this->timeEntry($project);

        $fake = FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/workspaces' => FakePluginHttp::response([['id' => 4711, 'name' => 'Solo WS']], 200),
            self::CREATE_URL => FakePluginHttp::response($this->createdResponse(), 200),
        ]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $fake->assertSent(fn ($request): bool => (string) $request->getUri() === self::CREATE_URL && $request->getMethod() === 'POST');
    }
}
