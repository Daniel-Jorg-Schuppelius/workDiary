<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileWorkItemService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use App\Enums\Agile\AgileItemType;
use App\Models\Agile\{AgileBoard, AgileEvent, AgileWorkItem};
use App\Models\{Task, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Produkt-Backlog (Feature 064, MVP-140): Arbeitselemente entstehen durch
 * Adoption bestehender Projektaufgaben (idempotent, nie Dubletten) oder
 * durch Neuanlage (Task + Work-Item in EINER Transaktion — kein zweites
 * Aufgabenmodell). Rang in 1000er-Schritten mit deterministischer
 * Neuverteilung; jede Mutation schreibt ihr Ereignis in derselben
 * Transaktion (fester Katalog).
 */
class AgileWorkItemService {
    private const RANK_STEP = 1000;

    /** Bestehende Projektaufgabe ins Backlog übernehmen (idempotent). */
    public function adopt(AgileBoard $board, Task $task, ?User $actor = null): AgileWorkItem {
        if ((int) $task->project_id !== (int) $board->project_id) {
            throw new InvalidArgumentException('Aufgabe gehört nicht zum Projekt des Boards.');
        }

        $existing = AgileWorkItem::query()->where('task_id', $task->id)->first();
        if ($existing !== null) {
            return $existing; // idempotent — keine Dublette (DoD)
        }

        return DB::transaction(function () use ($board, $task, $actor): AgileWorkItem {
            $item = AgileWorkItem::query()->create([
                'organization_id' => $board->organization_id,
                'board_id' => $board->id,
                'task_id' => $task->id,
                'item_type' => AgileItemType::Task->value,
                'backlog_rank' => $this->nextRank($board),
            ]);

            $this->recordEvent($board, 'backlog.added', $actor, $item, ['task_id' => $task->id, 'via' => 'adopt']);

            return $item;
        });
    }

    /**
     * Neues Arbeitselement: Task UND Work-Item in einer Transaktion
     * (Task-Erzeugung nach NodeConversionService-Konvention).
     *
     * @param array{title: string, description?: ?string, item_type?: string, story_points?: ?int} $attributes
     */
    public function create(AgileBoard $board, array $attributes, User $actor): AgileWorkItem {
        return DB::transaction(function () use ($board, $attributes, $actor): AgileWorkItem {
            $task = Task::query()->create([
                'organization_id' => $board->organization_id,
                'project_id' => $board->project_id,
                'is_global' => false,
                'title' => (string) $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'created_by' => $actor->id,
            ]);

            $item = AgileWorkItem::query()->create([
                'organization_id' => $board->organization_id,
                'board_id' => $board->id,
                'task_id' => $task->id,
                'item_type' => (AgileItemType::tryFrom((string) ($attributes['item_type'] ?? '')) ?? AgileItemType::Task)->value,
                'backlog_rank' => $this->nextRank($board),
                'story_points' => $attributes['story_points'] ?? null,
            ]);

            $this->recordEvent($board, 'backlog.added', $actor, $item, ['task_id' => $task->id, 'via' => 'create']);

            return $item;
        });
    }

    /**
     * Rang-Verschiebung: Item hinter $afterItem einsortieren (null = an die
     * Spitze). 1000er-Schritte; bei Kollision deterministische Neuverteilung.
     * Optimistische Sperre über lock_version (409 im Controller).
     */
    public function rerank(AgileWorkItem $item, ?AgileWorkItem $afterItem, int $expectedLockVersion, ?User $actor = null): AgileWorkItem {
        if ($afterItem !== null && (int) $afterItem->board_id !== (int) $item->board_id) {
            throw new InvalidArgumentException('Zielposition liegt auf einem anderen Board.');
        }

        return DB::transaction(function () use ($item, $afterItem, $expectedLockVersion, $actor): AgileWorkItem {
            $board = $item->board()->firstOrFail();

            $newRank = $this->rankBetween($board, $afterItem);
            if ($newRank === null) {
                $this->redistribute($board);
                $newRank = $this->rankBetween($board, $afterItem?->fresh());
                if ($newRank === null) {
                    throw new RuntimeException('Rang konnte nicht bestimmt werden.');
                }
            }

            $updated = AgileWorkItem::query()
                ->whereKey($item->id)
                ->where('lock_version', $expectedLockVersion)
                ->update(['backlog_rank' => $newRank, 'lock_version' => DB::raw('lock_version + 1')]);
            if ($updated === 0) {
                throw new AgileConflictException('Das Element wurde zwischenzeitlich geändert (Konflikt).');
            }

            $fresh = $item->fresh();
            $this->recordEvent($board, 'backlog.reranked', $actor, $fresh, [
                'from' => $item->backlog_rank,
                'to' => $newRank,
            ]);

            return $fresh ?? $item;
        });
    }

    public function setPoints(AgileWorkItem $item, ?int $points, ?User $actor = null): AgileWorkItem {
        return DB::transaction(function () use ($item, $points, $actor): AgileWorkItem {
            $from = $item->story_points;
            $item->update(['story_points' => $points]);
            $this->recordEvent($item->board()->firstOrFail(), 'points.changed', $actor, $item, ['from' => $from, 'to' => $points]);

            return $item;
        });
    }

    public function setType(AgileWorkItem $item, AgileItemType $type): AgileWorkItem {
        $item->update(['item_type' => $type->value]);

        return $item;
    }

    /**
     * Epic-Zuordnung (Vollaudit 2026-07, M25): nutzt die vorhandene
     * Eltern-Kind-Beziehung der Aufgaben (task.parent_task_id) — kein zweites
     * Hierarchiemodell. Kind-Items nur im selben Board (Plan-Regel P2), keine
     * Epic-Verschachtelung. `null` löst die Zuordnung.
     */
    public function assignEpic(AgileWorkItem $item, ?AgileWorkItem $epic, ?User $actor = null): AgileWorkItem {
        if ($item->item_type === AgileItemType::Epic) {
            throw new InvalidArgumentException('Epics können keinem Epic zugeordnet werden.');
        }
        if ($epic !== null) {
            if ($epic->item_type !== AgileItemType::Epic) {
                throw new InvalidArgumentException('Zielelement ist kein Epic.');
            }
            if ((int) $epic->board_id !== (int) $item->board_id) {
                throw new InvalidArgumentException('Epic und Kind müssen auf demselben Board liegen.');
            }
        }

        return DB::transaction(function () use ($item, $epic, $actor): AgileWorkItem {
            $task = $item->task()->firstOrFail();
            $from = $task->parent_task_id;
            $task->update(['parent_task_id' => $epic?->task_id]);

            $this->recordEvent($item->board()->firstOrFail(), 'epic.assigned', $actor, $item, [
                'from_task_id' => $from,
                'to_task_id' => $epic?->task_id,
            ]);

            return $item;
        });
    }

    /**
     * Epic-Fortschritt je Board (Vollaudit 2026-07, M25 / MVP-146): Kinder über
     * task.parent_task_id, „erledigt" = aktuelle Spalte hat Kategorie done.
     *
     * @return list<array{epic: AgileWorkItem, total: int, done: int, points_total: int, points_done: int}>
     */
    public function epicProgress(AgileBoard $board): array {
        $epics = AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->where('item_type', AgileItemType::Epic->value)
            ->with('task:id,title')
            ->orderBy('backlog_rank')
            ->get();
        if ($epics->isEmpty()) {
            return [];
        }

        $children = AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->whereHas('task', fn($q) => $q->whereIn('parent_task_id', $epics->pluck('task_id')))
            ->with(['task:id,parent_task_id', 'column:id,category'])
            ->get()
            ->groupBy(fn(AgileWorkItem $i): int => (int) $i->task?->parent_task_id);

        $progress = [];
        foreach ($epics as $epic) {
            $kids = $children->get((int) $epic->task_id, collect());
            $done = $kids->filter(fn(AgileWorkItem $i): bool => $i->column?->category === \App\Enums\Agile\AgileColumnCategory::Done);
            $progress[] = [
                'epic' => $epic,
                'total' => $kids->count(),
                'done' => $done->count(),
                'points_total' => (int) $kids->sum(fn(AgileWorkItem $i): int => (int) ($i->story_points ?? 0)),
                'points_done' => (int) $done->sum(fn(AgileWorkItem $i): int => (int) ($i->story_points ?? 0)),
            ];
        }

        return $progress;
    }

    /** Nächster freier Rang am Backlog-Ende. */
    private function nextRank(AgileBoard $board): int {
        $max = (int) AgileWorkItem::query()->where('board_id', $board->id)->max('backlog_rank');

        return $max + self::RANK_STEP;
    }

    /** Rang zwischen $afterItem und dessen Nachfolger; null = kein Platz. */
    private function rankBetween(AgileBoard $board, ?AgileWorkItem $afterItem): ?int {
        $lower = $afterItem !== null ? (int) $afterItem->backlog_rank : 0;
        $upper = (int) (AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->where('backlog_rank', '>', $lower)
            ->min('backlog_rank') ?: ($lower + 2 * self::RANK_STEP));

        $candidate = intdiv($lower + $upper, 2);

        return ($candidate > $lower && $candidate < $upper) ? $candidate : null;
    }

    /** Deterministische Neuverteilung in 1000er-Schritten (Rangfolge bleibt). */
    private function redistribute(AgileBoard $board): void {
        $rank = self::RANK_STEP;
        foreach (AgileWorkItem::query()->where('board_id', $board->id)->orderBy('backlog_rank')->orderBy('id')->get() as $item) {
            AgileWorkItem::query()->whereKey($item->id)->update(['backlog_rank' => $rank]);
            $rank += self::RANK_STEP;
        }
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(AgileBoard $board, string $event, ?User $actor, ?AgileWorkItem $item = null, array $payload = []): void {
        AgileEvent::record([
            'organization_id' => $board->organization_id,
            'board_id' => $board->id,
            'work_item_id' => $item?->id,
            'event' => $event,
            'actor_user_id' => $actor?->id,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
