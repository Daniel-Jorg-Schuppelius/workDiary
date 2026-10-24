<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JiraImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\TaskImport;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Task\TaskStatus;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Project\{Project, Task};
use App\Plugins\Jira\JiraPlugin;
use App\Plugins\Jira\Models\JiraProjectLink;
use App\Plugins\Jira\Services\{AdfText, JiraImportService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Jira-Import (MVP-1306): Enhanced Search über mehrere Seiten, Identität über
 * die Issue-ID auch nach Schlüsselwechsel, Statuskategorien statt Namen,
 * ADF-Text, Fälligkeit, Wiedereröffnung ohne Überschreiben lokaler
 * Abschlüsse, Abbruch ohne voreiligen Aufholpunkt, „nicht mehr sichtbar“.
 */
class JiraImportTest extends TestCase {
    use RefreshDatabase;
    use TaskImportFixtures;
    use WithOrganization;

    private Project $project;

    private JiraProjectLink $link;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->configureTaskImportApps();
        $this->enableTaskImportPlugin(JiraPlugin::ID);
        $this->jiraConnection();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
        $this->link = $this->jiraLink($this->project);
    }

    /** @return array{created: int, updated: int, unchanged: int, conflicts: int, inbox: int, failed: int} */
    private function sync(bool $full = false): array {
        return app(JiraImportService::class)->run($this->organization, $full);
    }

    private function task(string $issueId): ?Task {
        $task = ExternalReference::query()->forPlugin($this->organization->id, JiraPlugin::ID, JiraPlugin::EXT_TYPE_ISSUE)
            ->forExternalId(self::JIRA_CLOUD . ':' . $issueId)->first()?->referenceable;

        return $task instanceof Task ? $task : null;
    }

    /** @param list<array<string, mixed>> $issues */
    private function serve(array $issues): FakePluginHttp {
        return FakePluginHttp::fake([$this->jiraSearchUrl() => FakePluginHttp::response($this->jiraPage($issues))]);
    }

    public function test_first_run_reads_open_issues_over_two_pages_and_skips_done_history(): void {
        $http = FakePluginHttp::fake([$this->jiraSearchUrl() => function (RequestInterface $request) {
            parse_str($request->getUri()->getQuery(), $query);

            return FakePluginHttp::response(($query['nextPageToken'] ?? null) === 'page-2'
                ? $this->jiraPage([$this->jiraIssue('10003', 'MUS-3', 'Alt erledigt', 'done'), $this->jiraIssue('10009', 'FRE-1', 'Fremdes Projekt', 'new', [], '99999')])
                : $this->jiraPage([
                    $this->jiraIssue('10001', 'MUS-1', 'Login reparieren', 'new', ['duedate' => '2026-11-03']),
                    $this->jiraIssue('10002', 'MUS-2', 'API dokumentieren', 'indeterminate'),
                ], 'page-2'));
        }]);

        $counters = $this->sync();

        $this->assertSame(2, $counters['created']);
        $first = $this->task('10001');
        $this->assertNotNull($first);
        $this->assertSame('Login reparieren', $first->title);
        $this->assertSame('Beschreibung MUS-1', $first->description);
        $this->assertSame('2026-11-03', $first->due_date?->toDateString());
        $this->assertSame((int) $this->project->id, (int) $first->project_id);
        $this->assertSame(TaskStatus::InProgress, $this->task('10002')?->status);
        $this->assertNull($this->task('10003'), 'Erledigte Altvorgänge werden nicht angelegt.');
        $this->assertNull($this->task('10009'), 'Antworten werden auf das zugeordnete Projekt begrenzt.');
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['jql'] ?? '') === 'project = 10000 AND statusCategory != Done ORDER BY created ASC';
        });
        $this->link->refresh();
        $this->assertTrue($this->link->is_last_run_complete);
        $this->assertNotNull($this->link->checkpoint_at);
    }

    public function test_key_change_keeps_the_task_and_later_runs_ask_only_for_changes(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'Login reparieren')]);
        $this->sync();
        $taskId = $this->task('10001')?->id;

        $this->travel(30)->minutes();
        $http = $this->serve([$this->jiraIssue('10001', 'NEU-7', 'Login reparieren (verschoben)')]);
        $counters = $this->sync();

        $this->assertSame(1, $counters['updated']);
        $this->assertSame($taskId, $this->task('10001')?->id);
        $this->assertSame(1, Task::query()->count());
        $this->assertSame('Login reparieren (verschoben)', $this->task('10001')?->title);
        $http->assertSent(function (RequestInterface $request): bool {
            parse_str($request->getUri()->getQuery(), $query);

            // 30 Minuten seit dem Aufholpunkt plus 10 Minuten Überlappung, relativ statt Datum.
            return preg_match('/^project = 10000 AND updated >= -4\dm ORDER BY created ASC$/', (string) ($query['jql'] ?? '')) === 1;
        });
    }

    public function test_status_categories_map_and_unknown_category_becomes_a_case(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A'), $this->jiraIssue('10002', 'MUS-2', 'B', 'mystery')]);
        $this->sync();
        $this->assertSame(TaskStatus::Open, $this->task('10001')?->status);
        $this->assertNull($this->task('10002'), 'Ohne bekannte Kategorie keine Neuanlage.');
        $this->assertTrue(IntegrationInboxItem::query()->where('dedupe_key', 'issue:' . self::JIRA_CLOUD . ':10002:unknown_status')->exists());

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A', 'mystery')]);
        $this->sync();
        $this->assertSame(TaskStatus::Open, $this->task('10001')?->status, 'Ein unbekannter Status lässt den bekannten stehen.');
    }

    public function test_local_completion_survives_remote_progress_but_a_remote_completion_can_be_reopened(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->sync();
        $task = $this->task('10001');
        $task?->forceFill(['status' => TaskStatus::Done])->save();

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A', 'indeterminate')]);
        $this->sync();
        $this->assertSame(TaskStatus::Done, $task?->fresh()?->status, 'Lokaler Abschluss bleibt gegen „in Bearbeitung“.');
        $this->assertSame(0, IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->count(), 'Ohne Konfliktfall.');

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A', 'done')]);
        $this->sync();
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A', 'indeterminate')]);
        $this->sync();
        $this->assertSame(TaskStatus::InProgress, $task?->fresh()?->status, 'Den Abschluss der Quelle darf die Quelle zurücknehmen.');
    }

    public function test_failed_second_page_keeps_the_checkpoint(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->sync();
        $checkpoint = $this->link->fresh()?->checkpoint_at;

        $this->travel(20)->minutes();
        FakePluginHttp::fake([$this->jiraSearchUrl() => function (RequestInterface $request) {
            parse_str($request->getUri()->getQuery(), $query);

            return ($query['nextPageToken'] ?? null) === null
                ? FakePluginHttp::response($this->jiraPage([$this->jiraIssue('10002', 'MUS-2', 'B')], 'p2'))
                : FakePluginHttp::response(['errorMessages' => ['kaputt']], 500);
        }]);
        $this->sync();

        $link = $this->link->fresh();
        $this->assertFalse($link?->is_last_run_complete);
        $this->assertEquals($checkpoint, $link?->checkpoint_at, 'Kein Aufholpunkt über einen Abbruch hinweg.');
        $this->assertStringContainsString('500', (string) $link?->last_error);
        $this->assertNotNull($this->task('10002'), 'Gelesene Seiten werden trotzdem übernommen.');
    }

    public function test_page_limit_is_an_incomplete_run(): void {
        config(['plugins.jira.max_pages' => 1]);
        FakePluginHttp::fake([$this->jiraSearchUrl() => FakePluginHttp::response($this->jiraPage([$this->jiraIssue('10001', 'MUS-1', 'A')], 'more'))]);

        $this->sync();

        $this->assertFalse($this->link->fresh()?->is_last_run_complete);
        $this->assertNull($this->link->fresh()?->checkpoint_at);
    }

    public function test_full_run_marks_linked_issues_that_are_no_longer_visible_without_touching_them(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A'), $this->jiraIssue('10002', 'MUS-2', 'B')]);
        $this->sync();

        $this->serve([$this->jiraIssue('10002', 'MUS-2', 'B')]);
        $this->sync();
        $this->assertSame(0, IntegrationInboxItem::query()->count(), 'Ein Delta ist keine Abwesenheitsaussage.');

        $counters = $this->sync(full: true);

        $this->assertSame(1, $counters['inbox']);
        $item = IntegrationInboxItem::query()->sole();
        $this->assertSame(self::JIRA_CLOUD . ':10001', $item->external_id);
        $this->assertTrue((bool) ($item->remote_snapshot['remote_missing'] ?? false));
        $this->assertSame('integration.task_import.case.remote_deleted', $item->display_subtitle);
        $this->assertSame(TaskStatus::Open, $this->task('10001')?->status);
        $this->assertNotNull($this->link->fresh()?->last_full_sync_at);
    }

    public function test_incomplete_description_is_a_case_and_keeps_the_existing_text(): void {
        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A')]);
        $this->sync();

        $this->serve([$this->jiraIssue('10001', 'MUS-1', 'A', 'new', ['description' => ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Neu']]],
            ['type' => 'mediaSingle', 'content' => [['type' => 'media', 'attrs' => ['id' => 'x']]]],
        ]]])]);
        $this->sync();

        $this->assertSame('Beschreibung MUS-1', $this->task('10001')?->description);
        $this->assertTrue(IntegrationInboxItem::query()->where('dedupe_key', 'issue:' . self::JIRA_CLOUD . ':10001:description_incomplete')
            ->where('status', IntegrationInboxStatus::Open)->exists());
    }

    public function test_adf_projection_keeps_text_lists_and_breaks(): void {
        $result = AdfText::convert(['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Ziel']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Zeile 1'], ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'Zeile 2 '],
                ['type' => 'mention', 'attrs' => ['text' => '@Erika']],
            ]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'eins']]]]],
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'zwei']]]]],
            ]],
            ['type' => 'orderedList', 'content' => [
                ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>alert(1)</script>']]]]],
            ]],
        ]]);

        $this->assertTrue($result['complete']);
        $this->assertSame("Ziel\n\nZeile 1\nZeile 2 @Erika\n\n- eins\n- zwei\n\n1. <script>alert(1)</script>", $result['text']);

        $deep = ['type' => 'paragraph', 'content' => []];
        for ($i = 0; $i < 40; $i++) {
            $deep = ['type' => 'blockquote', 'content' => [$deep]];
        }
        $this->assertFalse(AdfText::convert(['type' => 'doc', 'content' => [$deep]])['complete'], 'Tiefe wird vor dem Abstieg begrenzt.');
        $this->assertFalse(AdfText::convert(['type' => 'nonsense'])['complete']);
    }
}
