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
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
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

    public function test_outbox_create_pushes_single_entry_when_opted_in(): void {
        // Sofort-Rückbuchung (Audit 2026-08, Welle 1.2): eigenes Opt-in
        // push_on_create, weil der Eintrag unmittelbar exported wird.
        \Illuminate\Support\Facades\Queue::fake();
        $this->config(['push_on_create' => true]);
        $project = $this->mappedProject('9');
        $entry = $this->timeEntry($project);

        $outbox = \App\Models\IntegrationOutboxEntry::query()
            ->where('operation', \App\Plugins\OpenProject\Services\OpenProjectOutboxDispatcher::OP_ENTRY_CREATE)
            ->where('idempotency_key', OpenProjectPlugin::ID . '-entry-create:' . $entry->getKey())
            ->firstOrFail();

        $fake = FakePluginHttp::fake([self::BASE . '/time_entries' => FakePluginHttp::response(['id' => 9001], 201)]);

        $this->assertTrue(app(\App\Plugins\OpenProject\Services\OpenProjectOutboxDispatcher::class)->dispatch($outbox));

        $fake->assertSentCount(1);
        // Rückbuchung: der Eintrag ist sofort exportiert.
        $this->assertTrue((bool) $entry->fresh()->exported);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => OpenProjectPlugin::ID,
            'external_type' => OpenProjectExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->getKey(),
            'external_id' => '9001',
        ]);
    }

    public function test_outbox_create_is_not_enqueued_without_opt_in(): void {
        \Illuminate\Support\Facades\Queue::fake();
        $this->config();
        $this->timeEntry($this->mappedProject('9'));

        $this->assertDatabaseMissing('integration_outbox', [
            'operation' => \App\Plugins\OpenProject\Services\OpenProjectOutboxDispatcher::OP_ENTRY_CREATE,
        ]);
    }

    public function test_push_creates_remote_entry_and_marks_exported(): void {
        $config = $this->config();
        $project = $this->mappedProject('9');
        $entry = $this->timeEntry($project);

        $fake = FakePluginHttp::fake([self::BASE . '/time_entries' => FakePluginHttp::response(['id' => 9001], 201)]);

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
        $fake->assertSent(function ($request): bool {
            $data = json_decode((string) $request->getBody(), true);

            return str_ends_with((string) $request->getUri(), '/time_entries')
                && data_get($data, 'hours') === 'PT1H30M'
                && data_get($data, 'spentOn') === '2026-05-26'
                && data_get($data, '_links.project.href') === '/api/v3/projects/9'
                && data_get($data, '_links.activity.href') === '/api/v3/time_entries/activities/1';
        });
    }

    public function test_push_is_idempotent(): void {
        $config = $this->config();
        $project = $this->mappedProject('9');
        $this->timeEntry($project);

        FakePluginHttp::fake([self::BASE . '/time_entries' => FakePluginHttp::response(['id' => 9001], 201)]);

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

        $fake = FakePluginHttp::fake([self::BASE . '/time_entries' => FakePluginHttp::response(['id' => 9001], 201)]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertNotEmpty($result['errors']);
        $fake->assertNothingSent();
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

        $fake = FakePluginHttp::fake([self::BASE . '/time_entries' => FakePluginHttp::response(['id' => 9001], 201)]);

        $result = $this->service()->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $fake->assertNothingSent();
    }
}
