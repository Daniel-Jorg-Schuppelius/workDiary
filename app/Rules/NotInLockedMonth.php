<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotInLockedMonth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Rules;

use App\Models\Platform\User;
use App\Services\TimeApproval\DayCloseService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Das Datum darf nicht in einen freigegebenen Monat fallen.
 *
 * **Anlegen war die offene Flanke** (Sicherheitsscan 2026-08-23, S-32): die
 * Sperre des Monatsabschlusses hing an der Bearbeitung bestehender Einträge.
 * Wer seinen Juni freigegeben bekommen hatte, konnte danach neue Zeiten mit
 * `date` im Juni **anlegen** — Gleitzeitsaldo und die Lohnzeilen (Reisezeit,
 * Bereitschaft, Überstundenzuschlag) werden zur Exportzeit gerechnet und
 * stiegen mit, ganz ohne Korrekturantrag.
 *
 * Der Weg für nachträgliche Änderungen ist der Zeitkorrektur-Antrag.
 *
 * Seit MVP-1551 gilt dasselbe für den abgeschlossenen Tag
 * ({@see DayCloseService::bookingLock()}); die Regel nimmt Datum oder Zeitstempel.
 */
class NotInLockedMonth implements ValidationRule {
    public function __construct(private readonly ?User $target = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            return;
        }

        $user = $this->target ?? Auth::user();

        if (! $user instanceof User) {
            return;
        }

        try {
            $day = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) === 1
                ? CarbonImmutable::parse(trim($value))
                // Zeitstempel: der Kalendertag der Buchung ist der lokale Tag.
                : CarbonImmutable::parse($value)->setTimezone(Tz::current())->startOfDay();
        } catch (\Throwable) {
            return; // Formatfehler meldet die `date`-Regel.
        }

        $lock = app(DayCloseService::class)->bookingLock($user, CarbonImmutable::parse($day->toDateString()));
        if ($lock !== null) {
            $fail((string) __($lock === 'month' ? 'day-close.errors.month_entry_locked' : 'day-close.errors.day_entry_locked'));
        }
    }
}
