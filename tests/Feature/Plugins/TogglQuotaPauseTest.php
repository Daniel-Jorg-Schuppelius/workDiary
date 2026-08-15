<?php
/*
 * Created on   : Sat Aug 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglQuotaPauseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{ExternalReference, IntegrationOutboxEntry, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Support\MatchingTimeImportService;
use App\Plugins\Toggl\Services\TogglOutboxDispatcher;
use App\Plugins\Toggl\Support\TogglQuotaGuard;
use App\Plugins\Toggl\{TogglConfig, TogglExportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Cache, Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Quota-Schutz des Toggl-Exports: Ein 402 (Stunden-Quota erschöpft) pausiert
 * die Create-Zustellung je Org, statt pro Outbox-Eintrag weitere API-Calls zu
 * verbrennen; der stündliche toggl:push holt die Einträge nach dem Reset nach.
 * Zusätzlich: Massenimporte (CSV) erzeugen KEINE Spiegel-Outbox-Einträge —
 * sonst flutet ein Import die Quota (Muster MatchingTimeImportService).
 */
class TogglQuotaPauseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CREATE_URL = 'https://api.track.toggl.com/api/v9/workspaces/4711/time_entries';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        TogglQuotaGuard::clear($this->organization->id);

        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->user->id])->save();
    }

    /** @return array<string, mixed> */
    private function config(): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => [
                'api_token' => 'test-token',
                'workspace_id' => '4711',
                'export_enabled' => true,
            ],
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    private function mappedProject(string $name = 'Testprojekt'): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'is_default' => false,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '9',
            'synced_at' => now(),
        ]);

        return $project;
    }

    private function timeEntry(Project $project, string $description): TimeEntry {
        return TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-26',
            'started_at' => CarbonImmutable::parse('2026-05-26 10:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-05-26 11:00:00'),
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'exported' => false,
        ]);
    }

    public function test_quota_402_pauses_create_delivery_and_stops_burning_calls(): void {
        Queue::fake();
        $this->config();
        $project = $this->mappedProject();
        $this->timeEntry($project, 'Erster Eintrag');
        $this->timeEntry($project, 'Zweiter Eintrag');

        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response(
                'You have hit your hourly limit for API calls.',
                402,
                ['X-Toggl-Quota-Resets-In' => '1800'],
            ),
        ]);

        [$first, $second] = IntegrationOutboxEntry::query()
            ->where('operation', TogglOutboxDispatcher::OP_ENTRY_CREATE)
            ->orderBy('id')->get()->all();

        // Erster Push läuft in die Quota: kein Fehlschlag (Backfill holt nach),
        // aber die Zustellung ist ab jetzt pausiert.
        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($first));
        $this->assertTrue(TogglQuotaGuard::isPaused($this->organization->id));

        // Zweiter Push verbrennt KEINEN weiteren API-Call.
        $this->assertTrue(app(TogglOutboxDispatcher::class)->dispatch($second));
        $fake->assertSentCount(1);

        // Nichts wurde als gepusht markiert — der stündliche Backfill findet beide.
        $this->assertSame(0, ExternalReference::query()
            ->where('external_type', MatchingTimeImportService::EXT_TYPE_ENTRY)
            ->count());
    }

    public function test_quota_pause_skips_batch_export(): void {
        $config = $this->config();
        Cache::put('toggl:quota-pause:' . $this->organization->id, true, 600);

        $result = (new TogglExportService)->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertNotSame([], $result['errors']);
    }

    public function test_csv_import_does_not_enqueue_mirror_create_outbox(): void {
        $this->config();
        $project = $this->mappedProject();
        $admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);
        $fake = FakePluginHttp::fake([
            self::CREATE_URL => FakePluginHttp::response(['id' => 9001], 200),
        ]);

        $csv = "user_email;date;start_time;end_time;project\nworker@example.com;2026-01-05;09:00;10:00;Testprojekt\n";
        $file = UploadedFile::fake()->createWithContent('zeiten.csv', $csv);
        $this->actingAs($admin)
            ->post(route('admin.imports.preflight'), [
                'entity' => 'project_times',
                'match_policy' => 'auto_create',
                'file' => $file,
            ])->assertRedirect();
        $run = \App\Models\ImportRun::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.imports.confirm', $run))->assertRedirect();

        // Importierte Zeile ist gebucht — aber KEIN Spiegel-Export Richtung Toggl.
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertSame(0, IntegrationOutboxEntry::query()->count());
        $fake->assertNothingSent();

        // Gegenprobe: eine direkt angelegte Zeit spiegelt weiterhin in die Outbox.
        $this->timeEntry($project, 'Manuell erfasst');
        $this->assertSame(1, IntegrationOutboxEntry::query()->count());
    }
}
