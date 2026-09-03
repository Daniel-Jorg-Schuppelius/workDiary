<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\ReconciliationStatus;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Gesamtbericht: alle Firmen, Zählung je Status, offene Einkaufsgebühr.
 */
final readonly class ReconciliationReport {
    /**
     * @param  list<CompanyReconciliation>  $companies
     */
    public function __construct(
        public array $companies,
        public ReconciliationOptions $options,
    ) {}

    /**
     * @return list<PeriodFinding>
     */
    public function findings(): array {
        $out = [];
        foreach ($this->companies as $company) {
            array_push($out, ...$company->findings);
        }

        return $out;
    }

    /**
     * @return array<string, int> Status-Wert → Anzahl (alle Stati, auch 0)
     */
    public function countsByStatus(): array {
        $counts = [];
        foreach (ReconciliationStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }
        foreach ($this->findings() as $finding) {
            $counts[$finding->status->value]++;
        }

        return $counts;
    }

    /**
     * Einkaufsgebühr der fehlenden bzw. teilweise berechneten Perioden.
     */
    public function openFee(): Money {
        $sum = Money::zero($this->currency());
        foreach ($this->findings() as $finding) {
            if (in_array($finding->status, [ReconciliationStatus::Missing, ReconciliationStatus::Partial], true)) {
                $sum = $sum->plus($finding->openFee());
            }
        }

        return $sum;
    }

    /**
     * Einkaufsgebühr der Perioden ohne Kontaktzuordnung (unbekannter Stand).
     */
    public function unmappedFee(): Money {
        $sum = Money::zero($this->currency());
        foreach ($this->findings() as $finding) {
            if ($finding->status === ReconciliationStatus::Unmapped) {
                $sum = $sum->plus($finding->period->fee());
            }
        }

        return $sum;
    }

    /**
     * @return list<CompanyReconciliation>
     */
    public function unmappedCompanies(): array {
        return array_values(array_filter($this->companies, static fn(CompanyReconciliation $c): bool => ! $c->mapping->isResolved()));
    }

    private function currency(): CurrencyCode {
        foreach ($this->findings() as $finding) {
            return $finding->period->fee()->getCurrency();
        }

        return CurrencyCode::Euro;
    }
}
