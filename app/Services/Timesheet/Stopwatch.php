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

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Platform\User;
use App\Models\Time\{TimeEntry, Timesheet};
use App\Services\TimeApproval\DayCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function start(User $user, Timesheet $timesheet, ?int $taskId = null, ?string $description = null, ?int $diaryEntryId = null): TimeEntry {
        return DB::transaction(function () use ($user, $timesheet, $taskId, $description, $diaryEntryId): TimeEntry {
            // Per-User serialisieren, sonst legen zwei parallele Start-Requests
            // (Doppelklick) beide an current() vorbei zwei laufende Einträge an;
            // stop() beendet dann nur einen.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($this->current($user)) {
                throw new StopwatchAlreadyRunningException();
            }
            if ($timesheet->isSigned()) {
                throw new RuntimeException('Timesheet is signed.');
            }

            $now = CarbonImmutable::now();
            $today = $now->setTimezone(\App\Support\Tz::current())->toDateString();
            // Abgeschlossener Tag oder freigegebener Monat: keine neue Buchung (MVP-1551).
            $lock = app(DayCloseService::class)->bookingLock($user, CarbonImmutable::parse($today));
            if ($lock !== null) {
                throw ValidationException::withMessages([
                    'stopwatch' => (string) __($lock === 'month' ? 'day-close.errors.month_entry_locked' : 'day-close.errors.day_entry_locked'),
                ]);
            }

            return TimeEntry::create([
                'organization_id' => $timesheet->organization_id,
                'project_id' => $timesheet->project_id,
                'timesheet_id' => $timesheet->id,
                'task_id' => $taskId,
                'diary_entry_id' => $diaryEntryId,
                'user_id' => $user->id,
                // Kalendertag lokal (Anzeige-Zeitzone), Zeitstempel bleiben UTC.
                'date' => $today,
                'started_at' => $now,
                'ended_at' => null,
                'break_minutes' => 0,
                'kind' => TimeEntryKind::Work->value,
                'minutes' => 0,
                'description' => $description,
            ]);
        });
    }

    /**
     * Laufende Stoppuhren auf einem Auftrag, gleich welcher Person.
     *
     * @return Collection<int, TimeEntry>
     */
    public function runningOnOrder(int $diaryEntryId): Collection {
        return TimeEntry::query()
            ->where('diary_entry_id', $diaryEntryId)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->with('user:id,name')
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Beendet alle Stoppuhren auf dem Auftrag; die Zeit wird gebucht wie beim Stopp von Hand.
     *
     * @return Collection<int, TimeEntry>
     */
    public function stopOnOrder(int $diaryEntryId): Collection {
        return $this->runningOnOrder($diaryEntryId)->each(function (TimeEntry $entry): void {
            $entry->ended_at = Carbon::now();
            $entry->save();
        });
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
