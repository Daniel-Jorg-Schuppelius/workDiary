<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use App\Enums\Agile\AgileColumnCategory;
use App\Models\Agile\AgileBoard;
use App\Models\Platform\User;
use App\Models\Project\{Project, Task};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Board-Lebenszyklus (Feature 064, MVP-139): Aktivierung je Projekt =
 * Board-Anlage mit vier Standardspalten; idempotent — Reaktivierung findet
 * das Board unverändert vor (Deaktivierung sperrt nur Navigation/Routen,
 * löscht nie). Einstellungen mit optimistischer Sperre (lock_version,
 * IdeaMap-Muster → ConflictException/409, nie Last-write-wins).
 */
class AgileBoardService {
    /** Aktiviert das agile Modul für ein Projekt (Board + Standardspalten). */
    public function activate(Project $project, string $method = AgileBoard::METHOD_KANBAN, ?User $actor = null): AgileBoard {
        $existing = AgileBoard::query()->where('project_id', $project->id)->first();
        if ($existing !== null) {
            return $existing; // idempotent — vorhandenes Board unverändert
        }

        return DB::transaction(function () use ($project, $method, $actor): AgileBoard {
            $board = AgileBoard::query()->create([
                'organization_id' => $project->organization_id,
                'project_id' => $project->id,
                'method' => $method === AgileBoard::METHOD_SCRUM ? AgileBoard::METHOD_SCRUM : AgileBoard::METHOD_KANBAN,
                'name' => (string) $project->name,
                'created_by' => $actor?->id,
            ]);

            $defaults = [
                [__('Bereit'), AgileColumnCategory::Open],
                [__('In Arbeit'), AgileColumnCategory::InProgress],
                [__('Review'), AgileColumnCategory::InProgress],
                [__('Erledigt'), AgileColumnCategory::Done],
            ];
            foreach ($defaults as $position => [$name, $category]) {
                $board->columns()->create([
                    'organization_id' => $project->organization_id,
                    'name' => $name,
                    'category' => $category->value,
                    'position' => $position + 1,
                ]);
            }

            $board->audit('agile.board.activated', ['method' => $board->method]);

            return $board->fresh(['columns']) ?? $board;
        });
    }

    /**
     * Einstellungen mit optimistischer Sperre aktualisieren.
     * Methodenwechsel ist gesperrt, solange ein aktiver Sprint läuft
     * (Guard greift, sobald die Sprint-Tabelle existiert — P4).
     *
     * @param array{name?: string, description?: ?string, dod_items?: array<int, string>, method?: string} $attributes
     *
     * @throws AgileConflictException bei lock_version-Konflikt
     */
    public function updateSettings(AgileBoard $board, array $attributes, int $expectedLockVersion, ?User $actor = null): AgileBoard {
        if (
            array_key_exists('method', $attributes)
            && $attributes['method'] !== $board->method
            && $this->hasActiveSprint($board)
        ) {
            throw new RuntimeException('Methodenwechsel ist mit aktivem Sprint nicht möglich.');
        }

        $payload = array_intersect_key($attributes, array_flip(['name', 'description', 'dod_items', 'method']));
        if (isset($payload['dod_items'])) {
            $payload['dod_items'] = JsonHelper::encode(array_values($payload['dod_items']));
        }

        $updated = AgileBoard::query()
            ->whereKey($board->id)
            ->where('lock_version', $expectedLockVersion)
            ->update([...$payload, 'lock_version' => DB::raw('lock_version + 1')]);

        if ($updated === 0) {
            throw new AgileConflictException('Das Board wurde zwischenzeitlich geändert (Konflikt).');
        }

        $fresh = $board->fresh(['columns']);
        // Query-Update feuert keine Model-Events — Audit explizit (054-Lehre).
        $fresh?->audit('agile.board.settings_updated', ['fields' => array_keys($payload), 'actor' => $actor?->id]);

        return $fresh ?? $board;
    }

