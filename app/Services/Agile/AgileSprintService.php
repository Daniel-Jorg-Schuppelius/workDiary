<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use App\Enums\Agile\AgileColumnCategory;
use App\Models\Agile\{AgileBoard, AgileEvent, AgileSprint, AgileSprintItem, AgileWorkItem};
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sprint-Lebenszyklus (Feature 064, MVP-142). Startregeln laufen in EINER
 * Transaktion mit lockForUpdate auf der Board-Zeile als Serialisierungs-
 * punkt (genau EIN aktiver Sprint je Board — auch bei parallelen Starts).
 * Abschluss verlangt je unerledigtem Element eine EXPLIZITE Entscheidung
 * (Backlog oder konkreter geplanter Folgesprint, kein Default). Kein
 * Wiederöffnen. Snapshots sind unveränderlich (Kennzahlen-Quelle, P5).
 */
class AgileSprintService {
    /** @param array{name: string, goal?: ?string, starts_on?: ?string, ends_on?: ?string} $attributes */
    public function plan(AgileBoard $board, array $attributes, ?User $actor = null): AgileSprint {
        $sprint = AgileSprint::query()->create([
            'organization_id' => $board->organization_id,
            'board_id' => $board->id,
            'name' => (string) $attributes['name'],
            'goal' => $attributes['goal'] ?? null,
            'starts_on' => $attributes['starts_on'] ?? null,
            'ends_on' => $attributes['ends_on'] ?? null,
            'status' => AgileSprint::STATUS_PLANNED,
            'created_by' => $actor?->id,
        ]);

        $sprint->audit('agile.sprint.planned', ['name' => $sprint->name]);

        return $sprint;
    }

    /** Element zuordnen — idempotent; nach Start als Scope-Zugang markiert. */
    public function assign(AgileSprint $sprint, AgileWorkItem $item, ?User $actor = null): AgileSprintItem {
        if ($sprint->isFinished()) {
            throw new RuntimeException((string) __('Der Sprint ist abgeschlossen.'));
        }
        if ((int) $item->board_id !== (int) $sprint->board_id) {
            throw new InvalidArgumentException('Element liegt auf einem anderen Board.');
        }

        return DB::transaction(function () use ($sprint, $item, $actor): AgileSprintItem {
            $existing = AgileSprintItem::query()
                ->where('sprint_id', $sprint->id)
                ->where('work_item_id', $item->id)
                ->first();
            if ($existing !== null) {
                return $existing; // idempotent
            }

            $assignment = AgileSprintItem::query()->create([
                'organization_id' => $sprint->organization_id,
                'sprint_id' => $sprint->id,
                'work_item_id' => $item->id,
                'position' => (int) AgileSprintItem::query()->where('sprint_id', $sprint->id)->max('position') + 1,
                'added_after_start' => $sprint->isActive(),
            ]);

            $this->recordEvent($sprint, 'sprint.item_added', $actor, $item, [
                'added_after_start' => $sprint->isActive(),
                'story_points' => $item->story_points, // Burndown-Scope-Quelle (P5)
            ]);

            return $assignment;
        });
    }

    public function remove(AgileSprint $sprint, AgileWorkItem $item, ?User $actor = null): void {
        if ($sprint->isFinished()) {
            throw new RuntimeException((string) __('Der Sprint ist abgeschlossen.'));
        }

        DB::transaction(function () use ($sprint, $item, $actor): void {
            $deleted = AgileSprintItem::query()
                ->where('sprint_id', $sprint->id)
                ->where('work_item_id', $item->id)
                ->delete();
            if ($deleted === 0) {
                return;
            }

            $this->recordEvent($sprint, 'sprint.item_removed', $actor, $item, [
                'after_start' => $sprint->isActive(),
            ]);
        });
    }

