<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningWorkTimeClassifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\{Attendance, User, WorkSchedule};
use Illuminate\Support\Carbon;

/**
 * Ordnet eine Lernsitzung der Arbeitszeit zu (Feature 149, MVP-749).
 *
 * Reine Entscheidungslogik ohne Schreibzugriff — dadurch isoliert testbar,
 * wie der {@see \App\Services\Compliance\AttendanceComplianceChecker}.
 *
 * Reihenfolge der Prüfung:
 *  1. Erfasste Anwesenheit, die den Zeitraum überlappt ⇒ INSIDE. Die Zeit
 *     ist bereits als Arbeitszeit erfasst; eine zweite Buchung wäre
 *     Doppelzählung.
 *  2. Sonst der Arbeitszeitrahmen aus dem WorkSchedule (Wochentag gilt und
 *     Sitzung liegt in frame_start..frame_end) ⇒ INSIDE. Wer im Rahmen
 *     lernt, arbeitet — auch wenn der Stempel fehlt.
 *  3. Sonst ⇒ OUTSIDE.
 *
 * Ohne WorkSchedule und ohne Anwesenheit ist die Lage nicht entscheidbar:
 * dann UNKNOWN, und der Aufrufer behandelt es wie OUTSIDE (zugunsten der
 * lernenden Person, aber sichtbar gekennzeichnet).
 */
class LearningWorkTimeClassifier {
    public const INSIDE = 'inside';
    public const OUTSIDE = 'outside';
    public const UNKNOWN = 'unknown';

    public function classify(User $user, Carbon $start, Carbon $end): string {
        if ($this->overlapsAttendance($user, $start, $end)) {
            return self::INSIDE;
        }

        $schedule = $this->scheduleFor($user);

        if ($schedule === null) {
            return self::UNKNOWN;
        }

        return $this->withinFrame($schedule, $start, $end) ? self::INSIDE : self::OUTSIDE;
    }

    /** Zählt die Sitzung als Arbeitszeit, die noch nicht erfasst ist? */
    public function createsWorkTime(string $classification): bool {
        return $classification !== self::INSIDE;
    }

    private function overlapsAttendance(User $user, Carbon $start, Carbon $end): bool {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value])
            ->where('started_at', '<=', $end)
            ->where(function ($query) use ($start): void {
                // Offene Stempel (ended_at NULL) laufen bis jetzt.
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $start);
            })
            ->exists();
    }

    private function scheduleFor(User $user): ?WorkSchedule {
        return WorkSchedule::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    /**
     * Rahmenzeit des Arbeitszeitmodells. Ohne gepflegten Rahmen gilt der
     * Wochentag allein nicht als Beleg — dann OUTSIDE, damit die Zeit
     * nicht stillschweigend verschwindet.
     */
    private function withinFrame(WorkSchedule $schedule, Carbon $start, Carbon $end): bool {
        if (! $schedule->appliesOnWeekday((int) $start->dayOfWeekIso)) {
            return false;
        }

        $frameStart = $schedule->frame_start;
        $frameEnd = $schedule->frame_end;

        if ($frameStart === null || $frameEnd === null) {
            return false;
        }

        $from = $start->copy()->setTimeFromTimeString((string) $frameStart);
        $to = $start->copy()->setTimeFromTimeString((string) $frameEnd);

        return $start->greaterThanOrEqualTo($from) && $end->lessThanOrEqualTo($to);
    }
}