    /**
     * Karte verschieben (Feature 064, P3) — in EINER Transaktion:
     * Versions-Guard (409), serverseitige WIP-Prüfung, Kriterien-/
     * DoD-Prüfung beim Zug nach done (Übersteuerung nur mit Recht +
     * Pflicht-Begründung → override.*-Events), Task-Status-Sync auf die
     * Spaltenkategorie, Ereignis column.moved.
     *
     * @param list<int> $confirmedDod bestätigte Positionen der DoD-Liste des Boards
     *
     * @throws AgileConflictException 409 bei lock_version-Konflikt
     * @throws AgileFlowException 422 bei WIP/Kriterien/DoD ohne Override
     */
    public function move(\App\Models\Agile\AgileWorkItem $item, \App\Models\Agile\AgileBoardColumn $targetColumn, int $expectedLockVersion, ?string $overrideReason = null, ?User $actor = null, bool $mayOverride = false, array $confirmedDod = []): \App\Models\Agile\AgileWorkItem {
        if ((int) $targetColumn->board_id !== (int) $item->board_id) {
            throw new \InvalidArgumentException('Zielspalte liegt auf einem anderen Board.');
        }

        return DB::transaction(function () use ($item, $targetColumn, $expectedLockVersion, $overrideReason, $actor, $mayOverride, $confirmedDod): \App\Models\Agile\AgileWorkItem {
            $board = $item->board()->firstOrFail();
            $fromColumn = $item->column;
            $overrides = [];

            // WIP-Prüfung serverseitig (Zielspalte, ohne das Item selbst).
            if ($targetColumn->wip_limit !== null && (int) $targetColumn->id !== (int) $item->column_id) {
                $count = \App\Models\Agile\AgileWorkItem::query()
                    ->where('column_id', $targetColumn->id)
                    ->where('id', '!=', $item->id)
                    ->count();
                if ($count >= $targetColumn->wip_limit) {
                    if (! $mayOverride || trim((string) $overrideReason) === '') {
                        throw new AgileFlowException('wip', (string) __('WIP-Limit der Spalte erreicht (:limit).', ['limit' => $targetColumn->wip_limit]));
                    }
                    $overrides['override.wip'] = [];
                }
            }

            // Zug nach done: unerfüllte Kriterien/DoD nur mit Override.
            if ($targetColumn->category === AgileColumnCategory::Done) {
                $openCriteria = \App\Models\Agile\AgileAcceptanceCriterion::query()
                    ->where('work_item_id', $item->id)
                    ->whereNull('checked_at')
                    ->count();
                if ($openCriteria > 0) {
                    if (! $mayOverride || trim((string) $overrideReason) === '') {
                        throw new AgileFlowException('criteria', (string) __(':count Akzeptanzkriterien sind noch offen.', ['count' => $openCriteria]));
                    }
                    $overrides['override.criteria'] = [];
                }
                // DoD gilt beim Eintritt in den Abschluss, nicht beim Umsortieren innerhalb.
                $unconfirmed = $fromColumn?->category === AgileColumnCategory::Done ? [] : $this->unconfirmedDod($board, $confirmedDod);
                if ($unconfirmed !== []) {
                    if (! $mayOverride || trim((string) $overrideReason) === '') {
                        throw new AgileFlowException('dod', (string) __(':count Punkte der Definition of Done sind nicht bestätigt.', ['count' => count($unconfirmed)]));
                    }
                    $overrides['override.dod'] = ['unconfirmed' => $unconfirmed];
                }
            }

            $updated = \App\Models\Agile\AgileWorkItem::query()
                ->whereKey($item->id)
                ->where('lock_version', $expectedLockVersion)
                ->update(['column_id' => $targetColumn->id, 'lock_version' => DB::raw('lock_version + 1')]);
            if ($updated === 0) {
                throw new AgileConflictException('Das Element wurde zwischenzeitlich geändert (Konflikt).');
            }

            // Task-Status-Sync: Spaltenkategorie → Task-Status (integrationsstabil).
            $task = $item->task()->first();
            if ($task !== null && (string) $task->getAttribute('status')?->value !== $targetColumn->category->value) {
                $task->forceFill(['status' => $targetColumn->category->value])->save();
            }

            foreach ($overrides as $overrideEvent => $detail) {
                $this->recordEvent($board, $overrideEvent, $actor, $item, ['reason' => (string) $overrideReason, ...$detail]);
            }
            $this->recordEvent($board, 'column.moved', $actor, $item, [
                'from' => $fromColumn?->id,
                'to' => $targetColumn->id,
            ]);

            return $item->fresh(['column', 'task']) ?? $item;
        });
    }