    /**
     * Start: genau 0 aktive Sprints am Board (lockForUpdate auf der
     * Board-Zeile), Ziel + gültiger Zeitraum + ≥1 Element; Commitment-
     * Snapshot = geordnete Item-Liste mit Punkten/Typ (unveränderlich).
     */
    public function start(AgileSprint $sprint, ?User $actor = null, float $capacityAdjustmentHours = 0.0, ?string $capacityAdjustmentReason = null): AgileSprint {
        return DB::transaction(function () use ($sprint, $actor, $capacityAdjustmentHours, $capacityAdjustmentReason): AgileSprint {
            // Serialisierungspunkt gegen parallele Starts.
            AgileBoard::query()->whereKey($sprint->board_id)->lockForUpdate()->firstOrFail();

            $fresh = AgileSprint::query()->whereKey($sprint->id)->firstOrFail();
            if ($fresh->status !== AgileSprint::STATUS_PLANNED) {
                throw new RuntimeException((string) __('Nur geplante Sprints können gestartet werden.'));
            }
            if (AgileSprint::query()->where('board_id', $sprint->board_id)->where('status', AgileSprint::STATUS_ACTIVE)->exists()) {
                throw new RuntimeException((string) __('Am Board läuft bereits ein aktiver Sprint.'));
            }
            if (trim((string) $fresh->goal) === '') {
                throw new RuntimeException((string) __('Der Sprint braucht ein Ziel.'));
            }
            if ($fresh->starts_on === null || $fresh->ends_on === null || $fresh->ends_on->lt($fresh->starts_on)) {
                throw new RuntimeException((string) __('Der Sprint braucht einen gültigen Zeitraum.'));
            }

            $items = $fresh->items()->with('workItem.task')->get();
            if ($items->isEmpty()) {
                throw new RuntimeException((string) __('Der Sprint braucht mindestens ein Element.'));
            }

            $commitment = $items->map(fn(AgileSprintItem $assignment): array => [
                'work_item_id' => $assignment->work_item_id,
                'title' => $assignment->workItem?->task?->title,
                'story_points' => $assignment->workItem?->story_points,
                'item_type' => $assignment->workItem?->item_type?->value,
                'position' => $assignment->position,
            ])->values()->all();

            // Kapazitäts-Snapshot (P10): Mitglieder × Arbeitszeitmodell −
            // genehmigte Urlaube ± Korrektur (Pflichtbegründung im Service).
            $capacity = app(AgileCapacityService::class)->snapshot(
                $fresh->board()->firstOrFail()->project()->firstOrFail(),
                $fresh->starts_on->copy(),
                $fresh->ends_on->copy(),
                $capacityAdjustmentHours,
                $capacityAdjustmentReason,
            );

            $fresh->update([
                'status' => AgileSprint::STATUS_ACTIVE,
                'started_at' => now(),
                'commitment_snapshot' => $commitment,
                'capacity_snapshot' => $capacity,
            ]);

            $this->recordEvent($fresh, 'sprint.started', $actor, null, [
                'items' => count($commitment),
                'points' => array_sum(array_map(fn(array $row): int => (int) ($row['story_points'] ?? 0), $commitment)),
            ]);

            return $fresh;
        });
    }

