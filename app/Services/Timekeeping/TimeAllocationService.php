<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAllocationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timekeeping;

use App\Models\{TimeAllocation, TimeEntry};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MVP-514 (Feature 103): einzige Schreibstelle der Zeitaufteilung.
 * Ersetzt die Anteile eines Eintrags transaktional und validiert:
 * bekannter Dimensionstyp, Ziel existiert in der eigenen Organisation
 * (globaler Org-Scope), Summe der Anteile ≤ Dauer des Eintrags.
 *
 * Harte Sperren (exportiert, Timesheet gesperrt/signiert) blockieren
 * auch hier — Muster TimeEntryReassignService (bewusst ohne
 * Korrekturfenster: Aufteilen ändert keine Minuten des Eintrags).
 */
class TimeAllocationService {
    public function __construct(private readonly TimeEntryEditPolicy $editPolicy) {}

    /**
     * @param  list<array{type: string, id: int, minutes: int, quantity: float|null, comment: string|null}>  $rows
     * @return list<TimeAllocation>
     *
     * @throws ValidationException
     */
    public function replaceForEntry(TimeEntry $entry, array $rows): array {
        $lock = $this->editPolicy->isHardLocked($entry);
        if ($lock['locked']) {
            throw ValidationException::withMessages([
                'allocations' => __('allocation.error.locked', ['reason' => (string) $this->editPolicy->reasonLabel($lock['reason'])]),
            ]);
        }

        $total = 0;
        $normalized = [];
        foreach ($rows as $row) {
            $modelClass = TimeAllocation::TYPES[$row['type']] ?? null;
            if ($modelClass === null) {
                throw ValidationException::withMessages(['allocations' => __('allocation.error.invalid_target')]);
            }
            // Existenz org-gescoped: der globale OrganizationScope filtert fremde Orgs.
            if (! $modelClass::query()->whereKey($row['id'])->exists()) {
                throw ValidationException::withMessages(['allocations' => __('allocation.error.invalid_target')]);
            }
            if ($row['minutes'] < 1) {
                throw ValidationException::withMessages(['allocations' => __('allocation.error.minutes_min')]);
            }

            $total += $row['minutes'];
            $normalized[] = $row + ['model_class' => $modelClass];
        }

        if ($total > (int) $entry->minutes) {
            throw ValidationException::withMessages([
                'allocations' => __('allocation.error.sum_exceeds', ['sum' => $total, 'max' => (int) $entry->minutes]),
            ]);
        }

        return DB::transaction(function () use ($entry, $normalized): array {
            TimeAllocation::query()->where('time_entry_id', $entry->id)->get()->each->delete();

            $created = [];
            foreach ($normalized as $row) {
                $created[] = TimeAllocation::query()->create([
                    'organization_id' => $entry->organization_id,
                    'time_entry_id' => $entry->id,
                    'allocatable_type' => (new $row['model_class'])->getMorphClass(),
                    'allocatable_id' => $row['id'],
                    'duration_minutes' => $row['minutes'],
                    'quantity' => $row['quantity'],
                    'comment' => $row['comment'],
                ]);
            }

            return $created;
        });
    }
}
