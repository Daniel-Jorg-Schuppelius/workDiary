<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PayrollClassifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Payroll;

use App\Enums\User\EmploymentType;
use App\Models\User;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Plausibilitätsprüfung der Beschäftigungsart anhand des geschätzten
 * Monatsverdienstes (Stundenlohn × Monatsstunden aus dem Arbeitszeit-Modell)
 * gegen die Minijob-Grenze und die Midijob-Obergrenze (Übergangsbereich).
 */
class PayrollClassifier {
    /** Obergrenze Übergangsbereich (Midijob) in EUR/Monat (§ 20 Abs. 2 SGB IV). */
    public const MIDIJOB_UPPER = 2000.0;

    public function __construct(private readonly MinimumWageService $minimumWages = new MinimumWageService) {}

    /**
     * Geschätzter Monatsverdienst aus Stundenlohn und Monatsstunden
     * (Wochenstunden × 13/3). Null, wenn Lohn oder Arbeitszeit-Modell fehlen.
     */
    public function monthlyEarnings(User $user): ?float {
        $wage = $user->payroll_hourly_wage;
        $schedule = $user->workSchedule();
        if ($wage === null || $schedule === null) {
            return null;
        }

        $monthlyHours = ($schedule->weekly_minutes / 60) * (13 / 3);

        return round($wage->toFloat() * $monthlyHours, 2);
    }

    /**
     * Liefert einen Hinweistext, wenn die gewählte Beschäftigungsart nicht zum
     * geschätzten Monatsverdienst passt — sonst null. Bewusst nur ein Hinweis,
     * keine harte Sperre (Ausnahmen wie Azubi/Werkstudent existieren).
     */
    public function mismatchHint(User $user): ?string {
        $earnings = $this->monthlyEarnings($user);
        if ($earnings === null || $user->employment_type === null) {
            return null;
        }

        $limit = $this->minimumWages->minijobMonthlyLimit();
        $earningsFmt = NumberHelper::toGermanFormat($earnings, 2, withThousandsSeparator: true);

        return match ($user->employment_type) {
            EmploymentType::Minijob => ($limit !== null && $earnings > $limit)
                ? (string) __('Geschätzter Monatsverdienst :amount € übersteigt die Minijob-Grenze (:limit €) – eher Midijob.', ['amount' => $earningsFmt, 'limit' => $limit])
                : null,
            EmploymentType::Midijob => ($earnings > self::MIDIJOB_UPPER)
                ? (string) __('Geschätzter Monatsverdienst :amount € liegt über der Midijob-Obergrenze (:limit €) – reguläre Beschäftigung.', ['amount' => $earningsFmt, 'limit' => number_format(self::MIDIJOB_UPPER, 0, ',', '.')])
                : null,
            EmploymentType::Vollzeit, EmploymentType::Teilzeit => ($limit !== null && $earnings <= $limit)
                ? (string) __('Geschätzter Monatsverdienst :amount € liegt im Minijob-Bereich (≤ :limit €).', ['amount' => $earningsFmt, 'limit' => $limit])
                : null,
            default => null,
        };
    }
}