    /**
     * Abschluss: je unerledigtem Element eine explizite Entscheidung —
     * 'backlog' oder die ID eines GEPLANTEN Folgesprints desselben Boards.
     *
     * @param array<int, string> $decisions work_item_id → 'backlog'|Sprint-ID
     *
     * @throws InvalidArgumentException wenn eine Entscheidung fehlt/ungültig ist
     * @throws RuntimeException wenn der Sprint nicht aktiv ist
     */
    public function complete(AgileSprint $sprint, array $decisions, ?User $actor = null): AgileSprint {
        return DB::transaction(function () use ($sprint, $decisions, $actor): AgileSprint {
            $fresh = AgileSprint::query()->whereKey($sprint->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isActive()) {
                throw new RuntimeException((string) __('Nur aktive Sprints können abgeschlossen werden.'));
            }

            $assignments = $fresh->items()->with(['workItem.column', 'workItem.task'])->get();
            $done = $assignments->filter(fn(AgileSprintItem $a): bool => $a->workItem?->column?->category === AgileColumnCategory::Done);
            $open = $assignments->reject(fn(AgileSprintItem $a): bool => $a->workItem?->column?->category === AgileColumnCategory::Done);

            // Zwangsentscheidung: kein Default, fehlende Angaben benennen.
            $missing = $open->filter(fn(AgileSprintItem $a): bool => ! array_key_exists((int) $a->work_item_id, $decisions));
            if ($missing->isNotEmpty()) {
                throw new InvalidArgumentException((string) __(
                    'Für :count unerledigte Elemente fehlt die Entscheidung (Backlog oder Folgesprint).',
                    ['count' => $missing->count()],
                ));
            }

            $carryOver = [];
            foreach ($open as $assignment) {
                $decision = (string) $decisions[(int) $assignment->work_item_id];
                if ($decision === 'backlog') {
                    $carryOver[] = ['work_item_id' => $assignment->work_item_id, 'target' => 'backlog'];

                    continue;
                }

                $target = AgileSprint::query()
                    ->where('board_id', $fresh->board_id)
                    ->where('status', AgileSprint::STATUS_PLANNED)
                    ->find((int) $decision);
                if ($target === null) {
                    throw new InvalidArgumentException((string) __('Folgesprint ist kein geplanter Sprint dieses Boards.'));
                }
                if ($assignment->workItem !== null) {
                    $this->assign($target, $assignment->workItem, $actor);
                }
                $carryOver[] = ['work_item_id' => $assignment->work_item_id, 'target' => $target->id];
            }

            $points = fn(AgileSprintItem $a): int => (int) ($a->workItem->story_points ?? 0);
            $completion = [
                'committed_points' => array_sum(array_map(
                    fn(array $row): int => (int) ($row['story_points'] ?? 0),
                    (array) ($fresh->commitment_snapshot ?? []),
                )),
                'done_points' => (int) $done->sum($points),
                'open_points' => (int) $open->sum($points),
                'done_items' => $done->count(),
                'open_items' => $open->count(),
                'scope_added' => $assignments->where('added_after_start', true)->count(),
                'carry_over' => $carryOver,
            ];

            $fresh->update([
                'status' => AgileSprint::STATUS_COMPLETED,
                'completed_at' => now(),
                'completion_snapshot' => $completion,
            ]);

            $this->recordEvent($fresh, 'sprint.completed', $actor, null, [
                'done_points' => $completion['done_points'],
                'open_items' => $completion['open_items'],
            ]);

            return $fresh;
        });
    }

    public function cancel(AgileSprint $sprint, string $reason, ?User $actor = null): AgileSprint {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Abbruch braucht einen Grund.');
        }

        return DB::transaction(function () use ($sprint, $reason, $actor): AgileSprint {
            $fresh = AgileSprint::query()->whereKey($sprint->id)->lockForUpdate()->firstOrFail();
            if ($fresh->isFinished()) {
                throw new RuntimeException((string) __('Der Sprint ist bereits abgeschlossen.'));
            }

            $fresh->update([
                'status' => AgileSprint::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => trim($reason),
            ]);

            $this->recordEvent($fresh, 'sprint.cancelled', $actor, null, ['reason' => trim($reason)]);

            return $fresh;
        });
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(AgileSprint $sprint, string $event, ?User $actor, ?AgileWorkItem $item = null, array $payload = []): void {
        AgileEvent::record([
            'organization_id' => $sprint->organization_id,
            'board_id' => $sprint->board_id,
            'work_item_id' => $item?->id,
            'sprint_id' => $sprint->id,
            'event' => $event,
            'actor_user_id' => $actor?->id,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
