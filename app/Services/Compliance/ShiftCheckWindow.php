<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftCheckWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Enums\Vacation\VacationStatus;
use App\Models\Absence\Vacation;
use App\Models\Schedule\ScheduledShift;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;

/**
 * Vorgeladene Nachbarschichten und Urlaube für die Prüfung vieler Schichten
 * (Schichtplan): die Regeln fragen sonst je Schicht sechsmal die Datenbank.
 * Scoped gebunden und nur zwischen {@see open()} und {@see close()} wirksam —
 * wer eine einzelne Schicht beim Speichern prüft, fragt wie bisher frisch.
 */
final class ShiftCheckWindow {
    /** Größtes Fenster einer Regel um den Schichttag ({@see Rules\ConsecutiveDaysRule}), dazu ein Tag für Nachtschichten. */
    private const int MARGIN_DAYS = 15;

    private ?string $from = null;

    private ?string $to = null;

    /** @var array<int, list<ScheduledShift>> Nutzer → Schichten ohne abgesagte, nach ID */
    private array $shifts = [];

    /** @var array<int, list<Vacation>> Nutzer → genehmigte und beantragte Urlaube, nach ID */
    private array $vacations = [];

    /** @var array<string, mixed> */
    private array $memo = [];

    /** @param  iterable<ScheduledShift>  $shifts */
    public function open(iterable $shifts): void {
        $this->close();
        $days = [];
        $users = [];
        foreach ($shifts as $shift) {
            $days[] = $shift->date->format('Y-m-d');
            $users[(int) $shift->user_id] = true;
        }
        if ($days === []) {
            return;
        }
        $first = CarbonImmutable::parse(min($days))->subDays(self::MARGIN_DAYS);
        $last = CarbonImmutable::parse(max($days))->addDays(self::MARGIN_DAYS);
        $this->from = $first->toDateString();
        $this->to = $last->toDateString();
        $userIds = array_keys($users);
        $this->shifts = array_fill_keys($userIds, []);
        $this->vacations = array_fill_keys($userIds, []);

        $rows = ScheduledShift::query()
            ->whereIn('user_id', $userIds)
            ->where('status', '!=', ScheduledShiftStatus::Cancelled->value)
            ->whereBetween('date', DateRange::days($first, $last))
            ->with('shiftType')
            ->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $this->shifts[(int) $row->user_id][] = $row;
        }

        $rows = Vacation::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', [VacationStatus::Approved, VacationStatus::Pending])
            ->where('start_date', '<', DateRange::dayAfter($last))
            ->where('end_date', '>=', $this->from)
            ->orderBy('id')
            ->get();
        foreach ($rows as $row) {
            $this->vacations[(int) $row->user_id][] = $row;
        }
    }

    public function close(): void {
        $this->from = null;
        $this->to = null;
        $this->shifts = [];
        $this->vacations = [];
        $this->memo = [];
    }

    /**
     * Andere, nicht abgesagte Schichten des Mitarbeiters an den Tagen `$fromDay` bis `$toDay`
     * (einschließlich); null, wenn das Fenster den Zeitraum nicht deckt.
     *
     * @return Collection<int, ScheduledShift>|null
     */
    public function shifts(ScheduledShift $shift, string $fromDay, string $toDay): ?Collection {
        if (! $this->covers($shift, $fromDay, $toDay)) {
            return null;
        }
        $found = [];
        foreach ($this->shifts[(int) $shift->user_id] as $other) {
            $day = $other->date->format('Y-m-d');
            if ($day >= $fromDay && $day <= $toDay && (! $shift->exists || (int) $other->id !== (int) $shift->id)) {
                $found[] = $other;
            }
        }

        return new Collection($found);
    }

    /** Erster genehmigter oder beantragter Urlaub am Schichttag; false, wenn das Fenster den Tag nicht deckt. */
    public function vacation(ScheduledShift $shift): Vacation|false|null {
        $day = $shift->date->format('Y-m-d');
        if (! $this->covers($shift, $day, $day)) {
            return false;
        }
        foreach ($this->vacations[(int) $shift->user_id] as $vacation) {
            if ($vacation->start_date->format('Y-m-d') <= $day && $vacation->end_date->format('Y-m-d') >= $day) {
                return $vacation;
            }
        }

        return null;
    }

    /**
     * Merkt einen Wert für die Dauer des Fensters; ohne Fenster wird jedes Mal neu geladen.
     *
     * @template T
     *
     * @param  Closure(): T  $load
     * @return T
     */
    public function remember(string $key, Closure $load): mixed {
        if ($this->from === null) {
            return $load();
        }
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $load();
        }

        return $this->memo[$key];
    }

    private function covers(ScheduledShift $shift, string $fromDay, string $toDay): bool {
        return $this->from !== null && $this->to !== null
            && isset($this->shifts[(int) $shift->user_id])
            && $fromDay >= $this->from && $toDay <= $this->to;
    }
}
