<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglUserResolutionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{Customer, ExternalReference, IntegrationInboxItem, PluginSetting, Project, TimeEntry, User};
use App\Plugins\Support\MatchingTimeImportService;
use App\Plugins\Toggl\{TogglConfig, TogglImportService, TogglPlugin};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-509: deterministische Benutzer-Zuordnung beim Toggl-Import.
 * Mehrbenutzer-Modus ist der Standard: kein stiller Hauptbenutzer-Fallback,
 * unbekannte Quell-Benutzer landen als offene Zuordnungsfälle in der Inbox.
 */
class TogglUserResolutionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $owner;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $this->owner->id])->save();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $settings */
    private function enableToggl(array $settings = []): array {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => array_merge(['api_token' => 'test-token'], $settings),
        ]);

        return TogglConfig::resolve($this->organization->id);
    }

    private function service(): TogglImportService {
        return new TogglImportService;
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

    /**
     * Reports-Stubs: Workspace 7, Projekt 9 (Acme|Website), zwei Einträge der
     * Toggl-User 41/42. Benutzerliste konfigurierbar (fehlend/verweigert).
     *
     * @param  array<int, array{id: int, email: string, fullname: string}>|null  $users  null = 403
     * @return array<string, mixed>
     */
    private function reportStubs(?array $users): array {
        return [
            'https://api.track.toggl.com/api/v9/me/time_entries*' => FakePluginHttp::response([], 200),
            'https://api.track.toggl.com/api/v9/me*' => FakePluginHttp::response(['email' => 'chef@example.com', 'clients' => [], 'projects' => []], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/users*' => $users === null
                ? FakePluginHttp::response(['error' => 'forbidden'], 403)
                : FakePluginHttp::response($users, 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/clients*' => FakePluginHttp::response([['id' => 5, 'name' => 'Acme']], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/projects*' => FakePluginHttp::response([['id' => 9, 'name' => 'Website', 'client_id' => 5, 'active' => true]], 200),
            'https://api.track.toggl.com/api/v9/workspaces/7/tags*' => FakePluginHttp::response([], 200),
            'https://api.track.toggl.com/api/v9/workspaces' => FakePluginHttp::response([['id' => 7, 'name' => 'Firma WS']], 200),
            'https://api.track.toggl.com/reports/api/v3/workspace/7/search/time_entries*' => FakePluginHttp::response([
                [
                    'project_id' => 9,
                    'description' => 'Arbeit A',
                    'billable' => true,
                    'user_id' => 41,
                    'time_entries' => [['id' => 501, 'start' => '2026-05-26T08:00:00+00:00', 'stop' => '2026-05-26T09:00:00+00:00']],
                ],
                [
                    'project_id' => 9,
                    'description' => 'Arbeit B',
                    'billable' => true,
                    'user_id' => 42,
                    'time_entries' => [['id' => 502, 'start' => '2026-05-26T10:00:00+00:00', 'stop' => '2026-05-26T11:30:00+00:00']],
                ],
            ], 200),
        ];
    }

    /** @return array{created: int, skipped: int, unmatched: int, unresolved_users: int, updated: int, conflicts: int, removed: int, incomplete: bool} */
    private function runImport(array $config): array {
        return $this->service()->importFromApi(
            $this->organization,
            $config,
            CarbonImmutable::parse('2026-05-25'),
            CarbonImmutable::parse('2026-05-27'),
        );
    }

    public function test_report_with_two_users_books_on_their_own_accounts(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        $anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);
        $ben = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'ben@example.com']);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'ben@example.com', 'fullname' => 'Ben'],
        ]));

        $result = $this->runImport($config);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['unresolved_users']);
        $this->assertSame($anna->id, TimeEntry::query()->where('description', 'like', '%Arbeit A%')->firstOrFail()->user_id);
        $this->assertSame($ben->id, TimeEntry::query()->where('description', 'like', '%Arbeit B%')->firstOrFail()->user_id);
        $this->assertSame(2, TimeEntry::query()->where('project_id', $project->id)->count());
    }

    public function test_unknown_toggl_user_lands_in_inbox_instead_of_owner(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'fremd@toggl.example', 'fullname' => 'Fremd'],
        ]));

        $result = $this->runImport($config);

        $this->assertSame(1, $result['created'], 'Nur der auflösbare Benutzer wird gebucht.');
        $this->assertSame(1, $result['unresolved_users']);
        $this->assertSame(0, TimeEntry::query()->where('user_id', $this->owner->id)->count(), 'Kein stiller Owner-Fallback.');

        $item = IntegrationInboxItem::query()
            ->where('plugin_id', TogglPlugin::ID)
            ->where('group_key', MatchingTimeImportService::PENDING_USER_GROUP_PREFIX . 'fremd@toggl.example')
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->first();
        $this->assertNotNull($item, 'Unbekannter Benutzer wird als offener Zuordnungsfall abgelegt.');
    }

    public function test_explicit_mapping_wins_over_email_equality(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        // Ein Org-User trägt GENAU die Toggl-Adresse, ein anderer ist explizit gemappt.
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);
        $mapped = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'privat@example.com']);
        $this->service()->rememberUserEmail($this->organization, 'anna@example.com', $mapped);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'anna@example.com', 'fullname' => 'Anna 2'],
        ]));

        $result = $this->runImport($config);

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, TimeEntry::query()->where('user_id', $mapped->id)->count(), 'Explizite Zuordnung schlägt E-Mail-Gleichheit.');
    }

    public function test_failed_workspace_user_fetch_marks_run_incomplete_and_books_nobody_silently(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        FakePluginHttp::fake($this->reportStubs(null));

        $result = $this->runImport($config);

        $this->assertTrue($result['incomplete'], 'Fehlende Workspace-Benutzerliste macht den Lauf unvollständig.');
        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['unresolved_users'], 'Einträge ohne E-Mail werden zur Klärung gestellt.');
        $this->assertSame(0, $result['removed'], 'Löschungserkennung bleibt bei unvollständigem Lauf aus.');
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_single_user_mode_books_default_user_when_explicitly_chosen(): void {
        $config = $this->enableToggl(['single_user_mode' => true, 'default_user_id' => $this->owner->id]);
        $this->customerWithProject('Acme', 'Website');

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'nix@toggl.example', 'fullname' => 'Nix'],
            ['id' => 42, 'email' => 'auch-nix@toggl.example', 'fullname' => 'Auch Nix'],
        ]));

        $result = $this->runImport($config);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['unresolved_users']);
        $this->assertSame(2, TimeEntry::query()->where('user_id', $this->owner->id)->count());
    }

    public function test_next_import_after_mapping_books_and_closes_open_case(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'fremd@toggl.example', 'fullname' => 'Fremd'],
        ]));
        $this->runImport($config);

        $open = IntegrationInboxItem::query()
            ->where('group_key', MatchingTimeImportService::PENDING_USER_GROUP_PREFIX . 'fremd@toggl.example')
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->firstOrFail();

        // Zuordnung pflegen (wie über „Zuordnungen verwalten"), dann Folgelauf.
        $target = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'neu@example.com']);
        $this->service()->rememberUserEmail($this->organization, 'fremd@toggl.example', $target);

        $result = $this->runImport($config);

        $this->assertSame(1, $result['created'], 'Folgelauf bucht den zuvor offenen Eintrag.');
        $this->assertSame(1, TimeEntry::query()->where('user_id', $target->id)->count());
        $this->assertSame(IntegrationInboxItem::STATUS_RESOLVED_CREATED, $open->fresh()->status, 'Der offene Fall wird geschlossen.');
    }

    public function test_booking_user_group_via_inbox_remembers_mapping_and_books_per_entry_project(): void {
        $config = $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'fremd@toggl.example', 'fullname' => 'Fremd'],
        ]));
        $this->runImport($config);

        $target = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'ziel@example.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => 'toggl',
                'group_key' => MatchingTimeImportService::PENDING_USER_GROUP_PREFIX . 'fremd@toggl.example',
                'user' => Sqid::encode(User::class, $target->id),
            ])
            ->assertRedirect();

        $entry = TimeEntry::query()->where('user_id', $target->id)->first();
        $this->assertNotNull($entry, 'Die Gruppe wird auf den gewählten Benutzer gebucht.');
        $this->assertSame($project->id, $entry->project_id, 'Das Projekt kommt aus dem Eintrag, nicht aus der Gruppe.');

        // Die Wahl ist als user_email-Referenz gemerkt — Folgeimporte treffen automatisch.
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_USER_EMAIL,
            'external_id' => 'fremd@toggl.example',
            'referenceable_id' => $target->id,
        ]);
    }

    public function test_booking_user_group_rejects_cross_org_user(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'fremd@toggl.example', 'fullname' => 'Fremd'],
            ['id' => 42, 'email' => 'fremd@toggl.example', 'fullname' => 'Fremd'],
        ]));
        $this->runImport($config);

        $otherOrg = \App\Models\Organization::factory()->create();
        $foreign = User::factory()->user()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.integration.inbox.group.book'), [
                'plugin' => 'toggl',
                'group_key' => MatchingTimeImportService::PENDING_USER_GROUP_PREFIX . 'fremd@toggl.example',
                'user' => Sqid::encode(User::class, $foreign->id),
            ])
            ->assertRedirect();

        $this->assertSame(0, TimeEntry::query()->count(), 'Fremd-Org-Benutzer bucht nichts.');
        $this->assertSame(0, TimeEntry::query()->where('user_id', $foreign->id)->count());
        $this->assertDatabaseMissing('external_references', [
            'external_type' => MatchingTimeImportService::EXT_TYPE_USER_EMAIL,
            'external_id' => 'fremd@toggl.example',
        ]);
    }

    public function test_booking_project_group_regroups_unresolvable_users_instead_of_silent_default(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');

        // Offene Projekt-Gruppe mit einer nicht auflösbaren Quell-E-Mail.
        IntegrationInboxItem::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'source' => 'csv',
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => 'entry',
            'external_id' => 'csv:regroup-1',
            'dedupe_key' => 'entry:csv:regroup-1',
            'group_key' => 'acme|website',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
            'remote_snapshot' => [
                'source' => 'csv',
                'entry_key' => 'csv:regroup-1',
                'client_name' => 'Acme',
                'project_name' => 'Website',
                'started_at' => '2026-05-26T08:00:00+00:00',
                'ended_at' => '2026-05-26T09:00:00+00:00',
                'billable' => true,
                'user_email' => 'unbekannt@toggl.example',
            ],
            'display_title' => 'Website',
            'display_subtitle' => 'Acme',
            'occurred_at' => CarbonImmutable::parse('2026-05-26T08:00:00+00:00'),
        ]);

        $result = $this->service()->bookInboxGroup($this->organization, 'acme|website', $project->customer, $project);

        $this->assertSame(0, $result['created'], 'Ohne auflösbaren Benutzer wird nicht still gebucht.');
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('integration_inbox_items', [
            'external_id' => 'csv:regroup-1',
            'group_key' => MatchingTimeImportService::PENDING_USER_GROUP_PREFIX . 'unbekannt@toggl.example',
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    public function test_reports_pagination_resolves_users_across_pages(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        $anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);
        $ben = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'ben@example.com']);

        $stubs = $this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'ben@example.com', 'fullname' => 'Ben'],
        ]);
        // Zwei Report-Seiten über den X-Next-Row-Number-Mechanismus.
        $stubs['https://api.track.toggl.com/reports/api/v3/workspace/7/search/time_entries*'] = [
            FakePluginHttp::response([[
                'project_id' => 9,
                'description' => 'Seite 1',
                'billable' => true,
                'user_id' => 41,
                'time_entries' => [['id' => 601, 'start' => '2026-05-26T08:00:00+00:00', 'stop' => '2026-05-26T09:00:00+00:00']],
            ]], 200, ['X-Next-Row-Number' => '2']),
            FakePluginHttp::response([[
                'project_id' => 9,
                'description' => 'Seite 2',
                'billable' => true,
                'user_id' => 42,
                'time_entries' => [['id' => 602, 'start' => '2026-05-26T10:00:00+00:00', 'stop' => '2026-05-26T11:00:00+00:00']],
            ]], 200),
        ];
        FakePluginHttp::fake($stubs);

        $result = $this->runImport($config);

        $this->assertSame(2, $result['created'], 'Beide Report-Seiten werden verarbeitet.');
        $this->assertFalse($result['incomplete']);
        $this->assertSame($anna->id, TimeEntry::query()->where('description', 'like', '%Seite 1%')->firstOrFail()->user_id);
        $this->assertSame($ben->id, TimeEntry::query()->where('description', 'like', '%Seite 2%')->firstOrFail()->user_id);
    }

    public function test_reports_row_wins_deduplication_against_me_endpoint(): void {
        $config = $this->enableToggl();
        $this->customerWithProject('Acme', 'Website');
        $chef = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'chef@example.com']);
        $anna = User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        // /me liefert DENSELBEN Eintrag (Token-Inhaber-Sicht, E-Mail chef@) wie
        // die Reports-API (workspacebezogene Identität: Toggl-User 41 = anna@).
        $stubs = $this->reportStubs([['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna']]);
        $stubs['https://api.track.toggl.com/api/v9/me/time_entries*'] = FakePluginHttp::response([[
            'id' => 501,
            'workspace_id' => 7,
            'project_id' => 9,
            'start' => '2026-05-26T08:00:00+00:00',
            'stop' => '2026-05-26T09:00:00+00:00',
            'billable' => true,
            'description' => 'Arbeit A',
        ]], 200);
        $stubs['https://api.track.toggl.com/reports/api/v3/workspace/7/search/time_entries*'] = FakePluginHttp::response([[
            'project_id' => 9,
            'description' => 'Arbeit A',
            'billable' => true,
            'user_id' => 41,
            'time_entries' => [['id' => 501, 'start' => '2026-05-26T08:00:00+00:00', 'stop' => '2026-05-26T09:00:00+00:00']],
        ]], 200);
        FakePluginHttp::fake($stubs);

        $result = $this->runImport($config);

        $this->assertSame(1, $result['created'], 'Dedupe über den Entry-Key: ein Eintrag, keine Dublette.');
        $entry = TimeEntry::query()->firstOrFail();
        $this->assertSame($anna->id, $entry->user_id, 'Die Reports-Zeile trägt die workspacebezogene Identität und gewinnt.');
        $this->assertSame(0, TimeEntry::query()->where('user_id', $chef->id)->count());
    }

    public function test_repair_command_reports_locked_entries_instead_of_changing_them(): void {
        $this->enableToggl();
        $project = $this->customerWithProject('Acme', 'Website');
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'email' => 'anna@example.com']);

        // Falsch zugeordneter, aber bereits exportierter Eintrag mit Toggl-Referenz.
        $locked = TimeEntry::query()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->owner->id,
            'date' => '2026-05-26',
            'started_at' => '2026-05-26 08:00:00',
            'ended_at' => '2026-05-26 09:00:00',
            'minutes' => 60,
            'exported' => true,
        ]);
        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => MatchingTimeImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $locked->getMorphClass(),
            'referenceable_id' => $locked->getKey(),
            'external_id' => 'toggl:501',
        ]);

        FakePluginHttp::fake($this->reportStubs([
            ['id' => 41, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
            ['id' => 42, 'email' => 'anna@example.com', 'fullname' => 'Anna'],
        ]));

        $this->artisan('toggl:repair-entry-users', ['--organization' => $this->organization->id, '--apply' => true, '--days' => 90])
            ->expectsOutputToContain('gesperrt (Beleg/Signatur)')
            ->assertExitCode(0);

        $this->assertSame($this->owner->id, $locked->fresh()->user_id, 'Exportierte Einträge werden nie automatisch umgehängt.');
    }
}
