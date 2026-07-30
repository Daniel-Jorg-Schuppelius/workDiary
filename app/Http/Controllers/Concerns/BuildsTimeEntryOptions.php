<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuildsTimeEntryOptions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Enums\Task\TaskStatus;
use App\Models\{DiaryEntry, Project, Task};
use Illuminate\Support\Collection;

/**
 * Gemeinsame Options-Quellen für die Zeiterfassung (Erfassungs-Dialog und
 * Eingabeleiste auf „Heute"): projektbezogene Aufgaben und Aufträge.
 */
trait BuildsTimeEntryOptions {
    /**
     * Offene Aufgaben des Projekts als Auswahl für die Erfassung.
     *
     * @return Collection<int, Task>
     */
    protected function taskOptions(Project $project): Collection {
        return $project->tasks()
            ->where('status', '!=', TaskStatus::Done)
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    /**
     * Aufträge, die für die Verbuchung von Stunden auf dieses Projekt sinnvoll
     * angeboten werden: alle nicht-archivierten offenen Aufträge auf dem Projekt
     * selbst plus jeder Auftrag, der bereits Stunden auf dieses Projekt gebucht
     * hat. Der aktuell verknüpfte Auftrag wird immer mit aufgenommen, damit der
     * Edit-Dialog beim Bearbeiten nichts verliert.
     *
     * @return Collection<int, DiaryEntry>
     */
    protected function diaryOptions(Project $project, ?int $currentId = null): Collection {
        return DiaryEntry::query()
            ->select(['id', 'title', 'content', 'mode', 'status', 'project_id'])
            ->where('is_archived', false)
            ->where(function ($q) use ($project, $currentId): void {
                $q->where('project_id', $project->id)
                    ->orWhereIn('id', function ($sub) use ($project): void {
                        $sub->select('diary_entry_id')
                            ->from('time_entries')
                            ->where('project_id', $project->id)
                            ->whereNotNull('diary_entry_id');
                    });
                if ($currentId !== null) {
                    $q->orWhere('id', $currentId);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
    }
}