    /**
     * Task→Board-Nachzieh-Sync (Feature 064, P3): Statuswechsel außerhalb des
     * Boards schiebt das Work-Item in die erste Spalte der Zielkategorie.
     * Läuft BEWUSST an {@see move()} vorbei: Der Statuswechsel ist bereits
     * vollzogen, hier wird nur gespiegelt — keine WIP-Prüfung, keine
     * optimistische Sperre. Offene Kriterien und DoD beim Zug nach „erledigt“
     * stehen als Abweichung im Board-Journal (MVP-1628). Kein Task-Write →
     * keine Endlos-Schleife mit move(). Aufrufer: {@see \App\Observers\TaskObserver::saved()}.
     */
    public function syncColumnFromTask(Task $task): void {
        if (! $task->wasChanged('status')) {
            return;
        }
        $item = \App\Models\Agile\AgileWorkItem::query()->where('task_id', $task->id)->first();
        if ($item === null) {
            return;
        }
        $rawStatus = $task->getAttribute('status');
        $status = (string) ($rawStatus instanceof \BackedEnum ? $rawStatus->value : $rawStatus);
        $currentCategory = $item->column?->category?->value;
        if ($currentCategory === $status) {
            return;
        }
        $target = \App\Models\Agile\AgileBoardColumn::query()
            ->where('board_id', $item->board_id)
            ->where('category', $status)
            ->orderBy('position')
            ->first();
        if ($target === null || (int) $target->id === (int) $item->column_id) {
            return;
        }
        $from = $item->column_id;
        \App\Models\Agile\AgileWorkItem::query()->whereKey($item->id)
            ->update(['column_id' => $target->id, 'lock_version' => DB::raw('lock_version + 1')]);
        $extra = ['organization_id' => $item->organization_id, 'board_id' => $item->board_id, 'work_item_id' => $item->id];
        \App\Models\Agile\AgileEvent::log(null, 'column.moved', ['from' => $from, 'to' => $target->id, 'origin' => 'task_sync'], null, extra: $extra);

        // Außerhalb des Boards erledigt (MVP-1628): der Zug wird gespiegelt, offene Kriterien und die
        // unbestätigte DoD aber als Abweichung festgehalten — wie eine Übersteuerung in move().
        if ($target->category === AgileColumnCategory::Done && $item->column?->category !== AgileColumnCategory::Done) {
            $board = $item->board()->first();
            $openCriteria = \App\Models\Agile\AgileAcceptanceCriterion::query()->where('work_item_id', $item->id)->whereNull('checked_at')->count();
            if ($openCriteria > 0) {
                \App\Models\Agile\AgileEvent::log(null, 'override.criteria', ['reason' => null, 'origin' => 'task_sync', 'open' => $openCriteria], null, extra: $extra);
            }
            $unconfirmed = $board instanceof AgileBoard ? $this->unconfirmedDod($board, []) : [];
            if ($unconfirmed !== []) {
                \App\Models\Agile\AgileEvent::log(null, 'override.dod', ['reason' => null, 'origin' => 'task_sync', 'unconfirmed' => $unconfirmed], null, extra: $extra);
            }
        }
    }

    /** Blockieren mit Pflichtgrund (Karte bleibt in der Spalte). */
    public function block(\App\Models\Agile\AgileWorkItem $item, string $reason, ?User $actor = null): \App\Models\Agile\AgileWorkItem {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Blockierung braucht einen Grund.');
        }

