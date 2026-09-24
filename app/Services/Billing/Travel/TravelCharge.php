<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelCharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Billing\Travel;

use App\Models\Diary\Tour;
use App\Services\Billing\DocumentTotalsCalculator;
use Illuminate\Support\Carbon;

/**
 * Eine abrechenbare Anfahrt-Position zu einer Tour, berechnet vom
 * {@see TravelChargeService}. Wird im {@see \App\Services\Invoicing\InvoiceGenerator}
 * zu einer Rechnungsposition.
 */
final class TravelCharge {
    public function __construct(
        public readonly Tour $tour,
        public readonly Carbon $date,
        public readonly float $quantity,
        public readonly string $unit,
        public readonly float $unitPrice,
        public readonly string $description,
    ) {}

    public function amount(): float {
        return DocumentTotalsCalculator::lineNet($this->quantity, $this->unitPrice)->withScale(2)->toFloat();
    }
}
