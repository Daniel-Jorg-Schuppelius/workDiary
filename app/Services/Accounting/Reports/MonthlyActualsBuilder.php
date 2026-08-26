<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthlyActualsBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Reports;

use App\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Ist-Summen je Konto und Kalendermonat (Feature 142, MVP-709).
 *
 * Ein Aufruf je Monat statt eines Monats-Ausdrucks in SQL: `MONTH()` und
 * `strftime()` sind treiberabhängig, zwölf kleine Abfragen über denselben
 * Index sind es nicht. Gemeinsame Quelle für Monatsraster der BWA und die
 * Übernahme „Vorjahr-Ist als Budget".
 */
class MonthlyActualsBuilder extends AbstractAccountingReportBuilder {
    /**
     * @param  list<CarbonImmutable>  $months  Monatserste in Reihenfolge
     * @return array<string, array<int, array{debit: numeric-string, credit: numeric-string}>> Monatsschlüssel `Y-m` → Konto-ID → Summen
     */
    public function build(Organization $organization, array $months, ?int $costCenterId = null): array {
        $result = [];
        foreach ($months as $month) {
            $start = $month->startOfMonth();
            $result[$start->format('Y-m')] = $this->sumsByAccount($organization, $start, $start->endOfMonth()->startOfDay(), null, $costCenterId);
        }

        return $result;
    }
}
