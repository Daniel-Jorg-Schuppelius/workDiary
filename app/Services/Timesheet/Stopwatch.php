<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Stopwatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Timesheet;

use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use RuntimeException;

class Stopwatch {
    /**
     * Liefert den aktuell laufenden Eintrag des Users (started_at gesetzt, ended_at null).
     */
    public function current(User $user): ?TimeEntry {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    public function start(User $user, Timesheet $timesheet, ?int $taskId = null, ?string $description = null): TimeEntry {
        if ($this->current($user)) {
            throw new RuntimeException('A running entry already exists.');
        }
        if ($timesheet->isSigned()) {
            throw new RuntimeException('Timesheet is signed.');
        }

        $now = CarbonImmutable::now();

        return TimeEntry::create([
            'organization_id' => $timesheet->organization_id,
            'project_id' => $timesheet->project_id,
            'timesheet_id' => $timesheet->id,
            'task_id' => $taskId,
            'user_id' => $user->id,
            'date' => $now->toDateString(),
            'started_at' => $now,
            'ended_at' => null,
            'break_minutes' => 0,
            'kind' => TimeEntry::KIND_WORK,
            'minutes' => 0,
            'description' => $description,
        ]);
    }

    public function stop(User $user): ?TimeEntry {
        $entry = $this->current($user);
        if (! $entry) {
            return null;
        }
        $entry->ended_at = Carbon::now();
        $entry->save(); // saving-hook berechnet minutes

        return $entry->refresh();
    }
}
