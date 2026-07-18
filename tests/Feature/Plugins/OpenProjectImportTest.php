<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Services\{OpenProjectImportService, OpenProjectStructureSync};
use App\Services\ProjectMergeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class OpenProjectImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://op.example.test/api/v3';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    private function service(): OpenProjectImportService {
        return new OpenProjectImportService(new OpenProjectStructureSync);
    }

    private function enable(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'base_url' => 'https://op.example.test',
                'api_token' => 'test-token',
            ], $extra),
        ]);

        return OpenProjectConfig::resolve($this->organization->id);
    }

    /** @param array<int, array<string, mixed>> $elements */
    private function hal(array $elements): array {
        return ['_embedded' => ['elements' => $elements], 'total' => count($elements)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $projects
     * @param  array<int, array<string, mixed>>  $workPackages
     * @param  array<int, array<string, mixed>>  $timeEntries
     */
    private function fakeApi(array $projects, array $workPackages, array $timeEntries): void {
        FakePluginHttp::fake([
            self::BASE . '/projects*' => FakePluginHttp::response($this->hal($projects), 200),
            self::BASE . '/work_packages*' => FakePluginHttp::response($this->hal($workPackages), 200),
            self::BASE . '/users*' => FakePluginHttp::response($this->hal([]), 200),
            self::BASE . '/time_entries*' => FakePluginHttp::response($this->hal($timeEntries), 200),
        ]);
    }

    private function timeEntryPayload(int $id, int $projectId, string $hours, string $spentOn, ?int $wpId = null): array {
        $links = ['project' => ['href' => "/api/v3/projects/{$projectId}", 'title' => 'Website']];
        if ($wpId !== null) {
            $links['workPackage'] = ['href' => "/api/v3/work_packages/{$wpId}", 'title' => 'Login bug'];
        }
        $links['user'] = ['href' => '/api/v3/users/3', 'title' => 'Tech'];

        return [
            'id' => $id,
            'hours' => $hours,
            'spentOn' => $spentOn,
            'comment' => ['raw' => 'Bugfix'],
            '_links' => $links,
        ];
    }

    public function test_import_books_entry_in_name_matched_project(): void {
        $config = $this->enable();
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Website',
            'is_default' => false,
        ]);

        $this->fakeApi(
            projects: [['id' => 9, 'name' => 'Website', 'active' => true, '_links' => []]],
            workPackages: [],
            timeEntries: [$this->timeEntryPayload(111, 9, 'PT0H45M', '2026-05-26')],
        );

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(45, $entry->minutes);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectImportService::EXT_TYPE_ENTRY,
            'external_id' => 'openproject:te:111',
            'referenceable_id' => $entry->id,
        ]);
        // Projekt-Mapping wurde gemerkt.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectStructureSync::EXT_TYPE_PROJECT,
            'external_id' => '9',
            'referenceable_id' => $project->id,
        ]);
    }

    public function test_import_is_idempotent(): void {
        $config = $this->enable();
        Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Website',
            'is_default' => false,
        ]);

        $this->fakeApi(
            projects: [['id' => 9, 'name' => 'Website', 'active' => true, '_links' => []]],
            workPackages: [],
            timeEntries: [$this->timeEntryPayload(111, 9, 'PT0H30M', '2026-05-26')],
        );

        $from = CarbonImmutable::parse('2026-05-25');
        $to = CarbonImmutable::parse('2026-05-27');

        $first = $this->service()->importFromApi($this->organization, $config, $from, $to);
        $second = $this->service()->importFromApi($this->organization, $config, $from, $to);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_unmatched_project_lands_in_inbox(): void {
        $config = $this->enable();

        $this->fakeApi(
            projects: [['id' => 9, 'name' => 'Mystery', 'active' => true, '_links' => []]],
            workPackages: [],
            timeEntries: [$this->timeEntryPayload(222, 9, 'PT0H15M', '2026-05-26')],
        );

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertSame(0, TimeEntry::query()->count());

        // MVP-103 Phase 2b: unmatched landet in der universellen Inbox (gruppiert nach Projekt).
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => 'entry',
            'external_id' => 'openproject:te:222',
            'group_key' => 'project:9',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
        $item = IntegrationInboxItem::query()->where('external_id', 'openproject:te:222')->first();
        $this->assertSame('Website', $item->remote_snapshot['project_name'] ?? null);
    }

    public function test_create_missing_projects_auto_creates_and_books(): void {
        $config = $this->enable(['create_missing_projects' => true]);

        $this->fakeApi(
            projects: [['id' => 9, 'name' => 'Brandneu', 'active' => true, '_links' => []]],
            workPackages: [],
            timeEntries: [$this->timeEntryPayload(333, 9, 'PT1H', '2026-05-26')],
        );

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $project = Project::query()->where('name', 'Brandneu')->first();
        $this->assertNotNull($project);
        $this->assertSame(60, TimeEntry::query()->where('project_id', $project->id)->value('minutes'));
    }

    public function test_merged_project_resolves_via_alias_and_relink_writes_alias(): void {
        $sync = new OpenProjectStructureSync;
        $source = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Website']);
        $target = Project::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Relaunch']);
        $sync->linkProject($this->organization, '9', $source);
        $sync->linkProject($this->organization, '10', $target);

        // Merge: Quell-Ref '9' kollidiert mit der Ziel-Ref '10' → wird Alias.
        app(ProjectMergeService::class)->merge($source, $target);

        $this->assertSame($target->id, $sync->resolveProject($this->organization, '9')?->id);
        $this->assertSame($target->id, $sync->resolveProject($this->organization, '10')?->id);

        // Re-Link einer weiteren OP-ID aufs Ziel darf extref_unique nicht
        // verletzen, sondern landet als Alias.
        $sync->linkProject($this->organization, '11', $target);
        $this->assertDatabaseHas('external_reference_aliases', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectStructureSync::EXT_TYPE_PROJECT,
            'external_id' => '11',
            'referenceable_id' => $target->id,
        ]);
    }

    public function test_book_inbox_group_books_and_remembers_reference(): void {
        $this->enable();
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'is_default' => false,
        ]);

        $item = IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'openproject:te:900',
            'dedupe_key' => 'entry:openproject:te:900',
            'group_key' => 'project:42',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'entry_key' => 'openproject:te:900',
                'project_external_id' => '42',
                'project_name' => 'Externes Projekt',
                'work_package_external_id' => null,
                'work_package_subject' => null,
                'description' => 'Wartung',
                'spent_on' => '2026-05-26',
                'minutes' => 90,
                'user_external_id' => null,
                'user_name' => null,
            ],
            'display_title' => 'Externes Projekt',
            'occurred_at' => '2026-05-26',
        ]);

        $result = $this->service()->bookInboxGroup($this->organization, 'project:42', $project);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);

        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $item->fresh()->status);
        $this->assertSame($entry->id, $item->fresh()->resolved_to_id);

        // Projekt-Reference gemerkt → künftiger Import matcht automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectStructureSync::EXT_TYPE_PROJECT,
            'external_id' => '42',
            'referenceable_id' => $project->id,
        ]);
    }

    public function test_book_group_via_inbox_creates_new_project(): void {
        $this->enable();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'openproject:te:777',
            'dedupe_key' => 'entry:openproject:te:777',
            'group_key' => 'project:7',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'entry_key' => 'openproject:te:777', 'project_external_id' => '7',
                'project_name' => 'Neu OP', 'work_package_external_id' => null, 'work_package_subject' => null,
                'description' => null, 'spent_on' => '2026-05-26', 'minutes' => 30,
                'user_external_id' => null, 'user_name' => null,
            ],
            'display_title' => 'Neu OP',
            'occurred_at' => '2026-05-26',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => OpenProjectPlugin::ID,
                'group_key' => 'project:7',
                'project_mode' => 'new',
                'new_project_name' => 'Neu OP',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Neu OP')->first();
        $this->assertNotNull($project);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());
    }

    public function test_health_check_is_degraded_without_credentials(): void {
        $this->assertSame('degraded', (new OpenProjectPlugin)->healthCheck()->status);
    }

    public function test_health_check_is_ok_when_reachable(): void {
        $this->enable();
        FakePluginHttp::fake([self::BASE . '/users/me*' => FakePluginHttp::response(['_type' => 'User'], 200)]);

        $this->assertTrue((new OpenProjectPlugin)->healthCheck()->isOk());
    }

    public function test_health_check_is_failing_on_unauthorized(): void {
        $this->enable();
        FakePluginHttp::fake([self::BASE . '/users/me*' => FakePluginHttp::response(['_type' => 'Error'], 401)]);

        $this->assertTrue((new OpenProjectPlugin)->healthCheck()->isFailing());
    }
}
