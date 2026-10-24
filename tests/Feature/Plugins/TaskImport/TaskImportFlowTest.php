<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\{DataDomain, IntegrationInboxStatus, TaskImportLinkStatus};
use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Project\{Project, Task};
use App\Models\Time\TimeEntry;
use App\Plugins\Asana\Services\AsanaImportService;
use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Models\JiraProjectLink;
use App\Plugins\Jira\Services\JiraImportService;
use App\Plugins\PluginManager;
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\{TaskImportAttachException, TaskImportBusyException};
use App\Services\Integration\{DataOwnershipResolver, InboxActionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Ablauf der Aufgabenimporte (MVP-1309–1312): Vorschau ohne Schreibwirkung,
 * Anlage erst nach Bestätigung, ausdrückliches Verknüpfen, Datenführung vor
 * jedem Schreiben, Konfliktrunden ohne Rückexport, Lauf-Sperre, gleiche
 * Strecke für Befehl und Knopf, Healthcheck, Oberfläche und Trennung der
 * Organisationen.
 */
class TaskImportFlowTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;

    private User $admin;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->configureTaskImportApps();
        $this->enableTaskImportPlugin(JiraPlugin::ID);
        $this->jiraConnection();
        $this->admin = $this->orgAdmin();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
    }

    /** @param list<array<string, mixed>> $issues */
    private function serve(array $issues): FakePluginHttp {
        return FakePluginHttp::fake([
            $this->jiraSearchUrl() => FakePluginHttp::response($this->jiraPage($issues)),
            '*/rest/api/3/issue/*' => FakePluginHttp::response($issues[0] ?? [], $issues === [] ? 404 : 200),
            '*/rest/api/3/project/search*' => FakePluginHttp::response(['values' => [
                ['id' => '10000', 'key' => 'MUS', 'name' => 'Musterprojekt'],
                ['id' => '10001', 'key' => 'ZWE', 'name' => 'Zweites'],
            ], 'isLast' => true]),
        ]);
    }

    private function jira(): JiraImportService {
        return app(JiraImportService::class);
    }

    public function test_preview_writes_nothing_and_a_pending_link_is_not_synced(): void {
        $link = $this->jiraLink($this->project, confirmed: false);
        $candidate = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Login reparieren']);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Login reparieren'), $this->jiraIssue('10002', 'MUS-2', 'Neu')]);

        $this->actingAs($this->admin)->get(route('admin.jira.links.preview', $link))
            ->assertOk()
            ->assertSee('MUS-2')
            ->assertSee(__('integration.task_import.preview.action.create'))
            ->assertSee(__('integration.task_import.preview.attach_candidate'))
            ->assertSee($candidate->sqid);

        $this->jira()->run($this->organization);
        $this->assertSame(1, Task::query()->count(), 'Weder Vorschau noch Lauf vor der Bestätigung schreiben.');
        $this->assertSame(0, ExternalReference::query()->count());
        $this->assertSame(0, IntegrationInboxItem::query()->count());

        $this->post(route('admin.jira.links.confirm', $link))->assertSessionHas('success');
        $this->assertSame(TaskImportLinkStatus::Active, $link->fresh()?->status);
        $this->post(route('admin.jira.sync'))->assertSessionHas('success');
        $this->assertSame(3, Task::query()->count());
    }

    public function test_explicit_attach_puts_differing_fields_up_for_review(): void {
        $link = $this->jiraLink($this->project, confirmed: false);
        $task = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'title' => 'Login reparieren', 'description' => null, 'status' => TaskStatus::InProgress]);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Login reparieren (Jira)')]);

        $this->actingAs($this->admin)->post(route('admin.jira.links.attach', $link), ['remote_id' => '10001', 'task' => $task->sqid])
            ->assertSessionHas('success', __('integration.task_import.flash.attached_with_conflicts', ['provider' => 'Jira', 'source' => 'Site']));

        $task->refresh();
        $this->assertSame('Login reparieren', $task->title, 'Abweichende Felder werden nicht still überschrieben.');
        $this->assertSame('Beschreibung MUS-1', $task->description, 'Leere lokale Felder werden gefüllt.');
        $conflict = IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->sole();
        $this->assertEqualsCanonicalizing(['title', 'status'], $conflict->diff_fields);

        $other = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id]);
        $this->post(route('admin.jira.links.attach', $link), ['remote_id' => '10001', 'task' => $other->sqid])
            ->assertSessionHas('error', __('integration.task_import.attach_refused.remote_linked'));
        $elsewhere = Task::factory()->create(['organization_id' => $this->organization->id]);
        $this->post(route('admin.jira.links.attach', $link), ['remote_id' => '10001', 'task' => $elsewhere->sqid])
            ->assertSessionHas('error', __('integration.task_import.attach_refused.wrong_target'));
    }

    public function test_attach_refuses_tasks_of_other_providers_and_other_organizations(): void {
        $link = $this->jiraLink($this->project);
        $todoist = Task::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id]);
        ExternalReference::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => 'todoist', 'external_type' => 'task', 'external_id' => '77', 'referenceable_type' => $todoist->getMorphClass(), 'referenceable_id' => $todoist->id]);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);

        try {
            $this->jira()->attach($this->organization, $link, '10001', $todoist);
            $this->fail('Eine Aufgabe mit anderem Anbieter darf nicht zusätzlich verknüpft werden.');
        } catch (TaskImportAttachException $e) {
            $this->assertSame('task_linked', $e->reason);
        }

        $foreignTask = Task::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->admin)->post(route('admin.jira.links.attach', $link), ['remote_id' => '10001', 'task' => $foreignTask->sqid])
            ->assertSessionHas('error', __('integration.task_import.attach_refused.wrong_target'));
        $this->assertSame(0, ExternalReference::query()->where('plugin_id', JiraPlugin::ID)->count());
    }

    public function test_foreign_data_ownership_blocks_create_and_update(): void {
        $this->jiraLink($this->project);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->jira()->run($this->organization);
        app(DataOwnershipResolver::class)->setOwner($this->organization, DataDomain::Tasks, 'todoist');

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A geändert'), $this->jiraIssue('10002', 'MUS-2', 'B')]);
        $counters = $this->jira()->run($this->organization);

        $this->assertSame(2, $counters['inbox']);
        $this->assertSame(['A'], Task::query()->pluck('title')->all());
        $this->assertSame('todoist', app(DataOwnershipResolver::class)->ownerFor($this->organization->fresh(), DataDomain::Tasks), 'Der Lauf ändert die Datenführung nicht.');
        $this->assertSame(2, IntegrationInboxItem::query()->where('dedupe_key', 'like', '%:ownership')->count());
    }

    public function test_keep_local_becomes_the_base_and_a_later_remote_change_opens_a_new_round(): void {
        $this->jiraLink($this->project);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->jira()->run($this->organization);
        $task = Task::query()->sole();
        $task->forceFill(['title' => 'Lokal'])->save();

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Remote 1')]);
        $this->assertSame(1, $this->jira()->run($this->organization)['conflicts']);
        $this->actingAs($this->admin);
        app(InboxActionService::class)->keepLocal(IntegrationInboxItem::query()->sole());

        $this->assertSame(0, $this->jira()->run($this->organization)['conflicts'], 'Entschiedener Konflikt kehrt nicht wieder.');
        $this->assertSame('Lokal', $task->fresh()?->title);

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Remote 2')]);
        $this->jira()->run($this->organization);
        $this->assertSame(2, IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->count());
        $open = IntegrationInboxItem::query()->where('status', IntegrationInboxStatus::Open)->sole();
        $this->assertSame('Remote 2', $open->mapped_snapshot['title'] ?? null);

        app(InboxActionService::class)->acceptRemote($open);
        $this->assertSame('Remote 2', $task->fresh()?->title);
        $this->assertSame(0, $this->jira()->run($this->organization)['conflicts']);
    }

    public function test_runs_are_locked_per_organization_and_provider(): void {
        $this->jiraLink($this->project);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $lock = Cache::lock('task-import:jira:' . $this->organization->id, 60);
        $lock->get();

        try {
            $this->jira()->run($this->organization);
            $this->fail('Ein zweiter Lauf derselben Organisation muss abgewiesen werden.');
        } catch (TaskImportBusyException) {
        }
        $this->artisan('jira:sync', ['--organization' => $this->organization->id])->expectsOutputToContain('läuft bereits')->assertSuccessful();
        $this->actingAs($this->admin)->post(route('admin.jira.sync'))->assertSessionHas('error');
        $this->assertSame(0, Task::query()->count());
        $this->assertSame(0, app(AsanaImportService::class)->run($this->organization)['failed'], 'Asana hat eine eigene Sperre.');

        $lock->release();
        $this->assertSame(1, $this->jira()->run($this->organization)['created']);
    }

    public function test_command_skips_a_disabled_plugin(): void {
        $this->jiraLink($this->project);
        $http = $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        PluginSetting::query()->where('plugin_id', JiraPlugin::ID)->update(['enabled' => false]);

        $this->artisan('jira:sync')->assertSuccessful();

        $http->assertNothingSent();
    }

    public function test_command_skips_paused_links_and_disconnected_connections(): void {
        $paused = $this->jiraLink($this->project);
        $paused->forceFill(['status' => TaskImportLinkStatus::Paused])->save();
        $http = $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->artisan('jira:sync')->assertSuccessful();
        $http->assertNothingSent();

        $paused->forceFill(['status' => TaskImportLinkStatus::Active])->save();
        \App\Plugins\Jira\Models\JiraConnection::query()->update(['status' => OAuthConnectionStatus::Disconnected->value, 'access_token' => null]);
        $this->artisan('jira:sync')->assertSuccessful();
        $http->assertNothingSent();
    }

    public function test_command_full_run_reads_all_issues(): void {
        $this->jiraLink($this->project);
        $http = $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);

        $this->artisan('jira:sync', ['--full' => true])->expectsOutputToContain('created 1')->assertSuccessful();

        $http->assertSent(function ($request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['jql'] ?? '') === 'project = 10000 ORDER BY created ASC';
        });
    }

    public function test_health_reports_configuration_connection_and_api_state(): void {
        $plugin = app(PluginManager::class)->find(JiraPlugin::ID);
        $this->assertNotNull($plugin);

        FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['values' => [], 'isLast' => true])]);
        $this->assertSame('ok', $plugin->healthCheck()->status);

        FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['message' => 'nope'], 401)]);
        $health = $plugin->healthCheck();
        $this->assertSame(['failing', 'auth'], [$health->status, $health->code]);

        FakePluginHttp::fake(['*/project/search*' => FakePluginHttp::response(['message' => 'slow down'], 429, ['Retry-After' => '0'])]);
        $this->assertSame('rate_limited', $plugin->healthCheck()->code);

        \App\Plugins\Jira\Models\JiraConnection::query()->update(['source_id' => null]);
        $this->assertSame('not_connected', $plugin->healthCheck()->code);
        config(['plugins.jira.client_secret' => '']);
        $this->assertSame('not_configured', $plugin->healthCheck()->code);
    }

    public function test_admin_page_mapping_dialog_and_access_rules(): void {
        $this->serve([]);
        $this->actingAs($this->orgUser())->get(route('admin.jira.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.jira.index'))->assertOk()
            ->assertSee(__('integration.task_import.links.empty'))
            ->assertSee(route('admin.jira.links.create'), false);
        $this->get(route('admin.jira.links.create'))->assertOk()->assertSee('Musterprojekt')->assertSee($this->project->sqid);

        $foreignProject = Project::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->post(route('admin.jira.links.store'), ['source_project_id' => '10000', 'project' => $foreignProject->sqid])->assertSessionHasErrors('project');
        $this->post(route('admin.jira.links.store'), ['source_project_id' => '55555', 'project' => $this->project->sqid])->assertSessionHasErrors('source_project_id');

        $this->post(route('admin.jira.links.store'), ['source_project_id' => '10000', 'project' => $this->project->sqid])->assertRedirect();
        $link = JiraProjectLink::query()->sole();
        $this->assertSame(TaskImportLinkStatus::Pending, $link->status);
        $this->assertSame('MUS', $link->source_project_key);
        $this->post(route('admin.jira.links.store'), ['source_project_id' => '10000', 'project' => $this->project->sqid])->assertSessionHasErrors('source_project_id');
        $this->get(route('admin.jira.links.create'))->assertOk()->assertDontSee('Musterprojekt')->assertSee('Zweites');

        $this->post(route('admin.jira.links.pause', $link))->assertSessionHas('error', __('integration.task_import.flash.status_invalid', ['provider' => 'Jira', 'source' => 'Site']));
        $this->delete(route('admin.jira.links.destroy', $link))->assertSessionHas('success');
        $this->assertSame(0, JiraProjectLink::query()->count());
    }

    public function test_organizations_stay_apart_and_task_links_only_point_to_the_site(): void {
        $this->jiraLink($this->project);
        $other = Organization::factory()->create();
        $this->enableTaskImportPlugin(JiraPlugin::ID, $other);
        $this->jiraConnection([], $other);
        $otherProject = Project::factory()->create(['organization_id' => $other->id]);
        $otherLink = $this->jiraLink($otherProject);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Gleiche ID, zwei Kunden')]);

        $this->jira()->run($this->organization);
        $this->jira()->run($other);

        $this->assertSame(2, Task::query()->withoutGlobalScopes()->where('title', 'Gleiche ID, zwei Kunden')->count());
        $this->actingAs($this->admin)->get(route('admin.jira.links.preview', $otherLink))->assertNotFound();
        $this->delete(route('admin.jira.links.destroy', $otherLink))->assertNotFound();

        $task = Task::query()->where('organization_id', $this->organization->id)->sole();
        $reference = ExternalReference::query()->forReferenceable($task)->sole();
        $plugin = app(PluginManager::class)->find(JiraPlugin::ID);
        $this->assertStringContainsString('href="' . self::JIRA_SITE . '/browse/MUS-1"', (string) $plugin?->renderActions('task-dialog.links', $task));

        $reference->forceFill(['payload' => ['remote' => ['url' => 'javascript:alert(1)']] + (array) $reference->payload])->save();
        $this->assertNull($this->jira()->sourceUrl($reference->fresh()));
        $reference->forceFill(['payload' => ['remote' => ['url' => 'https://evil.example.com/browse/MUS-1']] + (array) $reference->payload])->save();
        $this->assertNull($plugin?->renderActions('task-dialog.links', $task->fresh()));
    }

    public function test_time_bookings_survive_disconnect_and_vanished_source_tasks(): void {
        $this->jiraLink($this->project);
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->jira()->run($this->organization);
        $task = Task::query()->sole();
        $entry = TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'task_id' => $task->id, 'user_id' => $this->admin->id]);

        $this->serve([]);
        $this->jira()->run($this->organization, full: true);
        $this->actingAs($this->admin)->post(route('admin.jira.disconnect'));

        $this->assertNotNull($task->fresh());
        $this->assertSame((int) $task->id, (int) $entry->fresh()?->task_id);
        $this->assertSame(1, ExternalReference::query()->forReferenceable($task)->count());
    }
}
