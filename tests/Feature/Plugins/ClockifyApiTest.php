<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Clockify\{ClockifyConfig, ClockifyImportService, ClockifyPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Clockify-Reports-API: X-Api-Key-Auth, Workspace-Auflösung über /v1/user,
 * Detailed-Report-Import (UTC→App-Zeitzone), 429-Free-Plan-Hinweis — über den
 * Guzzle-MockHandler ({@see FakePluginHttp}).
 */
class ClockifyApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function enableClockifyApi(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => ClockifyPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'default_billable' => true,
                'api_key' => 'secret-key',
            ], $extra),
        ]);

        return ClockifyConfig::resolve($this->organization->id);
    }

    private function customerWithProject(string $customerName, string $projectName): Project {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => $customerName,
        ]);

        return Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => $projectName,
            'is_default' => false,
        ]);
    }

    /** @return array<string, mixed> */
    private function reportRow(string $id = 'abc123'): array {
        return [
            '_id' => $id,
            'description' => 'Feature',
            'userEmail' => 'daniel@example.com',
            'projectName' => 'Website',
            'clientName' => 'Acme',
            'taskName' => 'Development',
            'billable' => true,
            'tags' => [['_id' => 't1', 'name' => 'frontend']],
            'timeInterval' => [
                'start' => '2026-06-01T07:00:00Z',
                'end' => '2026-06-01T08:30:00Z',
                'duration' => 5400,
            ],
        ];
    }

    public function test_api_import_resolves_workspace_and_creates_entries(): void {
        $config = $this->enableClockifyApi();
        $project = $this->customerWithProject('Acme', 'Website');

        $fake = FakePluginHttp::fake([
            'https://api.clockify.me/api/v1/user' => FakePluginHttp::response(['id' => 'u1', 'defaultWorkspace' => 'ws1']),
            'https://reports.api.clockify.me/v1/workspaces/ws1/reports/detailed' => FakePluginHttp::response(['timeentries' => [$this->reportRow()]]),
        ]);

        $result = (new ClockifyImportService)->importFromApi($this->organization, $config);

        $this->assertSame(1, $result['created']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);
        // UTC-Zeiten werden in die App-Zeitzone konvertiert; das Datum bleibt stabil.
        $this->assertSame('2026-06-01', $entry->date?->toDateString());

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => ClockifyPlugin::ID,
            'external_type' => ClockifyImportService::EXT_TYPE_ENTRY,
            'external_id' => 'api:abc123',
        ]);

        $fake->assertSent(fn (RequestInterface $r): bool => $r->getHeaderLine('X-Api-Key') === 'secret-key');
    }

    public function test_api_import_uses_configured_workspace_and_is_idempotent(): void {
        $config = $this->enableClockifyApi(['workspace_id' => 'ws42']);
        $this->customerWithProject('Acme', 'Website');

        $fake = FakePluginHttp::fake([
            'https://reports.api.clockify.me/v1/workspaces/ws42/reports/detailed' => FakePluginHttp::response(['timeentries' => [$this->reportRow()]]),
        ]);

        $first = (new ClockifyImportService)->importFromApi($this->organization, $config);
        $this->assertSame(1, $first['created']);

        $second = (new ClockifyImportService)->importFromApi($this->organization, $config);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);

        // Konfigurierter Workspace → kein /v1/user-Aufruf nötig.
        foreach ($fake->recorded() as $call) {
            $this->assertStringNotContainsString('/v1/user', (string) $call['request']->getUri());
        }
    }

    public function test_free_plan_rate_limit_returns_csv_hint(): void {
        $config = $this->enableClockifyApi(['workspace_id' => 'ws1']);

        FakePluginHttp::fake([
            'https://reports.api.clockify.me/*' => FakePluginHttp::response(['message' => 'Too many requests'], 429),
        ]);

        $result = (new ClockifyImportService)->importFromApi($this->organization, $config);

        $this->assertSame(0, $result['created']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('CSV', $result['error'] ?? '');
    }

    public function test_api_import_requires_api_key(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => ClockifyPlugin::ID,
            'enabled' => true,
            'settings' => [],
        ]);
        $config = ClockifyConfig::resolve($this->organization->id);

        $result = (new ClockifyImportService)->importFromApi($this->organization, $config);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, $result['created']);
    }
}
