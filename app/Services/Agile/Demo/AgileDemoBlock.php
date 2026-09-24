<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile\Demo;

use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use Illuminate\Support\Collection;

/** Agile Vorführ-Boards (Scrum + Kanban). Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class AgileDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'agile_boards' => $this->seedAgileBoards($context->projects, $context->users),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Agile Vorführ-Boards (Feature 064, P7): Projekt 1 als Scrum-Board mit
     * abgeschlossenem und aktivem Sprint samt mehrwöchiger Event-Historie
     * (Burndown/Velocity/CFD-Demos), Projekt 2 als Kanban-Board mit
     * WIP-Limit und Blockierung. Rückdatierung via Carbon::setTestNow —
     * im finally IMMER zurückgesetzt.
     *
     * @param Collection<int, Project> $projects
     * @param Collection<int, User> $users
     */
    private function seedAgileBoards(Collection $projects, Collection $users): int {
        if (! $this->moduleActive('module.agile_projects')) {
            return 0;
        }
        $scrumProject = $projects->get(0);
        if ($scrumProject === null || \App\Models\Agile\AgileBoard::query()->where('project_id', $scrumProject->id)->exists()) {
            return 0;
        }
        $kanbanProject = $projects->get(1);

        $boards = app(\App\Services\Agile\AgileBoardService::class);
        $items = app(\App\Services\Agile\AgileWorkItemService::class);
        $sprints = app(\App\Services\Agile\AgileSprintService::class);
        /** @var User $actor */
        $actor = $users->first();
        $base = \Illuminate\Support\Carbon::now()->subWeeks(4)->startOfWeek()->setTime(9, 0);
        $at = fn(int $days, int $hour = 9) => \Illuminate\Support\Carbon::setTestNow($base->copy()->addDays($days)->setTime($hour, 0));

        try {
            // ── Scrum-Board mit zwei Sprints ─────────────────────────────
            $at(0);
            $board = $boards->activate($scrumProject, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $actor);
            $inProgress = $board->columns()->where('name', 'In Arbeit')->firstOrFail();
            $done = $board->columns()->where('category', 'done')->firstOrFail();

            $stories = collect([
                ['Anmeldung mit Zwei-Faktor absichern', 5],
                ['Dashboard-Kacheln konfigurierbar machen', 3],
                ['Export nach XLSX bereitstellen', 8],
                ['Benachrichtigungen zusammenfassen', 2],
                ['Suche über alle Bereiche', 5],
                ['Mobile Ansicht für die Zeiterfassung', 3],
            ])->map(fn(array $row) => $items->create($board, [
                'title' => $row[0],
                'story_points' => $row[1],
            ], $actor))->values()->all();

            $sprintOne = $sprints->plan($board, [
                'name' => 'Sprint 1', 'goal' => 'Grundfunktionen lieferfähig machen',
                'starts_on' => $base->toDateString(), 'ends_on' => $base->copy()->addDays(11)->toDateString(),
            ], $actor);
            foreach (array_slice($stories, 0, 4) as $story) {
                $sprints->assign($sprintOne, $story, $actor);
            }
            $at(0, 10);
            $sprintOne = $sprints->start($sprintOne, $actor);

            $move = function (int $index, $column, int $day, int $hour = 9) use ($boards, $stories, $actor, $at): void {
                $at($day, $hour);
                $item = $stories[$index]->fresh() ?? $stories[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $move(0, $inProgress, 2);
            $move(0, $done, 4);
            $move(1, $inProgress, 5);
            $move(1, $done, 7);
            $move(2, $inProgress, 8);

            $at(10);
            $sprintTwo = $sprints->plan($board, [
                'name' => 'Sprint 2', 'goal' => 'Auswertung und Suche ausbauen',
                'starts_on' => $base->copy()->addDays(14)->toDateString(),
                'ends_on' => $base->copy()->addDays(31)->toDateString(),
            ], $actor);

            $at(11, 16);
            $sprints->complete($sprintOne->fresh() ?? $sprintOne, [
                (int) $stories[2]->id => (string) $sprintTwo->id, // Carry-over in Sprint 2
                (int) $stories[3]->id => 'backlog',
            ], $actor);

            $sprintTwo = $sprintTwo->fresh() ?? $sprintTwo;
            $sprints->assign($sprintTwo, $stories[4], $actor);
            $at(14);
            $sprints->start($sprintTwo, $actor);
            $move(2, $inProgress, 15);
            $move(4, $inProgress, 16);
            $at(17);
            $boards->block($stories[2]->fresh() ?? $stories[2], 'Warten auf Kundenfreigabe', $actor);
            $at(18, 14);
            $boards->unblock($stories[2]->fresh() ?? $stories[2], $actor);
            $move(2, $done, 19);
            $at(20);
            $boards->block($stories[4]->fresh() ?? $stories[4], 'Testumgebung nicht erreichbar', $actor);

            // ── Kanban-Board mit WIP-Limit ───────────────────────────────
            if ($kanbanProject === null) {
                return 1;
            }
            $at(3);
            $kanban = $boards->activate($kanbanProject, \App\Models\Agile\AgileBoard::METHOD_KANBAN, $actor);
            $kanbanProgress = $kanban->columns()->where('name', 'In Arbeit')->firstOrFail();
            $boards->saveColumn($kanban, [
                'name' => (string) $kanbanProgress->name,
                'category' => 'in_progress',
                'wip_limit' => 2,
                'position' => (int) $kanbanProgress->position,
            ], $kanbanProgress, $actor);
            $kanbanDone = $kanban->columns()->where('category', 'done')->firstOrFail();

            $tasks = collect([
                'Serverwartung Standort Nord', 'Zertifikate erneuern',
                'Backup-Konzept prüfen', 'Monitoring-Alarme entrümpeln',
            ])->map(fn(string $title) => $items->create($kanban, ['title' => $title, 'item_type' => 'task'], $actor))->values()->all();

            $kanbanMove = function (int $index, $column, int $day) use ($boards, $tasks, $actor, $at): void {
                $at($day, 11);
                $item = $tasks[$index]->fresh() ?? $tasks[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $kanbanMove(0, $kanbanProgress, 4);
            $kanbanMove(0, $kanbanDone, 6);
            $kanbanMove(1, $kanbanProgress, 7);
            $kanbanMove(2, $kanbanProgress, 12);

            return 2;
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }
}
