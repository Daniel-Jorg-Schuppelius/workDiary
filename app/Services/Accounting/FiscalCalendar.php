<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FiscalCalendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AccountingProfile;
use App\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Geschäftsjahr ↔ Kalendermonat (Feature 142, MVP-709).
 *
 * Budget und Monatsraster hängen am Geschäftsjahr, nicht am Kalenderjahr:
 * Ein Geschäftsjahr ab Juli 2026 heißt `2026` und umfasst Juli 2026 bis
 * Juni 2027. Der Startmonat kommt aus dem Buchhaltungsprofil.
 */
final class FiscalCalendar {
    public function startMonth(Organization $organization): int {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();
        $month = $profile instanceof AccountingProfile ? (int) ($profile->fiscal_year_start_month ?? 1) : 1;

        return max(1, min(12, $month));
    }

    /** Geschäftsjahr, in das ein Datum fällt (Kennung = Kalenderjahr des Beginns). */
    public function fiscalYearOf(CarbonImmutable $date, int $startMonth): int {
        return $date->month >= $startMonth ? $date->year : $date->year - 1;
    }

    public function startOf(int $fiscalYear, int $startMonth): CarbonImmutable {
        return new CarbonImmutable(sprintf('%04d-%02d-01 00:00:00', $fiscalYear, $startMonth));
    }

    public function endOf(int $fiscalYear, int $startMonth): CarbonImmutable {
        return $this->startOf($fiscalYear, $startMonth)->addYear()->subDay()->endOfDay();
    }

    /**
     * Die zwölf Monatsersten eines Geschäftsjahres in Reihenfolge.
     *
     * @return list<CarbonImmutable>
     */
    public function monthsOf(int $fiscalYear, int $startMonth): array {
        $months = [];
        $cursor = $this->startOf($fiscalYear, $startMonth);
        for ($i = 0; $i < 12; $i++) {
            $months[] = $cursor;
            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /** Position (0–11) eines Kalendermonats im Geschäftsjahr. */
    public function positionOf(int $calendarMonth, int $startMonth): int {
        return ($calendarMonth - $startMonth + 12) % 12;
    }
}
