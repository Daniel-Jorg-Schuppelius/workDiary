<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduledTimeImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\TimeTracking;

use App\Models\Customer\Customer;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Clockify\ClockifyPlugin;
use App\Plugins\Kimai\KimaiPlugin;
use App\Scheduling\JobRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Phase 137 (E21): Kimai und Clockify importieren wie Toggl stündlich, sobald
 * ein API-Zugang hinterlegt ist; der Löschabgleich läuft mit. Ohne API-Zugang
 * oder bei abgeschaltetem Plugin fasst der Lauf die Organisation nicht an.
 */
final class ScheduledTimeImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const KIMAI = 'https://kimai.test';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
        $this->travelTo(CarbonImmutable::parse('2026-06-05 12:00'));
    }

    /** @param array<string, mixed> $settings */
    private function enable(string $pluginId, array $settings, bool $enabled = true): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'enabled' => $enabled,
            'settings' => ['default_billable' => true, 'single_user_mode' => true] + $settings,
        ]);
    }

    private function project(): Project {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Acme']);

        return Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'name' => 'Website', 'is_default' => false]);
    }

    /** @return array<string, mixed> */
    private function kimaiRow(int $id, string $begin): array {
        return [
            'id' => $id,
            'begin' => $begin . 'T09:00:00+0200',
            'end' => $begin . 'T10:30:00+0200',
            'description' => 'Feature',
            'billable' => true,
            'project' => ['id' => 7, 'name' => 'Website', 'customer' => ['id' => 3, 'name' => 'Acme']],
            'activity' => ['id' => 5, 'name' => 'Development'],
            'user' => ['id' => 1, 'username' => 'daniel'],
        ];
    }

    /** @return array<string, mixed> */
    private function clockifyRow(string $id, string $day): array {
        return [
            '_id' => $id,
            'description' => 'Feature',
            'projectName' => 'Website',
            'clientName' => 'Acme',
            'billable' => true,
            'timeInterval' => ['start' => $day . 'T07:00:00Z', 'end' => $day . 'T08:30:00Z', 'duration' => 5400],
        ];
    }

    public function test_both_imports_are_registered_hourly_like_toggl(): void {
        $jobs = app(JobRegistry::class);

        foreach (['kimai.import' => 'kimai', 'clockify.import' => 'clockify'] as $key => $plugin) {
            $job = $jobs->definition($key);
            $this->assertEquals($jobs->definition('toggl.import')->defaultCadence, $job->defaultCadence, $key);
            $this->assertSame($plugin, $job->plugin, $key);
            $this->assertNotSame($key, $job->label(), 'Bezeichnung unter scheduler.job.' . $key);
        }
    }

    /** Kimai: stündlicher Lauf importiert, ein später fehlender Eintrag gilt als gelöscht. */
    public function test_kimai_import_runs_with_api_access_and_reconciles_deletions(): void {
        $this->enable(KimaiPlugin::ID, ['base_url' => self::KIMAI, 'api_token' => 'secret-token']);
        $project = $this->project();

        FakePluginHttp::fake([self::KIMAI . '/api/timesheets*' => FakePluginHttp::response([$this->kimaiRow(101, '2026-06-01'), $this->kimaiRow(102, '2026-06-02')])]);
        $this->artisan('kimai:import')->assertExitCode(0);
        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());

        FakePluginHttp::fake([self::KIMAI . '/api/timesheets*' => FakePluginHttp::response([$this->kimaiRow(102, '2026-06-02')])]);
        $this->artisan('kimai:import')->assertExitCode(0);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count(), 'In Kimai gelöscht — hier ebenfalls entfernt.');
    }

    /** Clockify: stündlicher Lauf importiert, ein später fehlender Eintrag gilt als gelöscht. */
    public function test_clockify_import_runs_with_api_key_and_reconciles_deletions(): void {
        $this->enable(ClockifyPlugin::ID, ['api_key' => 'secret-key', 'workspace_id' => 'ws1']);
        $project = $this->project();
        $report = 'https://reports.api.clockify.me/v1/workspaces/ws1/reports/detailed';

        FakePluginHttp::fake([$report => FakePluginHttp::response(['timeentries' => [$this->clockifyRow('a1', '2026-06-01'), $this->clockifyRow('a2', '2026-06-02')]])]);
        $this->artisan('clockify:import')->assertExitCode(0);
        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());

        FakePluginHttp::fake([$report => FakePluginHttp::response(['timeentries' => [$this->clockifyRow('a2', '2026-06-02')]])]);
        $this->artisan('clockify:import')->assertExitCode(0);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count(), 'In Clockify gelöscht — hier ebenfalls entfernt.');
    }

    /** Ohne API-Zugang (CSV-Modus) oder mit abgeschaltetem Plugin geht keine Anfrage hinaus. */
    public function test_without_api_access_or_switched_off_nothing_is_requested(): void {
        $this->enable(KimaiPlugin::ID, ['base_url' => self::KIMAI]);
        $this->enable(ClockifyPlugin::ID, ['api_key' => 'secret-key', 'workspace_id' => 'ws1'], enabled: false);
        $fake = FakePluginHttp::fake();

        $this->artisan('kimai:import')->assertExitCode(0);
        $this->artisan('clockify:import')->assertExitCode(0);

        $fake->assertNothingSent();
        $this->assertSame(0, TimeEntry::query()->count());
    }
}
