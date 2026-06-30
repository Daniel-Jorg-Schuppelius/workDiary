<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, PluginSetting, Project, TimeEntry, User};
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\OpenProject\Services\{OpenProjectExportService, OpenProjectStructureSync};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class OpenProjectExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://op.example.test/api/v3';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->user->id])->save();
    }

    private function service(): OpenProjectExportService {
        return new OpenProjectExportService(new OpenProjectStructureSync);
    }

    private function config(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'base_url' => 'https://op.example.test',
                'api_token' => 'test-token',
                'default_activity_id' => '1',
            ], $extra),
        ]);

        return OpenProjectConfig::resolve($this->organization->id);
    }

    private function mappedProject(string $externalId = '9'): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Website',
            'is_default' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectStructureSync::EXT_TYPE_PROJECT,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => $externalId,
            'synced_at' => now(),
        ]);

        return $project;
    }

    private function timeEntry(Project $project): TimeEntry {
        return TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-26',
            'minutes' => 90,
            'kind' => TimeEntryKind::Work,
            'description' => 'Rückzubuchende Arbeit',
            'billable' => true,
            'exported' => false,
        ]);
    }

    public function test_push_creates_remote_entry_and_marks_exported(): void {
        $config = $this->config();
        $project = $this->mappedProject('9');
        $entry = $this->timeEntry($project);

        Http::fake([self::BASE . '/time_entries' => Http::response(['id' => 9001], 201)]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame(0, $result['failed']);
        $this->assertTrue($entry->fresh()->exported);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectExportService::EXT_TYPE_PUSHED,
            'external_id' => '9001',
            'referenceable_id' => $entry->id,
        ]);

        // Korrekte ISO-8601-Dauer im Request-Body.
        Http::assertSent(function ($request): bool {
            return str_ends_with($request->url(), '/time_entries')
                && $request['hours'] === 'PT1H30M'
                && $request['spentOn'] === '2026-05-26'
                && data_get($request->data(), '_links.project.href') === '/api/v3/projects/9'
                && data_get($request->data(), '_links.activity.href') === '/api/v3/time_entries/activities/1';
        });
    }

    public function test_push_is_idempotent(): void {
        $config = $this->config();
        $project = $this->mappedProject('9');
        $this->timeEntry($project);

        Http::fake([self::BASE . '/time_entries' => Http::response(['id' => 9001], 201)]);

        $first = $this->service()->exportPending($this->organization, $config);
        $second = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(1, $first['pushed']);
        $this->assertSame(0, $second['pushed']);
        $this->assertSame(1, ExternalReference::query()
            ->where('external_type', OpenProjectExportService::EXT_TYPE_PUSHED)
            ->count());
    }

    public function test_push_without_activity_id_reports_error(): void {
        $config = $this->config(['default_activity_id' => '']);
        $project = $this->mappedProject('9');
        $this->timeEntry($project);

        Http::fake([self::BASE . '/time_entries' => Http::response(['id' => 9001], 201)]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertNotEmpty($result['errors']);
        Http::assertNothingSent();
    }

    public function test_unmapped_project_is_skipped(): void {
        $config = $this->config();
        // Projekt ohne OpenProject-Mapping.
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Ohne Mapping',
            'is_default' => false,
        ]);
        $this->timeEntry($project);

        Http::fake([self::BASE . '/time_entries' => Http::response(['id' => 9001], 201)]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        Http::assertNothingSent();
    }
}
