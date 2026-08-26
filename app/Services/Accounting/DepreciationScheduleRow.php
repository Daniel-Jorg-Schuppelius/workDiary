<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DepreciationScheduleRow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine Jahreszeile des AfA-Plans (Feature 133, MVP-698).
 *
 * `fiscalYear` ist das Startjahr des Geschäftsjahres — bei abweichendem
 * Geschäftsjahr („2026/2027") also 2026. Damit passt der Schlüssel zum
 * Idempotenzschlüssel `depreciation:{id}:{fiscalYear}` des Adapters.
 */
final class DepreciationScheduleRow {
    public function __construct(
        public readonly int $fiscalYear,
        public readonly string $label,
        public readonly CarbonImmutable $startsOn,
        public readonly CarbonImmutable $endsOn,
        public readonly int $months,
        public readonly Money $amount,
        public readonly Money $bookValueEnd,
    ) {}

    /** @return array{fiscal_year: int, label: string, months: int, amount: string, book_value_end: string} */
    public function toArray(): array {
        return [
            'fiscal_year' => $this->fiscalYear,
            'label' => $this->label,
            'months' => $this->months,
            'amount' => $this->amount->getAmount(),
            'book_value_end' => $this->bookValueEnd->getAmount(),
        ];
    }
}
