<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Kimai\{KimaiConfig, KimaiExportService, KimaiImportService, KimaiPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Kimai-REST-API: Import (Bearer, full=true, Pagination) und Export-Rückkanal
 * (Timesheets anlegen, Idempotenz, Echo-Schutz) über den Guzzle-MockHandler
 * ({@see FakePluginHttp}).
 */
class KimaiApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://kimai.test';

    private User $owner;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function enableKimaiApi(array $extra = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'default_billable' => true,
                'base_url' => self::BASE,
                'api_token' => 'secret-token',
                // Einbenutzer-Modus (MVP-509): Fixtures ohne auflösbare
                // Quell-E-Mail — Mehrbenutzer-Semantik testet TogglUserResolutionTest.
                'single_user_mode' => true,
            ], $extra),
        ]);

        return KimaiConfig::resolve($this->organization->id);
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
    private function apiRow(int $id, string $client = 'Acme', string $project = 'Website', ?string $end = '2026-06-01T10:30:00+0200'): array {
        return [
            'id' => $id,
            'begin' => '2026-06-01T09:00:00+0200',
            'end' => $end,
            'description' => 'Feature',
            'billable' => true,
            'tags' => ['frontend'],
            'project' => ['id' => 7, 'name' => $project, 'customer' => ['id' => 3, 'name' => $client]],
            'activity' => ['id' => 5, 'name' => 'Development'],
            'user' => ['id' => 1, 'username' => 'daniel'],
        ];
    }

    public function test_api_import_creates_entries_and_remembers_kimai_ids(): void {
        $config = $this->enableKimaiApi();
        $project = $this->customerWithProject('Acme', 'Website');

        $fake = FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response([$this->apiRow(101)]),
        ]);

        $result = (new KimaiImportService)->importFromApi($this->organization, $config);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['unmatched']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);

        // Idempotenz-Schlüssel = stabile Kimai-ID.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_ENTRY,
            'external_id' => 'api:101',
        ]);

        // Numerische Kimai-IDs als Export-Mapping gemerkt.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_PROJECT_ID,
            'external_id' => '7',
            'referenceable_id' => $project->id,
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '3',
        ]);

        // Bearer-Token gesetzt.
        $fake->assertSent(fn (RequestInterface $r): bool => $r->getHeaderLine('Authorization') === 'Bearer secret-token');
    }

    public function test_api_import_is_idempotent_and_skips_running_timesheets(): void {
        $config = $this->enableKimaiApi();
        $this->customerWithProject('Acme', 'Website');

        FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response([
                $this->apiRow(101),
                $this->apiRow(102, end: null), // laufender Eintrag → übersprungen
            ]),
        ]);

        $first = (new KimaiImportService)->importFromApi($this->organization, $config);
        $this->assertSame(1, $first['created']);

        $second = (new KimaiImportService)->importFromApi($this->organization, $config);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(1, TimeEntry::query()->count());
    }

    public function test_api_import_unmatched_lands_in_inbox_with_kimai_ids(): void {
        $config = $this->enableKimaiApi();

        FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response([$this->apiRow(201, 'Unbekannt', 'Fremdprojekt')]),
        ]);

        $result = (new KimaiImportService)->importFromApi($this->organization, $config);

        $this->assertSame(1, $result['unmatched']);

        $item = IntegrationInboxItem::query()
            ->where('plugin_id', KimaiPlugin::ID)
            ->where('external_id', 'api:201')
            ->first();
        $this->assertNotNull($item);
        $this->assertSame(7, $item->remote_snapshot['project_id']);
        $this->assertSame(3, $item->remote_snapshot['client_id']);
        $this->assertSame('api', $item->remote_snapshot['source']);
    }

    public function test_api_import_requires_configuration(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'enabled' => true,
            'settings' => ['default_billable' => true],
        ]);
        $config = KimaiConfig::resolve($this->organization->id);

        $result = (new KimaiImportService)->importFromApi($this->organization, $config);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, $result['created']);
    }

    public function test_export_pushes_unexported_entries_and_is_idempotent(): void {
        $config = $this->enableKimaiApi(['export_enabled' => true, 'default_activity_id' => '5']);
        $project = $this->customerWithProject('Acme', 'Website');

        // Export-Mapping wie nach einem API-Import.
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '7',
            'synced_at' => now(),
        ]);

        $entry = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => '2026-06-02',
            'started_at' => '2026-06-02 09:00:00',
            'ended_at' => '2026-06-02 10:00:00',
            'description' => 'Vor Ort',
            'billable' => true,
        ]);

        $fake = FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response(['id' => 999]),
        ]);

        $result = (new KimaiExportService)->exportPending($this->organization, $config);

        $this->assertSame(1, $result['pushed']);
        $this->assertSame([], $result['errors']);

        $fake->assertSent(function (RequestInterface $r): bool {
            if ($r->getMethod() !== 'POST') {
                return false;
            }
            $body = (array) json_decode((string) $r->getBody(), true);

            return ($body['project'] ?? null) === 7
                && ($body['activity'] ?? null) === 5
                && ($body['begin'] ?? null) === '2026-06-02T09:00:00'
                && ($body['end'] ?? null) === '2026-06-02T10:00:00'
                && ($body['billable'] ?? null) === true;
        });

        $this->assertTrue((bool) $entry->refresh()->exported);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->id,
            'external_id' => '999',
        ]);

        // Zweiter Lauf: nichts mehr offen.
        $second = (new KimaiExportService)->exportPending($this->organization, $config);
        $this->assertSame(0, $second['pushed']);
    }

    public function test_outbox_create_pushes_single_entry_when_opted_in(): void {
        // Sofort-Rückbuchung (Audit 2026-08, Welle 1.2): eigenes Opt-in
        // push_on_create, weil der Eintrag unmittelbar exported wird.
        \Illuminate\Support\Facades\Queue::fake();
        $this->enableKimaiApi(['export_enabled' => true, 'push_on_create' => true, 'default_activity_id' => '5']);
        $project = $this->customerWithProject('Acme', 'Website');
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '7',
            'synced_at' => now(),
        ]);

        $entry = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => '2026-06-02',
            'started_at' => '2026-06-02 09:00:00',
            'ended_at' => '2026-06-02 10:00:00',
            'description' => 'Vor Ort',
            'billable' => true,
        ]);

        $outbox = \App\Models\IntegrationOutboxEntry::query()
            ->where('operation', \App\Plugins\Kimai\Services\KimaiOutboxDispatcher::OP_ENTRY_CREATE)
            ->where('idempotency_key', KimaiPlugin::ID . '-entry-create:' . $entry->getKey())
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response(['id' => 999]),
        ]);

        $this->assertTrue(app(\App\Plugins\Kimai\Services\KimaiOutboxDispatcher::class)->dispatch($outbox));

        $fake->assertSentCount(1);
        // Rückbuchung: der Eintrag ist sofort exportiert.
        $this->assertTrue((bool) $entry->fresh()->exported);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiExportService::EXT_TYPE_PUSHED,
            'referenceable_id' => $entry->getKey(),
            'external_id' => '999',
        ]);
    }

    public function test_outbox_create_is_not_enqueued_without_opt_in(): void {
        \Illuminate\Support\Facades\Queue::fake();
        $this->enableKimaiApi(['export_enabled' => true, 'default_activity_id' => '5']);
        $project = $this->customerWithProject('Acme', 'Website');

        TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => '2026-06-02',
            'started_at' => '2026-06-02 09:00:00',
            'ended_at' => '2026-06-02 10:00:00',
        ]);

        $this->assertDatabaseMissing('integration_outbox', [
            'operation' => \App\Plugins\Kimai\Services\KimaiOutboxDispatcher::OP_ENTRY_CREATE,
        ]);
    }

    public function test_export_never_echoes_imported_entries(): void {
        $config = $this->enableKimaiApi(['export_enabled' => true, 'default_activity_id' => '5']);
        $this->customerWithProject('Acme', 'Website');

        FakePluginHttp::fake([
            self::BASE . '/api/timesheets*' => FakePluginHttp::response([$this->apiRow(101)]),
        ]);

        (new KimaiImportService)->importFromApi($this->organization, $config);
        $this->assertSame(1, TimeEntry::query()->count());

        // Der importierte Eintrag trägt eine entry-Reference → nie zurückbuchen.
        $result = (new KimaiExportService)->exportPending($this->organization, $config);

        $this->assertSame(0, $result['pushed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseMissing('external_references', [
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiExportService::EXT_TYPE_PUSHED,
        ]);
    }

    public function test_export_requires_activity_id_and_flag(): void {
        $project = $this->customerWithProject('Acme', 'Website');
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'external_type' => KimaiImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '7',
            'synced_at' => now(),
        ]);

        FakePluginHttp::fake();

        // Export deaktiviert.
        $config = $this->enableKimaiApi(['default_activity_id' => '5']);
        $result = (new KimaiExportService)->exportPending($this->organization, $config);
        $this->assertSame(0, $result['pushed']);
        $this->assertNotSame([], $result['errors']);

        // Aktiviert, aber ohne Activity-ID.
        PluginSetting::query()->where('plugin_id', KimaiPlugin::ID)->delete();
        $config = $this->enableKimaiApi(['export_enabled' => true]);
        $result = (new KimaiExportService)->exportPending($this->organization, $config);
        $this->assertSame(0, $result['pushed']);
        $this->assertNotSame([], $result['errors']);
    }
}
