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

use App\Models\User;
use App\Services\TimeApproval\MonthClosureService;
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
            $day = CarbonImmutable::parse(is_string($value) ? $value : $value->format('Y-m-d'));
        } catch (\Throwable) {
            return; // Formatfehler meldet die `date`-Regel.
        }

        if (app(MonthClosureService::class)->isPeriodLockedForUser($user, $day)) {
            $fail((string) __('day-close.errors.month_entry_locked'));
        }
    }
}