        return DB::transaction(function () use ($item, $reason, $actor): \App\Models\Agile\AgileWorkItem {
            $item->update(['blocked_at' => now(), 'blocked_reason' => trim($reason)]);
            $this->recordEvent($item->board()->firstOrFail(), 'item.blocked', $actor, $item, ['reason' => trim($reason)]);

            return $item;
        });
    }

    public function unblock(\App\Models\Agile\AgileWorkItem $item, ?User $actor = null): \App\Models\Agile\AgileWorkItem {
        return DB::transaction(function () use ($item, $actor): \App\Models\Agile\AgileWorkItem {
            $item->update(['blocked_at' => null, 'blocked_reason' => null]);
            $this->recordEvent($item->board()->firstOrFail(), 'item.unblocked', $actor, $item, []);

            return $item;
        });
    }

    /**
     * Spalte anlegen/ändern (Feature 064, P3): Name, Kategorie, WIP-Limit,
     * Position (Umsortierung deterministisch neu durchnummeriert). Guard:
     * Ein Kategorie-Wechsel darf die letzte open-/done-Spalte nicht kippen.
     *
     * @param array{name: string, category: string, wip_limit?: int|null, position?: int|null, report_role?: string|null} $attributes
     *
     * @throws RuntimeException wenn der Guard greift (→ 422/Flash)
     */
    public function saveColumn(AgileBoard $board, array $attributes, ?\App\Models\Agile\AgileBoardColumn $column = null, ?User $actor = null): \App\Models\Agile\AgileBoardColumn {
        $category = AgileColumnCategory::from((string) $attributes['category']);

        return DB::transaction(function () use ($board, $attributes, $column, $category, $actor): \App\Models\Agile\AgileBoardColumn {
            if ($column !== null && $column->category !== $category) {
                $this->assertCategoryStaysCovered($board, $column);
            }

            $position = isset($attributes['position'])
                ? max(1, (int) $attributes['position'])
                : ($column->position ?? ((int) $board->columns()->max('position') + 1));

            $payload = [
                'name' => (string) $attributes['name'],
                'category' => $category->value,
                'wip_limit' => isset($attributes['wip_limit']) ? (int) $attributes['wip_limit'] : null,
                // Berichtsrolle (P9): working|waiting|null — Flow-Effizienz
                // rechnet nur bei vollständiger Klassifikation.
                'report_role' => in_array($attributes['report_role'] ?? null, ['working', 'waiting'], true)
                    ? $attributes['report_role']
                    : null,
            ];

            if ($column === null) {
                // Anlage immer jenseits des Unique-Index — resequence reiht ein.
                $column = $board->columns()->create([
                    ...$payload,
                    'organization_id' => $board->organization_id,
                    'position' => (int) $board->columns()->max('position') + 1001,
                ]);
            } else {
                $column->update($payload);
            }

            $this->resequenceColumns($board, $column, $position);
            $board->audit('agile.board.column_saved', ['column' => $column->id, 'actor' => $actor?->id]);

            return $column->fresh() ?? $column;
        });
    }

    /** Löschen nur leerer Spalten; open/done müssen abgedeckt bleiben. */
    public function deleteColumn(\App\Models\Agile\AgileBoardColumn $column, ?User $actor = null): void {
        DB::transaction(function () use ($column, $actor): void {
            if (\App\Models\Agile\AgileWorkItem::query()->where('column_id', $column->id)->exists()) {
                throw new RuntimeException((string) __('Nur leere Spalten können gelöscht werden.'));
            }

            $board = $column->board()->firstOrFail();
            $this->assertCategoryStaysCovered($board, $column);
            $column->delete();

            foreach ($board->columns()->orderBy('position')->get()->values() as $index => $remaining) {
                if ((int) $remaining->position !== $index + 1) {
                    \App\Models\Agile\AgileBoardColumn::query()->whereKey($remaining->id)->update(['position' => $index + 1]);
                }
            }

            $board->audit('agile.board.column_deleted', ['column' => $column->id, 'actor' => $actor?->id]);
        });
    }

    /** Board braucht mindestens je eine Spalte „offen" und „erledigt". */
    private function assertCategoryStaysCovered(AgileBoard $board, \App\Models\Agile\AgileBoardColumn $column): void {
        if (! in_array($column->category, [AgileColumnCategory::Open, AgileColumnCategory::Done], true)) {
            return;
        }

        $covered = $board->columns()
            ->whereKeyNot($column->id)
            ->where('category', $column->category->value)
            ->exists();
        if (! $covered) {
            throw new RuntimeException((string) __('Das Board braucht mindestens je eine Spalte „offen" und „erledigt".'));
        }
    }

    /** Zielposition einreihen, Rest deterministisch durchnummerieren. */
    private function resequenceColumns(AgileBoard $board, \App\Models\Agile\AgileBoardColumn $moved, int $target): void {
        $ordered = $board->columns()->whereKeyNot($moved->id)->orderBy('position')->get()->values()->all();
        array_splice($ordered, min(max($target - 1, 0), count($ordered)), 0, [$moved]);
        // Zweiphasig wegen Unique (board_id, position): erst Offset, dann final.
        foreach ($ordered as $index => $col) {
            \App\Models\Agile\AgileBoardColumn::query()->whereKey($col->id)->update(['position' => $index + 1001]);
        }
        foreach ($ordered as $index => $col) {
            \App\Models\Agile\AgileBoardColumn::query()->whereKey($col->id)->update(['position' => $index + 1]);
        }
    }

    /**
     * @param list<int> $confirmed
     *
     * @return list<string> Texte der nicht bestätigten DoD-Punkte
     */
    public function unconfirmedDod(AgileBoard $board, array $confirmed): array {
        $open = [];
        foreach (array_values((array) ($board->dod_items ?? [])) as $position => $text) {
            if (! in_array($position, $confirmed, true)) {
                $open[] = (string) $text;
            }
        }

        return $open;
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(AgileBoard $board, string $event, ?User $actor, ?\App\Models\Agile\AgileWorkItem $item = null, array $payload = []): void {
        $board->record($event, $payload, $actor, extra: ['work_item_id' => $item?->id]);
    }

    private function hasActiveSprint(AgileBoard $board): bool {
        if (! \Illuminate\Support\Facades\Schema::hasTable('agile_sprints')) {
            return false;
        }

        return DB::table('agile_sprints')
            ->where('board_id', $board->id)
            ->where('status', 'active')
            ->exists();
    }
}
