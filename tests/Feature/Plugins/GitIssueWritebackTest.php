<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitIssueWritebackTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationOutboxEntry, PluginSetting, Task};
use App\Plugins\Github\GithubPlugin;
use App\Plugins\Github\Services\GithubIssueWritebackDispatcher;
use App\Plugins\Gitlab\GitlabPlugin;
use App\Plugins\Gitlab\Services\GitlabIssueWritebackDispatcher;
use App\Plugins\Support\GitIssueImport\GitIssueWritebackObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Git-Issue-Status-Rückrichtung (Audit 2026-08, Welle 1.4): eine in workDiary
 * erledigte Aufgabe schließt das verknüpfte GitHub-/GitLab-Issue (+ Notiz),
 * Wiedereröffnen öffnet es. Opt-in über `writeback`; der Import ist gegen
 * Export-Echos unterdrückt.
 */
class GitIssueWritebackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Queue::fake();
    }

    /** @param array<string, mixed> $settings */
    private function configureGithub(array $settings = []): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => $settings + ['api_token' => 'ghp-token', 'repo_owner' => 'acme', 'repo_name' => 'support', 'writeback' => true],
        ]);
    }

    private function linkedTask(string $pluginId, string $externalId): Task {
        $task = Task::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => TaskStatus::Open,
        ]);

        ExternalReference::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => $pluginId,
            'external_type' => 'issue',
            'referenceable_type' => $task->getMorphClass(),
            'referenceable_id' => $task->getKey(),
            'external_id' => $externalId,
            'synced_at' => now(),
        ]);

        return $task;
    }

    public function test_completing_a_linked_task_closes_the_github_issue_with_note(): void {
        $this->configureGithub();
        $task = $this->linkedTask(GithubPlugin::ID, 'acme/support#42');

        $task->forceFill(['status' => TaskStatus::Done->value])->save();

        $outbox = IntegrationOutboxEntry::query()
            ->where('operation', 'github.issue.status')
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues/42' => FakePluginHttp::response(['number' => 42, 'state' => 'closed']),
            'https://api.github.com/repos/acme/support/issues/42/comments' => FakePluginHttp::response(['id' => 1], 201),
        ]);

        $this->assertTrue(app(GithubIssueWritebackDispatcher::class)->dispatch($outbox));

        $fake->assertSent(function ($request): bool {
            if ($request->getMethod() !== 'PATCH') {
                return false;
            }
            $body = (array) json_decode((string) $request->getBody(), true);

            return str_ends_with((string) $request->getUri(), '/issues/42') && ($body['state'] ?? null) === 'closed';
        });
        $fake->assertSent(fn ($request): bool => $request->getMethod() === 'POST'
            && str_ends_with((string) $request->getUri(), '/issues/42/comments'));
    }

    public function test_reopening_a_task_reopens_the_issue_without_comment(): void {
        $this->configureGithub();
        $task = $this->linkedTask(GithubPlugin::ID, 'acme/support#42');
        $task->forceFill(['status' => TaskStatus::Done->value])->save();
        IntegrationOutboxEntry::query()->delete();

        $task->forceFill(['status' => TaskStatus::Open->value])->save();
        $outbox = IntegrationOutboxEntry::query()->where('operation', 'github.issue.status')->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://api.github.com/repos/acme/support/issues/42' => FakePluginHttp::response(['number' => 42, 'state' => 'open']),
        ]);

        $this->assertTrue(app(GithubIssueWritebackDispatcher::class)->dispatch($outbox));

        $fake->assertSent(function ($request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'PATCH' && ($body['state'] ?? null) === 'open';
        });
        $fake->assertNotSent(fn ($request): bool => $request->getMethod() === 'POST');
    }

    public function test_without_opt_in_or_link_nothing_is_enqueued(): void {
        // Opt-in fehlt:
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GithubPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'ghp-token', 'repo_owner' => 'acme', 'repo_name' => 'support'],
        ]);
        $task = $this->linkedTask(GithubPlugin::ID, 'acme/support#42');
        $task->forceFill(['status' => TaskStatus::Done->value])->save();
        $this->assertDatabaseMissing('integration_outbox', ['operation' => 'github.issue.status']);

        // Opt-in da, aber Aufgabe ohne Issue-Verknüpfung:
        PluginSetting::query()->where('plugin_id', GithubPlugin::ID)->delete();
        $this->configureGithub();
        $unlinked = Task::factory()->create(['organization_id' => $this->organization->id, 'status' => TaskStatus::Open]);
        $unlinked->forceFill(['status' => TaskStatus::Done->value])->save();
        $this->assertDatabaseMissing('integration_outbox', ['operation' => 'github.issue.status']);
    }

    public function test_import_suppression_prevents_export_echo(): void {
        $this->configureGithub();
        $task = $this->linkedTask(GithubPlugin::ID, 'acme/support#42');

        GitIssueWritebackObserver::suppressed(fn () => $task->forceFill(['status' => TaskStatus::Done->value])->save());

        $this->assertDatabaseMissing('integration_outbox', ['operation' => 'github.issue.status']);
    }

    public function test_gitlab_dispatcher_closes_issue_via_state_event(): void {
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => GitlabPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'glpat-token', 'project_id' => '123', 'writeback' => true],
        ]);
        $task = $this->linkedTask(GitlabPlugin::ID, '123#7');

        $task->forceFill(['status' => TaskStatus::Done->value])->save();
        $outbox = IntegrationOutboxEntry::query()->where('operation', 'gitlab.issue.status')->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://gitlab.com/api/v4/projects/123/issues/7' => FakePluginHttp::response(['iid' => 7, 'state' => 'closed']),
            'https://gitlab.com/api/v4/projects/123/issues/7/notes' => FakePluginHttp::response(['id' => 1], 201),
        ]);

        $this->assertTrue(app(GitlabIssueWritebackDispatcher::class)->dispatch($outbox));

        $fake->assertSent(function ($request): bool {
            if ($request->getMethod() !== 'PUT') {
                return false;
            }
            $body = (array) json_decode((string) $request->getBody(), true);

            return ($body['state_event'] ?? null) === 'close';
        });
        $fake->assertSent(fn ($request): bool => $request->getMethod() === 'POST'
            && str_ends_with((string) $request->getUri(), '/notes'));
    }
}
