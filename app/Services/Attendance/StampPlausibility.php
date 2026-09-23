<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StampPlausibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\Platform\User;
use App\Services\TimeApproval\DayCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Ist ein von einem Gerät geliefertes Stempel-Datum glaubwürdig — und der Tag
 * noch offen?
 *
 * Drei Schranken, die der Online-Weg schon durch die Serverzeit hat: keine
 * Zukunft (bis auf Uhrenversatz), nicht älter als das Offline-Fenster, und der
 * Zieltag darf nicht abgeschlossen oder der Monat freigegeben sein. Für
 * Änderungen an gesperrten Tagen gibt es den Weg über eine Zeitkorrektur mit
 * Begründung und Genehmigung.
 *
 * Bis zum Sicherheitsaudit 2026-09-13 stand diese Prüfung nur im Offline-Sync.
 * Der Terminal-Eingang reichte `occurred_at` unverändert durch — wer das
 * Terminal-Token besaß, konnte in abgeschlossene Tage zurückstempeln und damit
 * Arbeitszeit, Zuschläge und Gleitzeitkonto nachträglich verändern.
 */
final class StampPlausibility {
    /** Wie weit ein Gerät vorgehen darf, bevor der Stempel als Zukunft gilt. */
    public const MAX_CLOCK_SKEW_MINUTES = 10;

    /** Wie weit ein Gerät nachreichen darf (Offline-Fenster). */
    public const MAX_BACKDATE_DAYS = 14;

    public function __construct(private readonly DayCloseService $dayClose) {}

    /**
     * @param  string  $field  Feldname für die Fehlermeldung
     *
     * @throws ValidationException
     */
    public function assert(User $user, string $raw, string $field): CarbonImmutable {
        $stamp = CarbonImmutable::parse($raw);
        $now = CarbonImmutable::now();

        if ($stamp->greaterThan($now->addMinutes(self::MAX_CLOCK_SKEW_MINUTES))) {
            throw ValidationException::withMessages([$field => (string) __('sync.error.stamp_in_future')]);
        }

        if ($stamp->lessThan($now->subDays(self::MAX_BACKDATE_DAYS))) {
            throw ValidationException::withMessages([
                $field => (string) __('sync.error.stamp_too_old', ['days' => self::MAX_BACKDATE_DAYS]),
            ]);
        }

        if ($this->dayClose->dayLockedFor($user, $stamp->startOfDay())) {
            throw ValidationException::withMessages([$field => (string) __('sync.error.day_locked')]);
        }

        return $stamp;
    }
}
