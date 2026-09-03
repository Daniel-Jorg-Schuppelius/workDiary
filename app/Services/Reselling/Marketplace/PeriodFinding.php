<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodFinding.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Enums\Reselling\ReconciliationStatus;
use CommonToolkit\ValueObjects\Money;

/**
 * Befund zu einer Abrechnungsperiode.
 */
final readonly class PeriodFinding {
    /**
     * @param  list<array{line: InvoiceLine, quantity: float, exact?: bool, months?: float, monthly?: bool, annual_unit?: Money}>  $matches  verbrauchte Positionen: quantity in Lizenzen, months in Lizenzmonaten, annual_unit = Stückpreis aufs Jahr
     */
    public function __construct(
        public BillingPeriod $period,
        public ReconciliationStatus $status,
        public array $matches,
        public ?Money $lowestUnitNet,
        public float $uncoveredQuantity,
        public string $note = '',
    ) {}

    /**
     * Anteil der Einkaufsgebühr, dem keine Rechnung gegenübersteht.
     */
    public function openFee(): Money {
        if ($this->period->quantity <= 0 || $this->uncoveredQuantity <= 0) {
            return Money::zero($this->period->fee()->getCurrency());
        }

        return $this->period->fee()->times($this->uncoveredQuantity / $this->period->quantity);
    }

    /**
     * @return list<string> Rechnungsnummern (eindeutig)
     */
    public function voucherNumbers(): array {
        $numbers = [];
        foreach ($this->matches as $match) {
            $numbers[] = $match['line']->voucherNumber !== '' ? $match['line']->voucherNumber : $match['line']->voucherId;
        }

        return array_values(array_unique($numbers));
    }
}
