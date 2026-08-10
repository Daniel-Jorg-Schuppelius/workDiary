<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryReassignService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timekeeping;

use App\Models\{Project, TimeEntry, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Massen-Neuzuordnung von Projektzeiten auf einen anderen Benutzer (MVP-508).
 *
 * Das Korrekturfenster blockiert die berechtigte Massenaktion bewusst nicht —
 * nur die harten Sperren ({@see TimeEntryEditPolicy::isHardLocked()}:
 * exportiert, Stundenzettel signiert/gesperrt). Eine gemischte Auswahl wird
 * nie teilweise gespeichert: der Preflight benennt die gesperrten Einträge,
 * {@see reassign()} bricht dann komplett ab.
 */
class TimeEntryReassignService {
    public function __construct(private readonly TimeEntryEditPolicy $editPolicy) {}

    /**
     * Lädt die Auswahl projekt-gebunden und benennt gesperrte Einträge.
     * IDs, die nicht zum Projekt gehören, fallen still heraus und werden
     * über `missing` gezählt (manipulierte oder veraltete Auswahl).
     *
     * @param  array<int, int|null>  $ids
     * @return array{entries: Collection<int, TimeEntry>, blocked: array<int, array{entry: TimeEntry, reason: string}>, missing: int}
     */
    public function preflight(Project $project, array $ids): array {
        $ids = array_values(array_unique(array_filter($ids, is_int(...))));

        /** @var Collection<int, TimeEntry> $entries */
        $entries = $project->timeEntries()
            ->with(['timesheet:id,status', 'user:id,name'])
            ->whereIn('id', $ids)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $blocked = [];
        foreach ($entries as $entry) {
            $hard = $this->editPolicy->isHardLocked($entry);
            if ($hard['locked']) {
                $blocked[] = ['entry' => $entry, 'reason' => (string) $hard['reason']];
            }
        }

        return [
            'entries' => $entries,
            'blocked' => $blocked,
            'missing' => count($ids) - $entries->count(),
        ];
    }

    /** Ziel muss ein aktiver interner Benutzer derselben Organisation sein. */
    public function isEligibleTarget(Project $project, User $target): bool {
        return (int) $target->organization_id === (int) $project->organization_id
            && ! $target->isCustomer()
            && ! $target->isDeactivated();
    }

    /**
     * Hängt die Auswahl transaktional auf den Zielbenutzer um. Die Auswahl
     * wird innerhalb der Transaktion erneut geprüft (Vorschau ist nicht
     * bindend); je Modell gespeichert, damit der saving-Hook Satz- und
     * Kostensnapshot für den neuen Benutzer neu rechnet.
     *
     * @param  array<int, int|null>  $ids
     * @return int Anzahl tatsächlich umgehängter Einträge
     *
     * @throws ValidationException bei fremden, fehlenden oder gesperrten Einträgen
     */
    public function reassign(Project $project, array $ids, User $target, User $actor): int {
        if (! $this->isEligibleTarget($project, $target)) {
            throw ValidationException::withMessages([
                'target_user_id' => (string) __('Der Zielbenutzer muss ein aktiver interner Benutzer derselben Organisation sein.'),
            ]);
        }

        $ids = array_values(array_unique(array_filter($ids, is_int(...))));

        return DB::transaction(function () use ($project, $ids, $target, $actor): int {
            /** @var Collection<int, TimeEntry> $entries */
            $entries = $project->timeEntries()
                ->with('timesheet:id,status')
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($entries->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'ids' => (string) __('Mindestens ein gewählter Zeiteintrag gehört nicht zu diesem Projekt oder existiert nicht mehr.'),
                ]);
            }

            $lockedLabels = [];
            foreach ($entries as $entry) {
                $hard = $this->editPolicy->isHardLocked($entry);
                if ($hard['locked']) {
                    $lockedLabels[] = ($entry->date?->format(\App\Support\Formats::date()) ?? '#' . $entry->id)
                        . ' (' . $this->editPolicy->reasonLabel($hard['reason']) . ')';
                }
            }
            if ($lockedLabels !== []) {
                throw ValidationException::withMessages([
                    'ids' => (string) __('Gesperrte Einträge in der Auswahl: :list — bitte Auswahl bereinigen.', [
                        'list' => implode(', ', $lockedLabels),
                    ]),
                ]);
            }

            $count = 0;
            foreach ($entries as $entry) {
                $from = (int) $entry->user_id;
                if ($from === (int) $target->id) {
                    continue;
                }

                $entry->user_id = $target->id;
                // Geladene user-Relation verwerfen: der RateCalculator liest
                // $entry->user — eine stale Relation rechnete den Kosten-
                // Snapshot sonst mit dem BISHERIGEN Benutzer.
                $entry->unsetRelation('user');
                $entry->save();
                // Ohne Lohn-/Kostendaten — nur die Zuordnung ist revisionsrelevant.
                $entry->audit('timeEntry.reassigned', [
                    'from_user_id' => $from,
                    'to_user_id' => (int) $target->id,
                    'by' => (int) $actor->id,
                    'project_id' => (int) $project->id,
                ]);
                $count++;
            }

            return $count;
        });
    }
}
