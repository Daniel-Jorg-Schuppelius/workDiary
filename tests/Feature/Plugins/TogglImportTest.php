<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, ForeignCustomer, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Support\ImportedTimeEntry;
use App\Plugins\Toggl\Sources\TogglEntry;
use App\Plugins\Toggl\{TogglConfig, TogglImportService, TogglPlugin};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class TogglImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function service(): TogglImportService {
        return new TogglImportService;
    }

    private function enableToggl(array $settings = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge([
                'api_token' => 'test-token',
                // Diese Bestandstests prüfen Projekt-/Billable-/Tag-/Referenz-
                // Semantik mit Fixtures ohne auflösbare Benutzer-E-Mail — im
                // ausdrücklichen Einbenutzer-Modus (MVP-509) bleibt dafür der
                // Standard-Benutzer-Fallback aktiv. Die Mehrbenutzer-Semantik
                // deckt TogglUserResolutionTest ab.
                'single_user_mode' => true,
            ], $settings),
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    /** Kunde "Acme" mit Projekt "Website" — matchbar über Namensgleichheit. */
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

    /** group_key wie vom Import erzeugt (lower(client|project)). */
    private function groupKey(string $client, string $project): string {
        return mb_strtolower(trim($client) . '|' . trim($project));
    }

    /** @param list<string> $tags */
    private function seedInboxEntry(string $client, string $project, string $entryKey, string $start, string $end, ?bool $billable = true, ?string $description = null, ?int $workspaceId = null, ?string $workspaceName = null, array $tags = []): IntegrationInboxItem {
        return IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'csv',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => $entryKey,
            'dedupe_key' => 'entry:' . $entryKey,
            'group_key' => ($workspaceId !== null ? 'ws' . $workspaceId . '|' : '') . $this->groupKey($client, $project),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => 'csv',
                'entry_key' => $entryKey,
                'client_name' => $client,
                'project_name' => $project,
                'description' => $description,
                'started_at' => CarbonImmutable::parse($start)->toIso8601String(),
                'ended_at' => CarbonImmutable::parse($end)->toIso8601String(),
                'billable' => $billable,
                'user_email' => null,
                'tags' => $tags,
                'workspace_id' => $workspaceId,
                'workspace_name' => $workspaceName,
            ],
            'display_title' => $project,
            'display_subtitle' => $client,
            'occurred_at' => CarbonImmutable::parse($start),
        ]);
    }

    private function fakeApi(array $timeEntries, array $clients, array $projects): void {
        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response($timeEntries, 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response([
                'email' => 'tech@example.com',
                'clients' => $clients,
                'projects' => $projects,
            ], 200),
        ]);
    }

    public function test_api_import_books_entry_in_matched_project(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => true,
                'description' => 'Bugfix',
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
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
        $this->assertTrue($entry->billable);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'external_id' => 'toggl:111',
            'referenceable_id' => $entry->id,
        ]);
    }

    public function test_api_sync_imports_employee_entries_via_reports_api(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        // /me liefert nur eigene Zeiten (hier: keine) — die Mitarbeiter-Zeit
        // kommt ausschließlich über die Reports-API des Workspaces. Deren
        // Zeilen tragen nur die user_id; die E-Mail kommt aus der
        // Workspace-Benutzerliste.
        FakePluginHttp::fake($this->reportApiStubs(withUsers: true));

        $result = $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        // Mitarbeiter-E-Mail aus dem Report bestimmt den Buchungs-Benutzer.
        $this->assertSame($mitarbeiter->id, $entry->user_id);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'external_id' => 'toggl:555',
            'referenceable_id' => $entry->id,
        ]);
        // Report-tag_ids werden über die Workspace-Tag-Liste aufgelöst;
        // unbekannte IDs (gelöschte Tags) fallen still raus.
        $this->assertSame(['Fernwartung'], $entry->tags()->pluck('name')->all());
    }

    /**
     * Endpunkt-Stubs für den Reports-API-Sync (Workspace 7, Projekt 9, ein
     * Mitarbeiter-Eintrag von Toggl-User 42). Ohne Benutzerliste bleibt die
     * E-Mail unaufgelöst → Buchung auf den Standard-Benutzer.
     *
     * @return array<string, mixed>
     */
    private function reportApiStubs(bool $withUsers): array {
        return [
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response([], 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response(['email' => 'chef@example.com', 'clients' => [], 'projects' => []], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/users*' => FakePluginHttp::response(
                $withUsers ? [['id' => 42, 'email' => 'worker@example.com', 'fullname' => 'Worker']] : [],
                200,
            ),
            'https://api.track.toggl.com/api/v9/workspaces/7/clients*' => FakePluginHttp::response([['id' => 5, 'name' => 'Acme']], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/projects*' => FakePluginHttp::response([['id' => 9, 'name' => 'Website', 'client_id' => 5, 'active' => true]], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/tags*' => FakePluginHttp::response([['id' => 77, 'name' => 'Fernwartung']], 200),
            'https://api.track.toggl.com/api/v9/workspaces' => FakePluginHttp::response([['id' => 7, 'name' => 'Firma WS']], 200),
            'https://api.track.toggl.com/reports/api/v3/workspace/7/search/time_entries*' => FakePluginHttp::response([[
                'project_id' => 9,
                'description' => 'Mitarbeiter-Arbeit',
                'billable' => true,
                'user_id' => 42,
                // 77 bekannt (→ „Fernwartung"), 999 gelöscht → still übersprungen.
                'tag_ids' => [77, 999],
                'time_entries' => [['id' => 555, 'start' => '2026-05-26T10:00:00+00:00', 'stop' => '2026-05-26T11:00:00+00:00']],
            ]], 200),
        ];
    }

    public function test_repair_command_api_mode_fixes_users_via_reports(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        // Alt-Zustand: Import ohne auflösbare E-Mail → Eintrag beim Owner.
        FakePluginHttp::fake($this->reportApiStubs(withUsers: false));
        $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );
        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame((int) $this->organization->owner_id, (int) $entry->user_id);

        // Reparatur im API-Modus (ohne CSV): Reports + Benutzerliste liefern die E-Mail.
        FakePluginHttp::fake($this->reportApiStubs(withUsers: true));
        $this->artisan('toggl:repair-entry-users', ['--organization' => $this->organization->id, '--days' => 60, '--apply' => true])
            ->assertExitCode(0);

        $this->assertSame($mitarbeiter->id, (int) $entry->fresh()->user_id);
    }

    public function test_api_import_is_idempotent(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:30:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
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

    /**
     * Löschungen dürfen nur aus einem vollständigen Abruf abgeleitet werden:
     * bricht ein Teil des Laufs still ab (hier: die Workspace-Liste), gälten
     * alle ungelieferten Einträge fälschlich als drüben gelöscht — so gingen
     * am 01./02.08.2026 real 50+44 Einträge verloren (Flip-Flop).
     */
    public function test_api_import_entfernt_geloeschte_nur_nach_vollstaendigem_abruf(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $clients = [['id' => 5, 'name' => 'Acme']];
        $projects = [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]];
        $entry111 = ['id' => 111, 'workspace_id' => 1, 'project_id' => 9, 'start' => '2026-05-26T10:00:00+00:00', 'stop' => '2026-05-26T10:45:00+00:00', 'billable' => true, 'description' => 'Bugfix'];
        $entry222 = ['id' => 222, 'workspace_id' => 1, 'project_id' => 9, 'start' => '2026-05-26T12:00:00+00:00', 'stop' => '2026-05-26T12:30:00+00:00', 'billable' => true, 'description' => 'Review'];
        $from = CarbonImmutable::parse('2026-05-25');
        $to = CarbonImmutable::parse('2026-05-27');

        // Erstimport: beide Einträge kommen an.
        $this->fakeApi(timeEntries: [$entry111, $entry222], clients: $clients, projects: $projects);
        $this->service()->importFromApi($this->organization, $config, $from, $to);
        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());

        // Teilabruf: die Workspace-Liste bricht ab (500) — Eintrag 222 fehlt
        // in der Lieferung, darf aber NICHT als gelöscht gelten.
        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response([$entry111], 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response(['email' => 'tech@example.com', 'clients' => $clients, 'projects' => $projects], 200),
            'https://api.track.toggl.com/api/v9/workspaces' => FakePluginHttp::response(['error' => 'boom'], 500),
        ]);
        $partial = $this->service()->importFromApi($this->organization, $config, $from, $to);
        $this->assertSame(0, $partial['removed']);
        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());

        // Vollständiger Lauf ohne 222 → jetzt ist es eine echte Löschung.
        $this->fakeApi(timeEntries: [$entry111], clients: $clients, projects: $projects);
        $complete = $this->service()->importFromApi($this->organization, $config, $from, $to);
        $this->assertSame(1, $complete['removed']);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());
    }

    /**
     * Toggl Free meldet billable=false für JEDEN Eintrag (Premium-Feature) —
     * das ist kein Signal: der Eintrag erbt die effektive Projekt-Abrechenbarkeit.
     */
    public function test_api_billable_false_inherits_effective_project_billable(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $project->forceFill(['billable' => null])->save(); // erbt vom Kunden (true)

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertTrue($entry->billable);
    }

    public function test_api_billable_false_respects_non_billable_project(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $project->forceFill(['billable' => false])->save();

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertFalse($entry->billable);
    }

    public function test_api_billable_false_with_default_billable_off_stays_false(): void {
        $config = $this->enableToggl(['default_billable' => false]);
        $project = $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertFalse($entry->billable);
    }

    public function test_api_billable_true_wins_over_non_billable_project(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $project->forceFill(['billable' => false])->save();

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => true,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertTrue($entry->billable);
    }

    /**
     * Guard gegen die stündliche Massen-„updated"-Welle: vor der ?bool-Umstellung
     * gespeicherte Fingerabdrücke (billable=false → '0') müssen mit dem neuen
     * „kein Signal"-Import (null → '0') weiterhin byte-identisch matchen.
     */
    public function test_legacy_fingerprint_matches_import_without_billable_signal(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        $start = CarbonImmutable::parse('2026-05-26T10:00:00+00:00');
        $stop = CarbonImmutable::parse('2026-05-26T10:30:00+00:00');

        $existing = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $stop,
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work,
            'billable' => false,
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $existing->getMorphClass(),
            'referenceable_id' => $existing->getKey(),
            'external_id' => 'toggl:111',
            'payload' => [
                // Alt-Zustand: Abdruck wurde mit hartem false gebildet.
                'fingerprint' => \App\Plugins\Support\RemoteTimeFingerprint::fromParts($start, $stop, null, 9, false),
            ],
            'synced_at' => now(),
        ]);

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:30:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $result = $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertFalse($existing->fresh()->billable);
    }

    public function test_book_inbox_group_round_trips_missing_billable_signal(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');

        // null = kein Quell-Signal (Toggl Free) → gebuchter Eintrag erbt (Kunde true).
        $this->seedInboxEntry('Beta GmbH', 'Intranet', 'csv:null-sig', '2026-05-26 09:00:00', '2026-05-26 10:00:00', billable: null);
        // Legacy-Snapshot mit hartem false (vor der Umstellung geschrieben) bucht als false.
        $this->seedInboxEntry('Beta GmbH', 'Intranet', 'csv:legacy-false', '2026-05-26 11:00:00', '2026-05-26 12:00:00', billable: false);

        $result = $this->service()->bookInboxGroup($this->organization, $this->groupKey('Beta GmbH', 'Intranet'), $project->customer, $project);

        $this->assertSame(2, $result['created']);
        $this->assertTrue(TimeEntry::query()->where('description', 'like', '%Intranet%')->where('started_at', '2026-05-26 09:00:00')->firstOrFail()->billable);
        $this->assertFalse(TimeEntry::query()->where('started_at', '2026-05-26 11:00:00')->firstOrFail()->billable);
    }

    public function test_api_import_attaches_tags_with_correct_organization(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:45:00+00:00',
                'billable' => false,
                'tags' => ['Support', 'Wartung'],
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        // Bewusst OHNE Org-Kontext/Auth (wie der Scheduler-Lauf): die Tags
        // müssen trotzdem in der richtigen Organisation landen.
        $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(['Support', 'Wartung'], $entry->tags()->pluck('name')->sort()->values()->all());
        $this->assertSame(
            [$this->organization->id, $this->organization->id],
            \App\Models\Tag::query()->withoutGlobalScopes()->pluck('organization_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_csv_import_attaches_tags_and_stays_idempotent(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');

        $csv = <<<'CSV'
        User,Email,Client,Project,Task,Description,Billable,Start date,Start time,End date,End time,Duration,Tags
        Tech,tech@example.com,Beta GmbH,Intranet,,Wartung,Yes,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,"Wartung, Vor-Ort"
        CSV;

        $first = $this->service()->importFromCsv($this->organization, $csv, $config);
        $second = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, TimeEntry::query()->count());

        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(['Vor-Ort', 'Wartung'], $entry->tags()->pluck('name')->sort()->values()->all());
        $this->assertSame(2, \App\Models\Tag::query()->withoutGlobalScopes()->count());
    }

    public function test_book_inbox_group_applies_snapshot_tags(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');

        $this->seedInboxEntry('Beta GmbH', 'Intranet', 'csv:tagged', '2026-05-26 09:00:00', '2026-05-26 10:00:00', tags: ['Eskalation']);

        $result = $this->service()->bookInboxGroup($this->organization, $this->groupKey('Beta GmbH', 'Intranet'), $project->customer, $project);

        $this->assertSame(1, $result['created']);
        $entry = TimeEntry::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame(['Eskalation'], $entry->tags()->pluck('name')->all());
    }

    public function test_sync_known_entry_update_applies_tags_additively(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        $start = CarbonImmutable::parse('2026-05-26T10:00:00+00:00');
        $stop = CarbonImmutable::parse('2026-05-26T10:30:00+00:00');

        $existing = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->admin->id,
            'date' => $start->toDateString(),
            'started_at' => $start,
            'ended_at' => $stop,
            'kind' => \App\Enums\TimeEntry\TimeEntryKind::Work,
            'description' => 'Alte Beschreibung',
        ]);
        $manual = \App\Models\Tag::create(['name' => 'Manuell', 'organization_id' => $this->organization->id]);
        $existing->tags()->sync([$manual->id]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $existing->getMorphClass(),
            'referenceable_id' => $existing->getKey(),
            'external_id' => 'toggl:111',
            'payload' => [
                'fingerprint' => \App\Plugins\Support\RemoteTimeFingerprint::fromParts($start, $stop, 'Alte Beschreibung', 9, false),
            ],
            'synced_at' => now(),
        ]);

        // Drüben wurde die Beschreibung geändert UND ein Tag ergänzt.
        $this->fakeApi(
            timeEntries: [[
                'id' => 111,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:30:00+00:00',
                'billable' => false,
                'description' => 'Neue Beschreibung',
                'tags' => ['Remote'],
            ]],
            clients: [['id' => 5, 'name' => 'Acme']],
            projects: [['id' => 9, 'name' => 'Website', 'client_id' => 5, 'workspace_id' => 1]],
        );

        $result = $this->service()->importFromApi($this->organization, $config, CarbonImmutable::parse('2026-05-25'), CarbonImmutable::parse('2026-05-27'));

        $this->assertSame(1, $result['updated']);
        $fresh = $existing->fresh();
        $this->assertSame('Neue Beschreibung', $fresh->description);
        // Additiv: der Remote-Tag kommt dazu, der manuelle bleibt.
        $this->assertSame(['Manuell', 'Remote'], $fresh->tags()->pluck('name')->sort()->values()->all());
    }

    public function test_unmatched_entry_is_recorded_in_inbox(): void {
        $config = $this->enableToggl();

        $this->fakeApi(
            timeEntries: [[
                'id' => 222,
                'workspace_id' => 1,
                'project_id' => 9,
                'start' => '2026-05-26T10:00:00+00:00',
                'stop' => '2026-05-26T10:15:00+00:00',
                'billable' => false,
            ]],
            clients: [['id' => 5, 'name' => 'Unknown Co']],
            projects: [['id' => 9, 'name' => 'Mystery', 'client_id' => 5, 'workspace_id' => 1]],
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

        // MVP-103 Phase 2b: unmatched landet in der universellen Inbox (gruppiert).
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => 'entry',
            'external_id' => 'toggl:222',
            'group_key' => 'ws1|' . $this->groupKey('Unknown Co', 'Mystery'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_csv_import_records_pending_entry(): void {
        $config = $this->enableToggl();

        $csv = <<<'CSV'
        User,Email,Client,Project,Task,Description,Billable,Start date,Start time,End date,End time,Duration,Tags
        Tech,tech@example.com,Beta GmbH,Intranet,,Wartung,Yes,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,
        CSV;

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'csv',
            'group_key' => $this->groupKey('Beta GmbH', 'Intranet'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_book_inbox_group_materializes_entries_and_remembers_reference(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Beta GmbH', 'Intranet');
        $customer = $project->customer;

        $item = $this->seedInboxEntry('Beta GmbH', 'Intranet', 'csv:abc', '2026-05-26 09:00:00', '2026-05-26 10:00:00');

        $result = $this->service()->bookInboxGroup($this->organization, $this->groupKey('Beta GmbH', 'Intranet'), $customer, $project);

        $this->assertSame(1, $result['created']);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(60, $entry->minutes);

        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $item->fresh()->status);
        $this->assertSame($entry->id, $item->fresh()->resolved_to_id);

        // Reference gemerkt → künftiger Match.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'external_id' => 'Beta GmbH',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertNotNull($this->service()->matchProject(
            $this->organization,
            new \App\Plugins\Toggl\Sources\TogglEntry(
                source: 'csv',
                entryKey: 'csv:x',
                clientName: 'Beta GmbH',
                projectName: 'Intranet',
                description: null,
                startedAt: CarbonImmutable::now(),
                endedAt: CarbonImmutable::now(),
            ),
        ));
    }

    public function test_match_project_prefers_stable_toggl_id_over_name(): void {
        $project = $this->customerWithProject('Acme', 'Altname');

        // Stabile Projekt-ID-Referenz (wie sie API-Import/Backfill schreiben).
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'referenceable_type' => $project->getMorphClass(),
            'referenceable_id' => $project->id,
            'external_id' => '777',
        ]);

        // In Toggl umbenannt: der Name passt nicht mehr, die ID schon → trifft trotzdem.
        $matched = $this->service()->matchProject(
            $this->organization,
            new TogglEntry(
                source: 'api', entryKey: 'toggl:1', clientName: 'Acme', projectName: 'Komplett anderer Name',
                description: null, startedAt: CarbonImmutable::now(), endedAt: CarbonImmutable::now(),
                projectId: 777,
            ),
        );

        $this->assertNotNull($matched);
        $this->assertSame($project->id, $matched->id);
    }

    public function test_book_inbox_group_remembers_stable_id_references(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Gamma', 'App');
        $customer = $project->customer;

        // Inbox-Eintrag mit Toggl-IDs im Snapshot (API-Herkunft).
        IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'api',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'toggl:42',
            'dedupe_key' => 'entry:toggl:42',
            'group_key' => $this->groupKey('Gamma', 'App'),
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => 'api', 'entry_key' => 'toggl:42',
                'client_name' => 'Gamma', 'project_name' => 'App',
                'client_id' => 5, 'project_id' => 50,
                'description' => null,
                'started_at' => CarbonImmutable::parse('2026-05-26 09:00:00')->toIso8601String(),
                'ended_at' => CarbonImmutable::parse('2026-05-26 10:00:00')->toIso8601String(),
                'billable' => false, 'user_email' => null,
            ],
            'display_title' => 'App',
            'display_subtitle' => 'Gamma',
            'occurred_at' => CarbonImmutable::parse('2026-05-26 09:00:00'),
        ]);

        $this->service()->bookInboxGroup($this->organization, $this->groupKey('Gamma', 'App'), $customer, $project);

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'external_id' => '50',
            'referenceable_id' => $project->id,
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '5',
            'referenceable_id' => $customer->id,
        ]);
    }

    public function test_backfill_writes_id_references_for_existing_name_links(): void {
        // Toggl aktiviert mit fixem Workspace (kein /workspaces-Aufruf nötig).
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'test-token', 'workspace_id' => 100],
        ]);

        // Bestehende, namensbasiert verknüpfte Datensätze.
        $project = $this->customerWithProject('Acme', 'Website');
        $customer = $project->customer;
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id,
            'external_id' => 'Acme',
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id, 'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT,
            'referenceable_type' => $project->getMorphClass(), 'referenceable_id' => $project->id,
            'external_id' => 'acme|website',
        ]);

        FakePluginHttp::fake([
            'https://api.track.toggl.com/api/v9/workspaces/100/clients*' => FakePluginHttp::response([
                ['id' => 1, 'name' => 'Acme', 'archived' => false],
            ]),
            'https://api.track.toggl.com/api/v9/workspaces/100/projects*' => FakePluginHttp::response([
                ['id' => 10, 'name' => 'Website', 'client_id' => 1, 'active' => true],
            ]),
        ]);

        $result = $this->service()->backfillIdReferences($this->organization);

        $this->assertSame(1, $result['projects']);
        $this->assertSame(1, $result['clients']);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT_ID,
            'external_id' => '1',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT_ID,
            'external_id' => '10',
            'referenceable_id' => $project->id,
        ]);
    }

    public function test_suggest_customer_and_project_return_close_matches(): void {
        $project = $this->customerWithProject('Acme GmbH', 'Webseite Relaunch');

        // Leicht abweichende Toggl-Schreibweise → Fuzzy-Vorschlag greift.
        $customer = $this->service()->suggestCustomer($this->organization, 'Acme Gmbh');
        $this->assertNotNull($customer);
        $this->assertSame($project->customer_id, $customer->id);

        $suggested = $this->service()->suggestProject($this->organization, $customer, 'Webseite-Relaunch');
        $this->assertNotNull($suggested);
        $this->assertSame($project->id, $suggested->id);

        // Komplett fremder Name → kein Vorschlag.
        $this->assertNull($this->service()->suggestCustomer($this->organization, 'Völlig anderer Laden'));
    }

    public function test_book_group_creates_new_customer_and_project_when_requested(): void {
        $this->enableToggl();
        $this->seedInboxEntry('Neukunde AG', 'Migration', 'csv:new', '2026-05-26 09:00:00', '2026-05-26 10:30:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('Neukunde AG', 'Migration'),
                'customer_mode' => 'new',
                'new_customer_name' => 'Neukunde AG',
                'project_mode' => 'new',
                'new_project_name' => 'Migration',
            ])
            ->assertRedirect();

        $customer = Customer::query()->where('name', 'Neukunde AG')->first();
        $this->assertNotNull($customer);

        $project = Project::query()->where('name', 'Migration')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($project);

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(90, $entry->minutes);

        // Referenzen gemerkt → künftiger Import matcht automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'external_id' => 'Neukunde AG',
            'referenceable_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'csv:new',
            'status' => IntegrationInboxItem::STATUS_RESOLVED_CREATED,
        ]);
    }

    public function test_book_group_to_existing_customer_with_new_project(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestand GmbH',
        ]);

        $this->seedInboxEntry('Bestand GmbH', 'Support 2026', 'csv:exist', '2026-05-26 09:00:00', '2026-05-26 10:00:00', false);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('Bestand GmbH', 'Support 2026'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'project_mode' => 'new',
                'new_project_name' => 'Support 2026',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Support 2026')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($project);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());
    }

    public function test_book_group_as_internal_project_without_customer(): void {
        $this->enableToggl();
        // Toggl-Eintrag ganz ohne Client → internes Firmenprojekt.
        $this->seedInboxEntry('', 'Interne Weiterbildung', 'csv:intern', '2026-05-26 09:00:00', '2026-05-26 11:00:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('', 'Interne Weiterbildung'),
                'customer_mode' => 'internal',
                'project_mode' => 'new',
                'new_project_name' => 'Interne Weiterbildung',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Interne Weiterbildung')->first();
        $this->assertNotNull($project);
        $this->assertNull($project->customer_id, 'Internes Projekt darf keinen Kunden haben.');

        $entry = TimeEntry::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(120, $entry->minutes);

        // Kein Kunde angelegt und keine Client-Referenz gemerkt.
        $this->assertSame(0, Customer::query()->count());
        $this->assertDatabaseMissing('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
        ]);

        // Projekt-Referenz wird gemerkt → künftiger Import matcht automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT,
            'referenceable_id' => $project->id,
        ]);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'csv:intern',
            'status' => IntegrationInboxItem::STATUS_RESOLVED_CREATED,
        ]);
    }

    public function test_csv_import_books_entries_on_matching_org_user_by_email(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        $csv = implode("\n", [
            'Client,Project,Description,Start date,Start time,End date,End time,Duration,Billable,Email',
            'Acme,Website,Arbeit A,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,Yes,worker@example.com',
            'Acme,Website,Arbeit B,2026-05-26,11:00:00,2026-05-26,12:00:00,01:00:00,Yes,unbekannt@example.com',
        ]);

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(2, $result['created']);
        // Mitarbeiter-E-Mail der CSV gewinnt; unbekannte E-Mail → Buchungs-Benutzer (Owner).
        $this->assertSame(1, TimeEntry::query()->where('user_id', $mitarbeiter->id)->count());
        $this->assertSame(1, TimeEntry::query()->where('user_id', $this->organization->owner_id)->count());
    }

    public function test_user_email_mapping_wins_over_direct_email_match(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        // Mitarbeiter mit abweichender Toggl-Adresse: Zuordnung gemerkt.
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'firma@workdiary.local',
        ]);
        $this->service()->rememberUserEmail($this->organization, 'privat@gmx.de', $mitarbeiter);

        $csv = implode("\n", [
            'Client,Project,Description,Start date,Start time,End date,End time,Duration,Billable,Email',
            'Acme,Website,Arbeit A,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,Yes,privat@gmx.de',
        ]);

        $result = $this->service()->importFromCsv($this->organization, $csv, $config);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, TimeEntry::query()->where('user_id', $mitarbeiter->id)->count());
    }

    public function test_store_user_mapping_endpoint_persists_reference(): void {
        $this->enableToggl();
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'firma@workdiary.local',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.store-user'), [
                'toggl_email' => 'Privat@GMX.de',
                'user' => $mitarbeiter->sqid,
            ])
            ->assertRedirect();

        // Schlüssel wird normalisiert (lowercase) gespeichert.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_USER_EMAIL,
            'external_id' => 'privat@gmx.de',
            'referenceable_id' => $mitarbeiter->id,
        ]);
    }

    public function test_mappings_page_offers_known_toggl_emails_as_dropdown(): void {
        $this->enableToggl();
        FakePluginHttp::fake([]); // API liefert leer — Snapshot-Quelle bleibt.

        // Bekannte Adresse aus einem offenen Inbox-Snapshot (CSV-Quelle).
        $item = $this->seedInboxEntry('Acme', 'Website', 'csv:mail1', '2026-05-26 09:00:00', '2026-05-26 10:00:00');
        $item->update(['remote_snapshot' => array_merge((array) $item->remote_snapshot, ['user_email' => 'privat@gmx.de'])]);

        $this->actingAs($this->admin)
            ->get(route('admin.toggl.mappings.index'))
            ->assertOk()
            ->assertSee('privat@gmx.de');

        // Nach der Zuordnung verschwindet die Adresse aus dem Dropdown,
        // bleibt aber als Benutzer-Zeile in der Tabelle sichtbar.
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'firma2@workdiary.local',
        ]);
        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.store-user'), [
                'toggl_email' => 'privat@gmx.de',
                'user' => $mitarbeiter->sqid,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.toggl.mappings.index'))
            ->assertOk()
            ->assertDontSee('option value="privat@gmx.de"', false)
            ->assertSee('privat@gmx.de');
    }

    public function test_second_email_for_same_user_is_stored_as_alias_and_listed(): void {
        $this->enableToggl();
        FakePluginHttp::fake([]);
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'firma@workdiary.local',
        ]);

        // Erste Adresse → Primär-Referenz, zweite → Alias (extref_unique).
        foreach (['privat@gmx.de', 'zweit@gmx.de'] as $email) {
            $this->actingAs($this->admin)
                ->post(route('admin.toggl.mappings.store-user'), [
                    'toggl_email' => $email,
                    'user' => $mitarbeiter->sqid,
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseHas('external_reference_aliases', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_USER_EMAIL,
            'external_id' => 'zweit@gmx.de',
            'referenceable_id' => $mitarbeiter->id,
        ]);

        // Beide Adressen lösen auf denselben Benutzer auf und erscheinen in der Tabelle.
        $this->assertSame($mitarbeiter->id, $this->service()->resolveImportUser($this->organization, 'zweit@gmx.de'));
        $this->actingAs($this->admin)
            ->get(route('admin.toggl.mappings.index'))
            ->assertOk()
            ->assertSee('privat@gmx.de')
            ->assertSee('zweit@gmx.de');

        // Alias-Zeile lässt sich entfernen.
        $aliasId = (int) \App\Models\ExternalReferenceAlias::query()
            ->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL)
            ->where('external_id', 'zweit@gmx.de')
            ->value('id');
        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.user-alias.delete', $aliasId))
            ->assertRedirect();
        $this->assertDatabaseMissing('external_reference_aliases', ['id' => $aliasId]);
    }

    public function test_repair_command_reassigns_users_from_csv(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        $mitarbeiter = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        $csv = implode("\n", [
            'Client,Project,Description,Start date,Start time,End date,End time,Duration,Billable,Email',
            'Acme,Website,Arbeit A,2026-05-26,09:00:00,2026-05-26,10:00:00,01:00:00,Yes,worker@example.com',
        ]);

        // Alt-Zustand simulieren: importiert, aber alles auf den Owner gebucht.
        $this->service()->importFromCsv($this->organization, $csv, $config);
        TimeEntry::query()->update(['user_id' => $this->organization->owner_id]);

        $path = tempnam(sys_get_temp_dir(), 'toggl-repair-');
        file_put_contents($path, $csv);

        // Dry-Run ändert nichts.
        $this->artisan('toggl:repair-entry-users', ['csv' => $path, '--organization' => $this->organization->id])
            ->assertExitCode(0);
        $this->assertSame(1, TimeEntry::query()->where('user_id', $this->organization->owner_id)->count());

        // --apply setzt den Benutzer anhand der CSV-E-Mail um; die Organisation
        // ist auch per Slug adressierbar.
        $this->artisan('toggl:repair-entry-users', ['csv' => $path, '--organization' => $this->organization->slug, '--apply' => true])
            ->assertExitCode(0);
        $this->assertSame(1, TimeEntry::query()->where('user_id', $mitarbeiter->id)->count());
        $this->assertSame(0, TimeEntry::query()->where('user_id', $this->organization->owner_id)->count());

        @unlink($path);
    }

    public function test_pending_groups_are_separated_per_workspace(): void {
        // Gleicher (leerer) Client/Projekt-Schlüssel, aber verschiedene
        // Workspaces → zwei getrennte Gruppen mit Workspace-Anzeige.
        $this->seedInboxEntry('', '', 'csv:w1', '2026-05-26 09:00:00', '2026-05-26 10:00:00', true, null, 111, 'Eigene Firma');
        $this->seedInboxEntry('', '', 'csv:w2', '2026-05-26 11:00:00', '2026-05-26 12:00:00', true, null, 222, 'LDS Systems');

        $groups = $this->service()->openInboxGroups($this->organization);
        $this->assertCount(2, $groups);
        $this->assertEqualsCanonicalizing(
            ['Eigene Firma', 'LDS Systems'],
            $groups->pluck('workspace_name')->all(),
        );
    }

    public function test_open_inbox_groups_include_entry_preview(): void {
        $this->seedInboxEntry('LDS Systems GmbH', 'Firma', 'csv:p1', '2026-05-26 09:00:00', '2026-05-26 09:24:00', true, 'Serverwartung');
        $this->seedInboxEntry('LDS Systems GmbH', 'Firma', 'csv:p2', '2026-05-27 10:00:00', '2026-05-27 11:00:00');

        $groups = $this->service()->openInboxGroups($this->organization);
        $this->assertCount(1, $groups);

        $entries = $groups->first()['entries'];
        $this->assertCount(2, $entries);
        $this->assertSame('Serverwartung', $entries[0]['description']);
        $this->assertSame(24, $entries[0]['minutes']);
        $this->assertNull($entries[1]['description']);
        $this->assertSame(0, $groups->first()['entries_more']);
    }

    public function test_book_group_with_new_foreign_customer_links_project_and_reference(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);

        // Toggl-Client „LDS" ist Endkunde der Firma X — Projekt entsteht bei der
        // Firma und verweist auf den Fremdkunden.
        $this->seedInboxEntry('LDS', 'Portal', 'csv:fc1', '2026-05-26 09:00:00', '2026-05-26 10:00:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('LDS', 'Portal'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'foreign_mode' => 'new',
                'new_foreign_customer_name' => 'LDS',
                'project_mode' => 'new',
                'new_project_name' => 'Portal',
            ])
            ->assertRedirect();

        $foreign = ForeignCustomer::query()->where('name', 'LDS')->where('customer_id', $customer->id)->first();
        $this->assertNotNull($foreign);

        $project = Project::query()->where('name', 'Portal')->first();
        $this->assertNotNull($project);
        $this->assertSame($customer->id, $project->customer_id);
        $this->assertSame($foreign->id, $project->foreign_customer_id);
        $this->assertSame(1, TimeEntry::query()->where('project_id', $project->id)->count());

        // Client-Referenz zeigt auf den Fremdkunden (präziserer Schlüssel).
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'external_id' => 'LDS',
            'referenceable_type' => $foreign->getMorphClass(),
            'referenceable_id' => $foreign->id,
        ]);
    }

    public function test_booking_second_group_into_same_project_creates_alias_instead_of_crashing(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Firma X']);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Sammelprojekt',
            'is_default' => false,
        ]);

        // Erste Gruppe → Projekt: Primär-Referenz entsteht.
        $this->seedInboxEntry('Firma X', 'Projekt A', 'csv:a1', '2026-05-26 09:00:00', '2026-05-26 10:00:00');
        $this->actingAs($this->admin)->post(route('admin.integration.inbox.group.book'), [
            'plugin' => TogglPlugin::ID,
            'group_key' => $this->groupKey('Firma X', 'Projekt A'),
            'customer_mode' => 'existing',
            'customer' => $customer->sqid,
            'project_mode' => 'existing',
            'project' => $project->sqid,
        ])->assertRedirect();

        // Zweite Gruppe (anderer Schlüssel) → DASSELBE Projekt: verletzte früher
        // extref_unique (Duplicate 1062), jetzt Alias-Weiterleitung.
        $this->seedInboxEntry('Firma X', 'Projekt B', 'csv:b1', '2026-05-27 09:00:00', '2026-05-27 10:00:00');
        $this->actingAs($this->admin)->post(route('admin.integration.inbox.group.book'), [
            'plugin' => TogglPlugin::ID,
            'group_key' => $this->groupKey('Firma X', 'Projekt B'),
            'customer_mode' => 'existing',
            'customer' => $customer->sqid,
            'project_mode' => 'existing',
            'project' => $project->sqid,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());
        $this->assertDatabaseHas('external_reference_aliases', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_PROJECT,
            'external_id' => $this->groupKey('Firma X', 'Projekt B'),
            'referenceable_id' => $project->id,
        ]);

        // Folge-Import matcht den Alias-Schlüssel automatisch aufs Projekt.
        $entry = new ImportedTimeEntry(
            entryKey: 'match:alias',
            clientName: 'Firma X',
            projectName: 'Projekt B',
            activity: null,
            description: null,
            startedAt: CarbonImmutable::parse('2026-05-28 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-05-28 10:00:00'),
        );
        $this->assertSame($project->id, $this->service()->matchProject($this->organization, $entry)?->id);
    }

    public function test_book_group_ignores_foreign_customer_equal_to_customer_name(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'LDS Systems GmbH',
        ]);

        // Client = Firma selbst (Prefill unverändert übernommen) → kein Endkunde.
        $this->seedInboxEntry('LDS Systems GmbH', 'Firma', 'csv:self', '2026-05-26 09:00:00', '2026-05-26 09:24:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('LDS Systems GmbH', 'Firma'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'foreign_mode' => 'new',
                'new_foreign_customer_name' => 'LDS Systems GmbH',
                'project_mode' => 'new',
                'new_project_name' => 'Firma',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'Firma')->first();
        $this->assertNotNull($project);
        $this->assertSame($customer->id, $project->customer_id);
        $this->assertNull($project->foreign_customer_id);
        $this->assertSame(0, ForeignCustomer::query()->count());
    }

    public function test_book_group_with_existing_foreign_customer_creates_second_project(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'LDS',
        ]);

        $this->seedInboxEntry('LDS', 'App', 'csv:fc2', '2026-05-26 11:00:00', '2026-05-26 12:00:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('LDS', 'App'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'foreign_mode' => 'existing',
                'foreign_customer' => $foreign->sqid,
                'project_mode' => 'new',
                'new_project_name' => 'App',
            ])
            ->assertRedirect();

        $project = Project::query()->where('name', 'App')->first();
        $this->assertNotNull($project);
        $this->assertSame($foreign->id, $project->foreign_customer_id);
    }

    public function test_book_group_rejects_existing_project_of_other_foreign_customer(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);
        $lds = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'LDS',
        ]);
        $thieme = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Thieme',
        ]);
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $thieme->id,
            'name' => 'Support',
            'is_default' => false,
        ]);

        $this->seedInboxEntry('LDS', 'Support', 'csv:fc3', '2026-05-26 09:00:00', '2026-05-26 09:30:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('LDS', 'Support'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'foreign_mode' => 'existing',
                'foreign_customer' => $lds->sqid,
                'project_mode' => 'existing',
                'project' => $project->sqid,
            ])
            ->assertStatus(422);
    }

    public function test_book_group_without_foreign_rejects_foreign_owned_project(): void {
        $this->enableToggl();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);
        $lds = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'LDS',
        ]);
        $foreignProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $lds->id,
            'name' => 'Clients',
            'is_default' => false,
        ]);

        $this->seedInboxEntry('Firma X', 'Clients', 'csv:fc5', '2026-05-26 09:00:00', '2026-05-26 09:30:00');

        // „Kein Fremdkunde" + endkunden-gebundenes Projekt → abgelehnt.
        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('Firma X', 'Clients'),
                'customer_mode' => 'existing',
                'customer' => $customer->sqid,
                'foreign_mode' => 'none',
                'project_mode' => 'existing',
                'project' => $foreignProject->sqid,
            ])
            ->assertStatus(422);
    }

    public function test_book_group_rejects_foreign_customer_of_other_customer(): void {
        $this->enableToggl();
        $customerA = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma A',
        ]);
        $customerB = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma B',
        ]);
        $foreignOfB = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customerB->id,
            'name' => 'LDS',
        ]);

        $this->seedInboxEntry('LDS', 'Portal', 'csv:fc4', '2026-05-26 09:00:00', '2026-05-26 09:30:00');

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => $this->groupKey('LDS', 'Portal'),
                'customer_mode' => 'existing',
                'customer' => $customerA->sqid,
                'foreign_mode' => 'existing',
                'foreign_customer' => $foreignOfB->sqid,
                'project_mode' => 'new',
                'new_project_name' => 'Portal',
            ])
            ->assertStatus(422);
    }

    public function test_match_project_scopes_by_foreign_customer_reference(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);
        $lds = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'LDS',
        ]);
        $thieme = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Thieme',
        ]);
        // Gleichnamige Projekte verschiedener Endkunden derselben Firma.
        $thiemeProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $thieme->id,
            'name' => 'Support',
            'is_default' => false,
        ]);
        $ldsProject = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $lds->id,
            'name' => 'Support',
            'is_default' => false,
        ]);

        // Gemerkte Client-Referenz: „LDS" → Fremdkunde LDS.
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $lds->getMorphClass(),
            'referenceable_id' => $lds->id,
            'external_id' => 'LDS',
            'synced_at' => now(),
        ]);

        $entry = new ImportedTimeEntry(
            entryKey: 'match:fc',
            clientName: 'LDS',
            projectName: 'Support',
            activity: null,
            description: null,
            startedAt: CarbonImmutable::parse('2026-05-26 09:00:00'),
            endedAt: CarbonImmutable::parse('2026-05-26 10:00:00'),
        );

        $matched = $this->service()->matchProject($this->organization, $entry);
        $this->assertNotNull($matched);
        $this->assertSame($ldsProject->id, $matched->id);
        $this->assertNotSame($thiemeProject->id, $matched->id);

        // matchCustomer löst den Fremdkunden zur Firma auf.
        $resolved = $this->service()->matchCustomer($this->organization, 'LDS');
        $this->assertNotNull($resolved);
        $this->assertSame($customer->id, $resolved->id);
    }

    public function test_suggest_foreign_customer_prefers_reference_then_fuzzy(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Firma X',
        ]);
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Thieme Verlag',
            'company' => null,
        ]);

        // Fuzzy: leicht abweichende Schreibweise trifft den Fremdkunden.
        $suggested = $this->service()->suggestForeignCustomer($this->organization, 'Thieme-Verlag');
        $this->assertNotNull($suggested);
        $this->assertSame($foreign->id, $suggested->id);

        // Referenz schlägt Fuzzy: gemerkter Client-Name zeigt exakt auf den Fremdkunden.
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $foreign->getMorphClass(),
            'referenceable_id' => $foreign->id,
            'external_id' => 'TV',
            'synced_at' => now(),
        ]);
        $byRef = $this->service()->suggestForeignCustomer($this->organization, 'TV');
        $this->assertNotNull($byRef);
        $this->assertSame($foreign->id, $byRef->id);

        $this->assertNull($this->service()->suggestForeignCustomer($this->organization, 'Völlig anderer Endkunde'));
    }

    public function test_update_and_delete_mapping(): void {
        $beta = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Beta']);
        $gamma = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Gamma']);

        $ref = ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $beta->getMorphClass(),
            'referenceable_id' => $beta->id,
            'external_id' => 'Beta Client',
            'synced_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.update', $ref->id), ['target_id' => $gamma->sqid])
            ->assertRedirect();

        $this->assertDatabaseHas('external_references', [
            'id' => $ref->id,
            'referenceable_id' => $gamma->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.delete', $ref->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('external_references', ['id' => $ref->id]);
    }

    public function test_update_client_mapping_to_foreign_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Firma X']);
        $foreign = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'LDS',
        ]);

        $ref = ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_CLIENT,
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->id,
            'external_id' => 'LDS',
            'synced_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.toggl.mappings.update', $ref->id), ['target_id' => $foreign->sqid])
            ->assertRedirect();

        $this->assertDatabaseHas('external_references', [
            'id' => $ref->id,
            'referenceable_type' => $foreign->getMorphClass(),
            'referenceable_id' => $foreign->id,
        ]);
    }

    public function test_non_billing_user_cannot_book_group(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => TogglPlugin::ID,
                'group_key' => 'x|y',
                'customer_mode' => 'new',
                'new_customer_name' => 'X',
                'project_mode' => 'new',
                'new_project_name' => 'Y',
            ])
            ->assertForbidden();
    }
}
